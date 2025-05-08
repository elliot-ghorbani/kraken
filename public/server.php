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
use Swoole\Timer;

try {
    $config = new Config();
} catch (\Throwable $e) {
    echo 'Loading config failed...' . PHP_EOL . $e->getMessage();

    die();
}

$swooleServer = new Server("127.0.0.1", 8080);
$loadBalancer = new LoadBalancer($config->servers, $config->strategy);
$logger = new Logger($config->accessLogPath, $config->errorLogPath, $config->accessLogFormat, $config->errorLogFormat);

$swooleServer->set([
    'worker_num' => $config->workerNum,
    'enable_coroutine' => true,
]);

$swooleServer->on("start", function (Server $server) use ($config) {
    echo "PHP Load Balancer running at http://127.0.0.1:8080\n";

    Process::signal(SIGINT, function() use ($server) {
        echo "Shutting down gracefully...\n";
        $server->shutdown();
    });
});

$configFile = __DIR__ . '/../config/config.json';
$lastMtime = filemtime($configFile);

$swooleServer->on("WorkerStart", function (Server $server, int $workerId) use (&$loadBalancer, &$logger, &$lastMtime, $configFile) {
    Process::signal(SIGINT, function () {
        echo "Worker received SIGINT, exiting...\n";
        exit(0);
    });

    // hot config reload
    Timer::tick(1000, function () use (&$loadBalancer, &$logger, &$lastMtime, $configFile) {
        clearstatcache(true, $configFile);
        $mtime = filemtime($configFile);

        if ($mtime === $lastMtime) {
            return;
        }

        echo "Reloading config...\n";
        $lastMtime = $mtime;

        try {
            new Config()->updateConfig($loadBalancer, $logger);
        } catch (\Throwable $e) {
            echo 'Reloading config failed...' . PHP_EOL . $e->getMessage();
        }
    });

    // health check
    Timer::tick(10000, function () use (&$loadBalancer) {
        echo "Health check started...\n";

        new HealthChecker()->check($loadBalancer);
    });
});

$swooleServer->on("request", function (Request $request, Response $response) use (&$loadBalancer, $logger) {
    $clientIp = $request->server['remote_addr'] ?? '127.0.0.1';
    $stickyCookie = $request->cookie['LBSESSION'] ?? null;

    [$target, $index] = $loadBalancer->getServer($stickyCookie ?? $clientIp);

    $loadBalancer->connections[$index]++;

    $client = new Client($target['host'], $target['port']);
    $client->set(['timeout' => 3]);
    $client->setMethod($request->server['request_method']);
    $client->setHeaders($request->header ?? []);
    $client->setData($request->rawContent());
    $client->execute($request->server['request_uri']);

    $loadBalancer->connections[$index]--;

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
