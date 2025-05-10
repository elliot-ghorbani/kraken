<?php

require __DIR__ . '/../vendor/autoload.php';

use KrakenTide\App;
use KrakenTide\Config;
use KrakenTide\HealthChecker;
use KrakenTide\LoadBalancer;
use KrakenTide\Logger;
use Swoole\Coroutine\Http\Client;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Timer;

$app = App::getInstance();
$serversTable = $app->getServersTable();
$globalTable = $app->getGlobalTable();

try {
    $config = new Config();

    /** @var \KrakenTide\Server $server */
    foreach ($config->servers as $key => $server) {
        $serversTable->set(
            $key,
            [
                'host' => $server->getHost(),
                'port' => $server->getPort(),
                'ssl' => (int)$server->isSsl(),
                'health_check_path' => $server->getHealthCheckPath(),
                'weight' => $server->getWeight(),
                'is_healthy' => (int)$server->isHealthy(),
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
                        'ssl' => (int)$server->isSsl(),
                        'health_check_path' => $server->getHealthCheckPath(),
                        'weight' => $server->getWeight(),
                        'is_healthy' => (int)$server->isHealthy(),
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

    $client = new Client($target['host'], $target['port'], $target['ssl']);
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
