<?php
namespace LoadBalancer;

use Swoole\Coroutine\Http\Client;

class HealthChecker {
    public function check(LoadBalancer $balancer): void
    {
        foreach ($balancer->servers as $i => $server) {
            [$host, $port] = explode(':', $server);
            go(function () use ($balancer, $i, $host, $port) {
                $cli = new Client($host, (int)$port);
                $cli->set(['timeout' => 1]);
                $cli->get("/health");

                $balancer->healthStatus[$i] = $cli->statusCode === 200;

                $cli->close();
            });
        }
    }
}
