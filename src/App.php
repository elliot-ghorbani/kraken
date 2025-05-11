<?php

namespace KrakenTide;

use Swoole\Coroutine\Http\Client;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Table;

class App
{
    private Table $serversTable;
    private Table $globalTable;
    private Config $config;
    private LoadBalancer $loadBalancer;
    private Logger $logger;
    private HealthChecker $healthChecker;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
            $instance->bootstrap();
        }

        return $instance;
    }

    private function bootstrap(): void
    {
        $this->initServersTable();

        $this->initGlobalTable();
        
        $this->initConfig();
        
        $this->initLoadBalancer();
        
        $this->initLogger();

        $this->initHealthChecker();
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getHealthChecker(): HealthChecker
    {
        return $this->healthChecker;
    }

    private function initServersTable(): void
    {
        $serversTable = new Table(1024);
        $serversTable->column('host', Table::TYPE_STRING, 64);
        $serversTable->column('health_check_path', Table::TYPE_STRING, 64);
        $serversTable->column('port', Table::TYPE_INT, 2);
        $serversTable->column('ssl', Table::TYPE_INT, 1);
        $serversTable->column('weight', Table::TYPE_INT, 2);
        $serversTable->column('is_healthy', Table::TYPE_INT, 1);
        $serversTable->column('connections', Table::TYPE_INT, 4);
        $serversTable->column('response_times', Table::TYPE_INT, 4);
        $serversTable->create();

        $this->serversTable = $serversTable;
    }

    private function initGlobalTable(): void
    {
        $globalTable = new Table(1024);
        $globalTable->column('last_index', Table::TYPE_INT, 4);
        $globalTable->create();

        $this->globalTable = $globalTable;
    }
    
    private function initConfig(): void
    {
        $this->config = new Config();

        try {
            $this->updateConfig(false);
        } catch (\Throwable $e) {
            echo 'Loading config failed...' . PHP_EOL . $e->getMessage();

            die();
        }
    }

    private function initLoadBalancer(): void
    {
        $this->loadBalancer = new LoadBalancer($this->serversTable, $this->globalTable, $this->config->strategy);
    }

    private function initLogger(): void
    {
        $this->logger = new Logger(
            $this->config->accessLogPath,
            $this->config->errorLogPath,
            $this->config->accessLogFormat,
            $this->config->errorLogFormat
        );
    }

    public function updateConfig(bool $reload): void
    {
        $this->config->loadConfig();

        /** @var \KrakenTide\Server $server */
        foreach ($this->config->servers as $key => $server) {
            $this->serversTable->set(
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

        if ($reload) {
            $this->config->updateConfig($this->loadBalancer, $this->logger);
        }
    }

    private function initHealthChecker(): void
    {
        $this->healthChecker = new HealthChecker($this->serversTable);
    }

    public function handle(Request $request, Response $response)
    {
        $clientIp = $request->server['remote_addr'] ?? '127.0.0.1';
        $stickyCookie = $request->cookie['LBSESSION'] ?? null;

        [$target, $index] =$this->loadBalancer->getServer($stickyCookie ?? $clientIp);

       $this->serversTable->incr($index, 'connections');

        $client = new Client($target['host'], $target['port'], $target['ssl']);
        $client->setMethod($request->server['request_method']);
        $client->setHeaders($request->header ?? []);
        $client->setData($request->rawContent());
        $client->execute($request->server['request_uri']);

       $this->serversTable->decr($index, 'connections');

        if ($client->errCode) {
            $response->status(502);
            $response->end("Bad Gateway");

           $this->logger->error("Failed to proxy to {$target['host']}:{$target['port']}: {$client->errMsg}");

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

       $this->logger->access($request->server);
    }
}
