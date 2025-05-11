<?php

namespace KrakenTide;

use Swoole\Table;

class LoadBalancer
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

    private Table $serversTable;
    private Table $globalTable;
    private string $strategy;
    private array $healthyServers = [];

    public function __construct(Table $serversTable, Table $globalTable, string $strategy)
    {
        $this->serversTable = $serversTable;
        $this->globalTable = $globalTable;

        $this->updateConfig($strategy);
    }

    public function updateConfig(string $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getServer(?string $clientId = null): array
    {
        $this->setHealthyServers();

        // do something here
        if (empty($this->healthyServers)) {
            return [$this->serversTable[0], 0];
        }

        $index = match ($this->strategy) {
            self::STRATEGY_RANDOM => $this->random(),
            self::STRATEGY_STICKY => $this->sticky($clientId),
            self::STRATEGY_ROUND_ROBIN => $this->roundRobin(),
            self::STRATEGY_WEIGHTED_ROUND_ROBIN => $this->weightedRoundRobin(),
            self::STRATEGY_LEAST_RESPONSE_TIME => $this->leastResponseTime(),
            self::STRATEGY_WEIGHTED_LEAST_RESPONSE_TIME => $this->weightedLeastResponseTime(),
            self::STRATEGY_LEAST_CONNECTION => $this->leastConnection(),
            self::STRATEGY_WEIGHTED_LEAST_CONNECTION => $this->weightedLeastConnection(),
            self::STRATEGY_IP_HASH => $this->ipHash($clientId),
            default => array_key_first($this->healthyServers),
        };

        $this->globalTable->set(0, ['last_index' => $index]);

        return [$this->healthyServers[$index], $index];
    }

    private function setHealthyServers(): void
    {
        $this->healthyServers = [];

        foreach ($this->serversTable as $key => $item) {
            if (!$item['is_healthy']) {
                continue;
            }

            $this->healthyServers[$key] = $item;
        }
    }

    private function random(): int
    {
        return array_rand($this->healthyServers);
    }

    private function sticky(?string $clientId = null): int
    {
        if ($clientId === null) {
            return $this->random();
        }

        $hash = crc32($clientId);
        $index = $hash % count($this->healthyServers);

        return array_keys($this->healthyServers)[$index];
    }

    private function ipHash(?string $clientId = null): int
    {
        if ($clientId === null) {
            return $this->random();
        }

        $hash = crc32($clientId);
        $index = $hash % count($this->healthyServers);

        return array_keys($this->healthyServers)[$index];
    }

    private function roundRobin(): int
    {
        $lastIndex = array_key_first($this->healthyServers);

        if ($global = $this->globalTable->get(0)) {
            $nextIndex = ($global['last_index'] + 1)  % count($this->healthyServers);

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
            $weights = array_merge($weights, array_fill(0, $server['weight'], $key));
        }

        $lastIndex = 0;
        if ($global = $this->globalTable->get(0)) {
            $lastIndex = ($global['last_index'] + 1) % count($weights);
        }

        return $weights[$lastIndex];
    }

    private function leastConnection(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            return $a['connections'] <=> $b['connections'];
        });

        return array_key_first($this->healthyServers);
    }

    private function weightedLeastConnection(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            $aValue = $a['connections'] / max(1, $a['weight']);
            $bValue = $b['connections'] / max(1, $b['weight']);

            return $aValue <=> $bValue;
        });

        return array_key_first($this->healthyServers);
    }

    private function leastResponseTime(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            return $a['response_time'] <=> $b['response_time'];
        });

        return array_key_first($this->healthyServers);
    }

    private function weightedLeastResponseTime(): int
    {
        uasort($this->healthyServers, function (array $a, array $b) {
            $aValue = $a['response_time'] / max(1, $a['weight']);
            $bValue = $b['response_time'] / max(1, $b['weight']);
            return $aValue <=> $bValue;
        });

        return array_key_first($this->healthyServers);
    }
}
