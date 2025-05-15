<?php

require __DIR__ . '/../vendor/autoload.php';

use KrakenTide\App;
use Swoole\Constant;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Timer;

$app = App::getInstance();

$swooleServer = new Server("0.0.0.0", 8080);

$swooleServer->set($app->getConfig()->getAppConfigs());

$swooleServer->on(Constant::EVENT_START, function (Server $server) use ($app) {
    echo "Kraken running at http://0.0.0.0:8080" . PHP_EOL;

    Process::signal(SIGINT, function () use ($server) {
        echo "Shutting down gracefully..." . PHP_EOL;
        $server->shutdown();
    });

    Timer::tick(1000, function () use ($app) {
        try {
            $app->updateConfig(true);
        } catch (\Throwable $e) {
            echo 'Reloading config failed...' . PHP_EOL . $e->getMessage();
        }
    });

    Timer::tick(10000, function () use ($app) {
        $app->getHealthChecker()->handle();
    });
});

$swooleServer->on(Constant::EVENT_WORKER_START, function (Server $server, int $workerId) {
    echo "Worker {$workerId} started" . PHP_EOL;

    Process::signal(SIGINT, function () {
        echo "Worker received SIGINT, exiting..." . PHP_EOL;
        exit(0);
    });
});

$swooleServer->on(Constant::EVENT_REQUEST, function (Request $request, Response $response) use ($app) {
    $app->handle($request, $response);
});

$swooleServer->start();
