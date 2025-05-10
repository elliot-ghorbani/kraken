<?php

namespace LoadBalancer;

class Server
{
    private string $host;

    private int $port;
    private bool $ssl;

    private ?string $healthCheckPath;

    private int $weight;

    private bool $isHealthy = true;

    private int $connections = 0;
    private int $responseTimes = 0;

    public function __construct(array $serverData)
    {
        $this->host = $serverData['host'];
        $this->port = $serverData['port'];
        $this->ssl = $serverData['ssl'];
        $this->healthCheckPath = $serverData['health_check_path'] ?? null;
        $this->weight = $serverData['weight'] ?? 1;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getHealthCheckPath(): ?string
    {
        return $this->healthCheckPath;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    public function getConnections(): int
    {
        return $this->connections;
    }

    public function getResponseTimes(): int
    {
        return $this->responseTimes;
    }

    public function isSsl(): bool
    {
        return $this->ssl;
    }
}
