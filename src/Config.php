<?php
namespace LoadBalancer;

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

    private function loadConfig(): void
    {
        $configs = json_decode(
            file_get_contents(__DIR__ . '/../config/config.json'), true, flags: JSON_THROW_ON_ERROR
        );

        $this->workerNum = $configs['worker_num'] ?? swoole_cpu_num();
        $this->servers = $configs['servers'];
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
        $loadBalancer->updateConfig($this->servers, $this->strategy);
        $logger->updateConfig($this->accessLogPath, $this->errorLogPath, $this->accessLogFormat, $this->errorLogFormat);
    }
}
