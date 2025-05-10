<?php

namespace KrakenTide;

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Swoole\Table;

class HealthChecker
{
    public function check(Table $table): void
    {
        /** @var Server $server */
        foreach ($table as $key => $server) {
            if (empty($server['health_check_path'])) {
                return;
            }

            Coroutine::create(function () use ($server, $table, $key) {
                $cli = new Client($server['host'], $server['port']);
                $cli->set(['timeout' => 1]);
                $cli->get($server['health_check_path']);

                $isHealthy = (int)$cli->statusCode === 200;

                $server['is_healthy'] = (int)$isHealthy;

                if (!$isHealthy) {
                    $server['connections'] = 0;
                }

                $table->set($key, $server);

                $cli->close();
            });
        }
    }
}
