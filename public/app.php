<?php

require __DIR__ . '/../vendor/autoload.php';

use LoadBalancer\Config;
use LoadBalancer\HealthChecker;
use LoadBalancer\LoadBalancer;
use LoadBalancer\Logger;
use Swoole\Coroutine\Http\Client;
use Swoole\Http\Response;
use Swoole\Http\Request;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Table;
use Swoole\Timer;

$serversTable = new Table(1024);
$serversTable->column('host', Table::TYPE_STRING, 64);
$serversTable->column('health_check_path', Table::TYPE_STRING, 64);
$serversTable->column('port', Table::TYPE_INT, 2);
$serversTable->column('weight', Table::TYPE_INT, 2);
$serversTable->column('is_healthy', Table::TYPE_INT, 1);
$serversTable->column('connections', Table::TYPE_INT, 4);
$serversTable->column('response_times', Table::TYPE_INT, 4);
$serversTable->create();

$globalTable = new Table(1024);
$globalTable->column('last_index', Table::TYPE_INT, 4);
$globalTable->create();

try {
    $config = new Config();

    /** @var \LoadBalancer\Server $server */
    foreach ($config->servers as $key => $server) {
        $serversTable->set(
            $key,
            [
                'host' => $server->getHost(),
                'port' => $server->getPort(),
                'health_check_path' => $server->getHealthCheckPath(),
                'weight' => $server->getWeight(),
                'is_healthy' => $server->isHealthy(),
                'connections' => $server->getConnections(),
                'response_times' => $server->getResponseTimes(),
            ]
        );
    }
} catch (\Throwable $e) {
    echo 'Loading config failed...' . PHP_EOL . $e->getMessage();

    die();
}

$swooleServer = new Server("0.0.0.0", 8080);

$loadBalancer = new LoadBalancer($serversTable, $globalTable, $config->strategy);

$logger = new Logger($config->accessLogPath, $config->errorLogPath, $config->accessLogFormat, $config->errorLogFormat);

$swooleServer->set([
    'worker_num' => $config->workerNum,
    'enable_coroutine' => true,
]);

$configFile = __DIR__ . '/../config/config.json';
$lastMtime = filemtime($configFile);

$swooleServer->on("start", function (Server $server) use ($serversTable, &$loadBalancer, &$logger, &$lastMtime, $configFile) {
    echo "Kraken running at http://0.0.0.0:8080\n";

    Process::signal(SIGINT, function () use ($server) {
        echo "Shutting down gracefully...\n";
        $server->shutdown();
    });

    // hot config reload
    Timer::tick(1000, function () use (&$loadBalancer, &$logger, &$lastMtime, $configFile, $serversTable) {
        clearstatcache(true, $configFile);
        $mtime = filemtime($configFile);

        if ($mtime === $lastMtime) {
            return;
        }

        echo "Reloading config...\n";
        $lastMtime = $mtime;

        try {
            $config = new Config();
            $config->updateConfig($loadBalancer, $logger);

            /** @var \LoadBalancer\Server $server */
            foreach ($config->servers as $key => $server) {
                $serversTable->set(
                    $key,
                    [
                        'host' => $server->getHost(),
                        'port' => $server->getPort(),
                        'health_check_path' => $server->getHealthCheckPath(),
                        'weight' => $server->getWeight(),
                        'is_healthy' => $server->isHealthy(),
                        'connections' => $server->getConnections(),
                        'response_times' => $server->getResponseTimes(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            echo 'Reloading config failed...' . PHP_EOL . $e->getMessage();
        }

        echo "Reload config ended...\n";
    });

    // health check
    Timer::tick(10000, function () use ($serversTable) {
        echo "Health check started...\n";

        new HealthChecker()->check($serversTable);

        echo "Health check ended...\n";
    });
});

$swooleServer->on("WorkerStart", function (Server $server, int $workerId) use (&$loadBalancer, &$logger, &$lastMtime, $configFile) {
    echo "Worker {$workerId} started\n";

    Process::signal(SIGINT, function () {
        echo "Worker received SIGINT, exiting...\n";
        exit(0);
    });
});

$swooleServer->on("request", function (Request $request, Response $response) use ($serversTable, &$loadBalancer, $logger) {
    $clientIp = $request->server['remote_addr'] ?? '127.0.0.1';
    $stickyCookie = $request->cookie['LBSESSION'] ?? null;

    [$target, $index] = $loadBalancer->getServer($stickyCookie ?? $clientIp);

    $serversTable->incr($index, 'connections');

    $client = new Client($target['host'], $target['port']);
    $client->set(['timeout' => 3]);
    $client->setMethod($request->server['request_method']);
    $client->setHeaders($request->header ?? []);
    $client->setData($request->rawContent());
    $client->execute($request->server['request_uri']);

    $serversTable->decr($index, 'connections');

    if ($client->errCode) {
        $response->status(502);
        $response->end("Bad Gateway");

        $logger->error("Failed to proxy to {$target['host']}:{$target['port']}: {$client->errMsg}");

        return;
    }

    $response->status($client->statusCode);

    $skipHeaders = ['transfer-encoding'];

    foreach ($client->headers ?? [] as $key => $val) {
        if (!in_array(strtolower($key), $skipHeaders)) {
            $response->header($key, $val);
        }
    }

    $response->cookie('LBSESSION', (string)$index, time() + 600);
    $response->end($client->body);

    $client->close();

    $logger->access($request->server);
});

$swooleServer->start();
