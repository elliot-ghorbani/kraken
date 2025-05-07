<?php
namespace LoadBalancer;

use Dotenv\Dotenv;

class Config {
    public int $appPort;
    public int $workerNum;
    public array $servers;
    public string $strategy;
    public string $accessLogPath;
    public string $accessLogFormat;
    public string $errorLogPath;
    public string $errorLogFormat;

    public function __construct()
    {
        $this->loadConfig();
    }

    private function env(string $key, $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    private function loadConfig(): void
    {
        $dotenv = Dotenv::createMutable(__DIR__ . '/../');
        $dotenv->load();

        $this->appPort = (int)$this->env('APP_PORT', 8080);
        $this->workerNum = (int)$this->env('WORKER_NUM', swoole_cpu_num());
        $this->servers = explode(',', $this->env('SERVERS', ''));
        $this->strategy = $this->env('STRATEGY', 'round_robin');
        $this->accessLogPath = $this->env('ACCESS_LOG_PATH', '/tmp/php_lb_access.log');
        $this->accessLogFormat = $this->env(
            'ACCESS_LOG_FORMAT',
            '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"',
        );
        $this->errorLogPath = $this->env('ERROR_LOG_PATH', '/tmp/php_lb_error.log');
        $this->errorLogFormat = $this->env('ERROR_LOG_FORMAT', '[$time_local] [error] $message');
    }
    
    public function updateConfig(LoadBalancer $loadBalancer, Logger $logger): void
    {
        $loadBalancer->updateConfig($this->servers, $this->strategy);
        $logger->updateConfig($this->accessLogPath, $this->errorLogPath, $this->accessLogFormat, $this->errorLogFormat);
    }
}
