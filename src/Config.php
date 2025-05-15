<?php

namespace KrakenTide;

class Config
{
    private const string CONFIG_FILE_PATH = __DIR__ . '/../config/config.json';

    private array $appConfigs;
    private ServerCollection $servers;
    private string $strategy;
    private string $accessLogPath;
    private string $accessLogFormat;
    private string $errorLogPath;
    private string $errorLogFormat;
    private int $rateLimiterCount;
    private int $rateLimiterDuration;

    public function loadConfig(): void
    {
        $configs = json_decode(
            file_get_contents(self::CONFIG_FILE_PATH),
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
            'http_compression' => true,
        ];

        if (isset($serverConfigs['worker_num'])) {
            $this->appConfigs['worker_num'] = $serverConfigs['worker_num'];
        }

        $this->servers = new ServerCollection();
        foreach ($configs['servers'] ?? [] as $item) {
            $this->servers->add(new Server($item));
        }

        $this->strategy = $configs['strategy'] ?? 'round_robin';

        if (isset($configs['rate_limiter_count']) && $configs['rate_limiter_duration']) {
            $this->rateLimiterCount = $configs['rate_limiter_count'];
            $this->rateLimiterDuration = $configs['rate_limiter_duration'];
        }

        $this->accessLogPath = $configs['access_log']['path'] ?? '/tmp/php_lb_access.log';
        $this->accessLogFormat = $configs['access_log']['format']
            ?? '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"';

        $this->errorLogPath = $configs['error_log']['path'] ?? '/tmp/php_lb_error.log';
        $this->errorLogFormat = $configs['error_log']['format']
            ?? '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"';
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

    public static function getConfigFileTime(): int
    {
        clearstatcache(true, self::CONFIG_FILE_PATH);

        return filemtime(self::CONFIG_FILE_PATH);
    }

    public function getRateLimiterCount(): int
    {
        return $this->rateLimiterCount;
    }

    public function getRateLimiterDuration(): int
    {
        return $this->rateLimiterDuration;
    }
}
