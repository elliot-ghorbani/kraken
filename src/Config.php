<?php

namespace KrakenTide;

class Config
{
    private array $appConfigs;
    private ServerCollection $servers;
    private string $strategy;
    private string $accessLogPath;
    private string $accessLogFormat;
    private string $errorLogPath;
    private string $errorLogFormat;

    public function loadConfig(): void
    {
        $configs = json_decode(
            file_get_contents(__DIR__ . '/../config/config.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->appConfigs = [
            'enable_coroutine' => true,
            'max_coroutine' => 100000,
            'max_connection' => 100000,
            'socket_buffer_size' => 2 * 1024 * 1024,
            'buffer_output_size' => 2 * 1024 * 1024,
            'max_request' => 0,
            'open_tcp_nodelay' => true,
            'enable_reuse_port' => true,
        ];

        if (isset($serverConfigs['worker_num'])) {
            $this->appConfigs['worker_num'] = $serverConfigs['worker_num'];
        }

        $this->servers = new ServerCollection();
        foreach ($configs['servers'] ?? [] as $item) {
            $this->servers->add(new Server($item));
        }

        $this->strategy = $configs['strategy'] ?? 'round_robin';

        if (isset($configs['access_log'])) {
            $this->accessLogPath = $configs['access_log']['path'] ?? '/tmp/php_lb_access.log';
            $this->accessLogFormat = $configs['access_log']['format']
                ?? '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"';
        }

        if (isset($configs['error_log'])) {
            $this->errorLogPath = $configs['error_log']['path'] ?? '/tmp/php_lb_error.log';
            $this->errorLogFormat = $configs['error_log']['format']
                ?? '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"';
        }
    }

    public function updateConfig(LoadBalancer $loadBalancer, Logger $logger): void
    {
        $loadBalancer->updateConfig($this->strategy);
        $logger->updateConfig($this->accessLogPath, $this->errorLogPath, $this->accessLogFormat, $this->errorLogFormat);
    }

    public function getAppConfigs(): array
    {
        return $this->appConfigs;
    }

    public function getServers(): ServerCollection
    {
        return $this->servers;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function getAccessLogPath(): string
    {
        return $this->accessLogPath;
    }

    public function getAccessLogFormat(): string
    {
        return $this->accessLogFormat;
    }

    public function getErrorLogPath(): string
    {
        return $this->errorLogPath;
    }

    public function getErrorLogFormat(): string
    {
        return $this->errorLogFormat;
    }
}
