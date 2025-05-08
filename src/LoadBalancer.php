<?php

namespace LoadBalancer;

class LoadBalancer
{
    public array $servers;
    public array $weights = [];
    public array $connections = [];
    public array $responseTimes = [];
    public array $healthStatus = [];
    public int $lastIndex = -1;
    public int $currentWeight = 0;
    public string $strategy;

    public function __construct(array $servers, string $strategy)
    {
        $this->updateConfig($servers, $strategy);
    }

    public function updateConfig(array $servers, string $strategy): void
    {
        $this->servers = $servers;
        $this->strategy = $strategy;
        $this->healthStatus = array_fill(0, count($servers), true);
        $this->connections = array_fill(0, count($servers), 0);
        $this->weights = array_fill(0, count($servers), 1);
        $this->responseTimes = array_fill(0, count($servers), 50);
    }

    private function getHealthyServers(): array
    {
        return array_filter($this->servers, fn($_, $i) => $this->healthStatus[$i], ARRAY_FILTER_USE_BOTH);
    }

    public function getServer(?string $clientId = null): array
    {
        $healthyServers = $this->getHealthyServers();

        if (empty($healthyServers)) {
            return [$this->servers[0], 0];
        }

        $strategy = $this->strategy;

        if ($strategy === 'sticky' && is_numeric(
                $clientId
            ) && isset($healthyServers[$clientId]) && $this->healthStatus[$clientId]) {
            return [$healthyServers[$clientId], $clientId];
        }

        switch ($strategy) {
            case 'round_robin':
                $this->lastIndex = ($this->lastIndex + 1) % count($healthyServers);
                break;
            case 'random':
                $this->lastIndex = array_rand($healthyServers);
                break;
            case 'least_conn':
                $healthyIndexes = array_keys($healthyServers);

                $healthyServersConnections = array_filter(
                    $this->connections,
                    function ($serverIndex) use ($healthyIndexes) {
                        return in_array($serverIndex, $healthyIndexes);
                    },
                    ARRAY_FILTER_USE_KEY
                );

                $this->lastIndex = array_search(min($healthyServersConnections), $healthyServersConnections);

                break;
            case 'ip_hash':
                $this->lastIndex = crc32($clientId ?? 'default') % count($healthyServers);
                break;
            default:
                $this->lastIndex = 0;
        }

        $index = $this->lastIndex;

        return [$healthyServers[$index], $index];
    }
}
