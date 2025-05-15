<?php

namespace KrakenTide;

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Swoole\Table;

class HealthChecker
{
    protected Table $serversTable;

    public function __construct(Table $table)
    {
        $this->serversTable = $table;
    }

    public function check(): void
    {
        echo "Health Check: Started..." . PHP_EOL;

        /** @var Server $server */
        foreach ($this->serversTable as $key => $server) {
            if (empty($server['health_check_path'])) {
                return;
            }

            Coroutine::create(function () use ($server, $key) {
                $cli = new Client($server['host'], $server['port']);
                $cli->set(['timeout' => 1]);
                $cli->get($server['health_check_path']);

                $isHealthy = (int)$cli->statusCode === 200;

                $server['is_healthy'] = (int)$isHealthy;

                if (!$isHealthy) {
                    $server['connections'] = 0;
                }

                $this->serversTable->set($key, $server);

                $cli->close();
            });
        }

        echo "Health Check: Done!" . PHP_EOL;
    }
}
