<?php

require __DIR__ . '/../vendor/autoload.php';

use KrakenTide\App;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Timer;

$app = App::getInstance();

$swooleServer = new Server("0.0.0.0", 8080);

$swooleServer->set([
    'worker_num' => $app->getConfig()->workerNum,
    'enable_coroutine' => true,
]);

$configFile = __DIR__ . '/../config/config.json';
$lastMtime = filemtime($configFile);

$swooleServer->on("start", function (Server $server) use ($app, &$lastMtime, $configFile) {
    echo "Kraken running at http://0.0.0.0:8080" . PHP_EOL;

    Process::signal(SIGINT, function () use ($server) {
        echo "Shutting down gracefully..." . PHP_EOL;
        $server->shutdown();
    });

    // hot config reload
    Timer::tick(1000, function () use ($app, &$lastMtime, $configFile) {
        clearstatcache(true, $configFile);
        $mtime = filemtime($configFile);

        if ($mtime === $lastMtime) {
            return;
        }

        echo "Reloading config..." . PHP_EOL;
        $lastMtime = $mtime;

        try {
            $app->updateConfig(true);
        } catch (\Throwable $e) {
            echo 'Reloading config failed...' . PHP_EOL . $e->getMessage();
        }

        echo "Reload config ended..." . PHP_EOL;
    });

    // health check
    Timer::tick(10000, function () use ($app) {
        echo "Health check started..." . PHP_EOL;

        $app->getHealthChecker()->check();

        echo "Health check ended..." . PHP_EOL;
    });
});

$swooleServer->on("WorkerStart", function (Server $server, int $workerId) {
    echo "Worker {$workerId} started" . PHP_EOL;

    Process::signal(SIGINT, function () {
        echo "Worker received SIGINT, exiting..." . PHP_EOL;
        exit(0);
    });
});

$swooleServer->on("request", function (Request $request, Response $response) use ($app) {
    $app->handle($request, $response);
});

$swooleServer->start();
