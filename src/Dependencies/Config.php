<?php

namespace KrakenTide\Dependencies;

use KrakenTide\Tables\GlobalTable;
use KrakenTide\Tables\ReportsTable;
use KrakenTide\Tables\ServersTable;
use Swoole\Constant;

class Config extends AbstrctDependency
{
    private const string CONFIG_FILE_PATH = __DIR__ . '/../../config/config.json';

    private array $configArray;
    private array $appConfigs;

    public function loadConfig(): void
    {
        $this->configArray = json_decode(
            file_get_contents(self::CONFIG_FILE_PATH),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->setAppConfigs();

        $this->serServersConfig();

        $this->setGlobalConfig();

        $this->setReportsConfig();
    }

    public function getAppConfigs(): array
    {
        return $this->appConfigs;
    }

    public static function getConfigFileTime(): int
    {
        clearstatcache(true, self::CONFIG_FILE_PATH);

        return filemtime(self::CONFIG_FILE_PATH);
    }

    private function setAppConfigs(): void
    {
        $this->appConfigs = [
            Constant::OPTION_ENABLE_COROUTINE => true,
            Constant::OPTION_MAX_COROUTINE => 100000,
            Constant::OPTION_MAX_CONNECTION => 100000,
            Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
            Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
            Constant::OPTION_MAX_REQUEST => 0,
            Constant::OPTION_OPEN_CPU_AFFINITY => true,
            Constant::OPTION_OPEN_TCP_NODELAY => true,
            Constant::OPTION_ENABLE_REUSE_PORT => true,
            Constant::OPTION_HTTP_COMPRESSION => true,
            Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
        ];

        if (isset($this->configArray['worker_num'])) {
            $this->appConfigs[Constant::OPTION_WORKER_NUM] = $this->configArray['worker_num'];
        }

        if (isset($this->configArray['ssl_cert_file']) && $this->configArray['ssl_key_file']) {
            $this->appConfigs[Constant::OPTION_SSL_CERT_FILE] = $this->configArray['ssl_cert_file'];
            $this->appConfigs[Constant::OPTION_SSL_KEY_FILE] = $this->configArray['ssl_key_file'];
        }
    }

    private function serServersConfig(): void
    {
        foreach ($this->configArray['servers'] as $key => $server) {
            $this->app->getServersTable()->set(
                $key,
                [
                    ServersTable::HOST => $server['host'],
                    ServersTable::PORT => $server['port'],
                    ServersTable::SSL => (int)$server['ssl'],
                    ServersTable::HEALTH_CHECK_PATH => $server['health_check_path'] ?? '',
                    ServersTable::WEIGHT => $server['weight'],
                    ServersTable::IS_HEALTHY => 1,
                    ServersTable::CONNECTIONS => 0,
                    ServersTable::RESPONSE_TIME => 0,
                ]
            );
        }
    }

    private function setGlobalConfig(): void
    {
        $globalConfigs = [
            GlobalTable::STRATEGY => $this->configArray['strategy'] ?? LoadBalancer::STRATEGY_ROUND_ROBIN,
            GlobalTable::CONFIG_FILE_TIME => filectime(self::CONFIG_FILE_PATH)
        ];

        if (isset($this->configArray['rate_limiter_count']) && $this->configArray['rate_limiter_duration']) {
            $globalConfigs[GlobalTable::RATE_LIMITER_DURATION] = $this->configArray['rate_limiter_duration'];
            $globalConfigs[GlobalTable::RATE_LIMITER_COUNT] = $this->configArray['rate_limiter_count'];
        }

        $this->app->getGlobalTable()->set(GlobalTable::GLOBAL_KEY, $globalConfigs);
    }

    private function setReportsConfig(): void
    {
        $this->app->getReportsTable()
            ->set(
                ReportsTable::ACCESS,
                [
                    ReportsTable::PATH => $this->configArray['access_log']['path'] ?? '/tmp/php_lb_access.log',
                    ReportsTable::FORMAT => $this->configArray['access_log']['format']
                        ?? '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"',
                ]
            );

        $this->app->getReportsTable()
            ->set(
                ReportsTable::ERROR,
                [
                    ReportsTable::PATH => $this->configArray['error_log']['path'] ?? '/tmp/php_lb_error.log',
                    ReportsTable::FORMAT => $this->configArray['error_log']['format']
                        ?? '$remote_addr - $host "$request_method $request_uri" $status $request_time "$http_user_agent"',
                ]
            );
    }
}
