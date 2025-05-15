<?php

namespace KrakenTide\Dependencies;

use KrakenTide\Tables\ServersTable;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

class HealthChecker extends AbstrctDependency
{
    public function handle(): void
    {
        echo "Health Check: Started..." . PHP_EOL;

        foreach ($this->app->getServersTable() as $key => $server) {
            if (empty($server[ServersTable::HEALTH_CHECK_PATH])) {
                continue;
            }

            Coroutine::create(function () use ($server, $key) {
                $cli = new Client($server[ServersTable::HOST], $server[ServersTable::PORT]);
                $cli->set(['timeout' => 1]);
                $cli->get($server[ServersTable::HEALTH_CHECK_PATH]);

                $isHealthy = (int)$cli->statusCode === 200;

                $server[ServersTable::IS_HEALTHY] = (int)$isHealthy;

                if (!$isHealthy) {
                    $server[ServersTable::CONNECTIONS] = 0;
                }

                $this->app->getServersTable()->set($key, $server);

                $cli->close();
            });
        }

        echo "Health Check: Done!" . PHP_EOL;
    }
}
