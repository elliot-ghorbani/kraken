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
            self::STRATEGY_STICKY => $this->sticky(),
            self::STRATEGY_ROUND_ROBIN => $this->roundRobin(),
            self::STRATEGY_WEIGHTED_ROUND_ROBIN => $this->weightedRoundRobin(),
            self::STRATEGY_LEAST_RESPONSE_TIME => $this->leastResponseTime(),
            self::STRATEGY_WEIGHTED_LEAST_RESPONSE_TIME => $this->weightedLeastResponseTime(),
            self::STRATEGY_LEAST_CONNECTION => $this->leastConnection(),
            self::STRATEGY_WEIGHTED_LEAST_CONNECTION => $this->weightedLeastConnection(),
            self::STRATEGY_IP_HASH => $this->ipHash(),
            default => array_key_first($this->healthyServers),
        };

        $this->globalTable->set(0, ['last_index' => $index]);

        return [$this->healthyServers[$index], $index];
    }

    private function setHealthyServers(): void
    {
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

    private function sticky(): int
    {
        // to be developed

        return 0;
    }

    private function ipHash(): int
    {
        // to be developed

        return 0;
    }

    private function roundRobin(): int
    {
        $lastIndex = array_key_first($this->healthyServers);

        if ($global = $this->globalTable->get(0)) {
            $globalLastIndex = $global['last_index'];

            if (isset($this->healthyServers[$globalLastIndex])) {
                $lastIndex = $globalLastIndex + 1;
            }
        };

        return $lastIndex % count($this->healthyServers);
    }

    private function weightedRoundRobin(): int
    {
        // to be developed

        return 0;
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
        // to be developed

        return 0;
    }

    private function leastResponseTime(): int
    {
        // to be developed

        return 0;
    }

    private function weightedLeastResponseTime(): int
    {
        // to be developed

        return 0;
    }
}
