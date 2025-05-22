<?php

namespace KrakenTide;

use KrakenTide\Dependencies\Config;
use KrakenTide\Dependencies\HealthChecker;
use KrakenTide\Dependencies\LoadBalancer;
use KrakenTide\Dependencies\Logger;
use KrakenTide\Dependencies\RateLimiter;
use KrakenTide\Tables\GlobalTable;
use KrakenTide\Tables\RateLimiterTable;
use KrakenTide\Tables\ReportsTable;
use KrakenTide\Tables\ServersTable;
use Swoole\Coroutine\Http\Client;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\Constant;
use Swoole\Process;
use Swoole\Timer;

class App
{
    private Server $swooleServer;
    private Table $serversTable;
    private Table $globalTable;
    private Config $config;
    private LoadBalancer $loadBalancer;
    private Logger $logger;
    private HealthChecker $healthChecker;
    private Table $reportsTable;
    private Table $rateLimiterTable;
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->initConfig();

        $this->swooleServer = new Server("0.0.0.0", 8080, SWOOLE_PROCESS);
        $this->loadBalancer = new LoadBalancer($this);
        $this->logger = new Logger($this);
        $this->healthChecker = new HealthChecker($this);
        $this->rateLimiter = new RateLimiter($this);
    }

    private function initConfig(): void
    {
        $this->serversTable = ServersTable::create();
        $this->globalTable = GlobalTable::create();
        $this->reportsTable = ReportsTable::create();
        $this->rateLimiterTable = RateLimiterTable::create();

        $this->config = new Config($this);

        try {
            $this->updateConfig(false);
        } catch (\Throwable $e) {
            echo 'Loading config failed...' . PHP_EOL . $e->getMessage();

            die();
        }
    }

    public function updateConfig(bool $reload): void
    {
        if ($reload) {
            $globalConfigs = $this->globalTable->get(GlobalTable::GLOBAL_KEY);
            if (Config::getConfigFileTime() === $globalConfigs[GlobalTable::CONFIG_FILE_TIME]) {
                return;
            }

            echo "Config: Reloading..." . PHP_EOL;
        } else {
            echo "Config: Loading..." . PHP_EOL;
        }

        $this->config->loadConfig();

        echo "Config: Loaded!" . PHP_EOL;
    }

    public function getRateLimiterTable(): Table
    {
        return $this->rateLimiterTable;
    }

    public function getGlobalTable(): Table
    {
        return $this->globalTable;
    }

    public function getServersTable(): Table
    {
        return $this->serversTable;
    }

    public function getReportsTable(): Table
    {
        return $this->reportsTable;
    }

    public function start(): void
    {
        $this->swooleServer->set($this->config->getAppConfigs());

        $this->swooleServer->on(Constant::EVENT_START, function (Server $server) {
            echo "Kraken running at http://0.0.0.0:8080" . PHP_EOL;

            Process::signal(SIGINT, function () use ($server) {
                echo "Shutting down gracefully..." . PHP_EOL;
                $server->shutdown();
            });

            Timer::tick(1000, function () {
                try {
                    $this->updateConfig(true);
                } catch (\Throwable $e) {
                    echo 'Reloading config failed...' . PHP_EOL . $e->getMessage();
                }
            });

            Timer::tick(1000, function () {
                $this->rateLimiter->cleanUp();
            });

            Timer::tick(10000, function () {
                $this->healthChecker->handle();
            });
        });

        $this->swooleServer->on(Constant::EVENT_WORKER_START, function (Server $server, int $workerId) {
            echo "Worker {$workerId} started" . PHP_EOL;

            Process::signal(SIGINT, function () {
                echo "Worker received SIGINT, exiting..." . PHP_EOL;
                exit(0);
            });
        });

        $this->swooleServer->on(Constant::EVENT_REQUEST, function (Request $request, Response $response) {
            $this->handle($request, $response);
        });

        $this->swooleServer->start();
    }

    public function handle(Request $request, Response $response): void
    {
        if (!$this->rateLimiter->handle($request)) {
            $response->status(429);
            $response->end("Too Many Requests");

            return;
        }

        [$target, $index] = $this->loadBalancer->getServer($request);

        $this->serversTable->incr($index, ServersTable::CONNECTIONS); // Increase connections
        $start = microtime(true); // Start timing

        $client = new Client($target[ServersTable::HOST], $target[ServersTable::PORT], !!$target[ServersTable::SSL]);
        $client->setMethod($request->server['request_method']);
        $client->setHeaders($request->header ?? []);
        $client->setData($request->rawContent());
        $client->execute($request->server['request_uri']);

        $this->serversTable->decr($index, ServersTable::CONNECTIONS); // Decrease connections
        $duration = microtime(true) - $start; // End timing
        $durationMs = (int)round($duration * 1000); // convert to ms

        $prev = $this->serversTable->get($index);
        if ($prev !== false) {
            $newTime = (int)round(($prev[ServersTable::RESPONSE_TIME] + $durationMs) / 2);
            $this->serversTable->set($index, [ServersTable::RESPONSE_TIME => $newTime]);
        }

        if ($client->errCode) {
            $response->status(502);
            $response->end("Bad Gateway");

            $this->logger->error(
                "Failed to proxy to {$target[ServersTable::HOST]}:{$target[ServersTable::PORT]}: {$client->errMsg}"
            );

            $client->close();

            return;
        }

        $response->status($client->statusCode);

        $skipHeaders = ['transfer-encoding'];

        foreach ($client->headers ?? [] as $key => $val) {
            if (!in_array(strtolower($key), $skipHeaders)) {
                $response->header($key, $val);
            }
        }

        $response->cookie(LoadBalancer::SESSION_COOKIE, (string)$index, time() + 600);

        if ($response->isWritable()) {
            $response->end($client->body);
        }

        $client->close();

        $this->logger->access($request->server);
    }
}
