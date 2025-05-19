<?php

namespace KrakenTide\Dependencies;

use KrakenTide\Tables\GlobalTable;
use KrakenTide\Tables\ServersTable;
use Swoole\Http\Request;

class LoadBalancer extends AbstrctDependency
{
    public const string STRATEGY_RANDOM = 'random';
    public const string STRATEGY_LEAST_CONNECTION = 'least_conn';
    public const string STRATEGY_WEIGHTED_LEAST_CONNECTION = 'weighted_least_conn';
    public const string STRATEGY_ROUND_ROBIN = 'round_robin';
    public const string STRATEGY_WEIGHTED_ROUND_ROBIN = 'weighted_round_robin';
    public const string STRATEGY_LEAST_RESPONSE_TIME = 'least_response_time';
    public const string STRATEGY_WEIGHTED_LEAST_RESPONSE_TIME = 'weighted_least_response_time';
    public const string STRATEGY_STICKY = 'sticky';
    public const string STRATEGY_IP_HASH = 'ip_hash';

    public const string SESSION_COOKIE = 'LBSESSION';

    private array $healthyServers = [];

    private function getStrategy(): string
    {
        $globalConfigs = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY);

        return $globalConfigs[GlobalTable::STRATEGY];
    }

    public function getServer(Request $request): array
    {
        $this->setHealthyServers();

        if (empty($this->healthyServers)) {
            throw new \RuntimeException('No healthy servers found.');
        }

        $index = match ($this->getStrategy()) {
            self::STRATEGY_RANDOM => $this->random(),
            self::STRATEGY_STICKY => $this->sticky($request),
            self::STRATEGY_ROUND_ROBIN => $this->roundRobin(),
            self::STRATEGY_WEIGHTED_ROUND_ROBIN => $this->weightedRoundRobin(),
            self::STRATEGY_LEAST_RESPONSE_TIME => $this->leastResponseTime(),
            self::STRATEGY_WEIGHTED_LEAST_RESPONSE_TIME => $this->weightedLeastResponseTime(),
            self::STRATEGY_LEAST_CONNECTION => $this->leastConnection(),
            self::STRATEGY_WEIGHTED_LEAST_CONNECTION => $this->weightedLeastConnection(),
            self::STRATEGY_IP_HASH => $this->ipHash($request),
            default => array_key_first($this->healthyServers),
        };

        $globalConfigs = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY);
        $globalConfigs[GlobalTable::LAST_INDEX] = $index;

        $this->app->getGlobalTable()->set(GlobalTable::GLOBAL_KEY, $globalConfigs);

        return [$this->healthyServers[$index], $index];
    }

    private function setHealthyServers(): void
    {
        $this->healthyServers = [];

        foreach ($this->app->getServersTable() as $key => $item) {
            if (!$item[ServersTable::IS_HEALTHY]) {
                continue;
            }

            $this->healthyServers[$key] = $item;
        }
    }

    private function random(): int
    {
        return array_rand($this->healthyServers);
    }

    private function sticky(Request $request): int
    {
        $stickyCookie = $request->cookie[self::SESSION_COOKIE] ?? null;

        if ($stickyCookie === null) {
            return $this->random();
        }

        $hash = crc32($stickyCookie);
        $index = $hash % count($this->healthyServers);

        return array_keys($this->healthyServers)[$index];
    }

    private function ipHash(Request $request): int
    {
        $clientIp = $request->server['remote_addr'] ?? '127.0.0.1';

        if ($clientIp === null) {
            return $this->random();
        }

        $hash = crc32($clientIp);
        $index = $hash % count($this->healthyServers);

        return array_keys($this->healthyServers)[$index];
    }

    private function roundRobin(): int
    {
        $lastIndex = array_key_first($this->healthyServers);

        if ($global = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY)) {
            $nextIndex = ($global[GlobalTable::LAST_INDEX] + 1)  % count($this->healthyServers);

            if (isset($this->healthyServers[$nextIndex])) {
                $lastIndex = $nextIndex;
            }
        }

        return $lastIndex;
    }

    private function weightedRoundRobin(): int
    {
        $weights = [];
        foreach ($this->healthyServers as $key => $server) {
            $weights = array_merge($weights, array_fill(0, $server[ServersTable::WEIGHT], $key));
        }

        $lastIndex = 0;
        if ($global = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY)) {
            $lastIndex = ($global[GlobalTable::LAST_INDEX] + 1) % count($weights);
        }

        return $weights[$lastIndex];
    }

    private function leastConnection(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            return $a[ServersTable::CONNECTIONS] <=> $b[ServersTable::CONNECTIONS];
        });

        return array_key_first($this->healthyServers);
    }

    private function weightedLeastConnection(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            $aValue = $a[ServersTable::CONNECTIONS] / max(1, $a[ServersTable::WEIGHT]);
            $bValue = $b[ServersTable::CONNECTIONS] / max(1, $b[ServersTable::WEIGHT]);

            return $aValue <=> $bValue;
        });

        return array_key_first($this->healthyServers);
    }

    private function leastResponseTime(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            return $a[ServersTable::RESPONSE_TIME] <=> $b[ServersTable::RESPONSE_TIME];
        });

        return array_key_first($this->healthyServers);
    }

    private function weightedLeastResponseTime(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            $aValue = $a[ServersTable::RESPONSE_TIME] / max(1, $a[ServersTable::WEIGHT]);
            $bValue = $b[ServersTable::RESPONSE_TIME] / max(1, $b[ServersTable::WEIGHT]);
            return $aValue <=> $bValue;
        });

        return array_key_first($this->healthyServers);
    }
}
