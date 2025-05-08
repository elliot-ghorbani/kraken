<?php

namespace LoadBalancer;

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

class HealthChecker
{
    public function check(LoadBalancer $balancer): void
    {
        foreach ($balancer->servers as $i => $server) {
            if (!isset($server['health_check_path'])) {
                return;
            }

            Coroutine::create(function () use ($balancer, $i, $server) {
                $cli = new Client($server['host'], $server['port']);
                $cli->set(['timeout' => 1]);
                $cli->get($server['health_check_path']);

                $isHealthy = $cli->statusCode === 200;

                $balancer->healthStatus[$i] = $isHealthy;

                if (!$isHealthy) {
                    $balancer->connections[$i] = 0;
                }

                $cli->close();
            });
        }
    }
}
