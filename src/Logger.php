<?php

namespace KrakenTide;

use DateTime;
use Swoole\Table;

class Logger
{
    private Table $loggerTable;

    public function __construct(Table $loggerTable)
    {
        $this->loggerTable = $loggerTable;
    }

    public function access(array $context): void
    {
        $accessConfig = $this->loggerTable->get('access');

        $log = $this->interpolate($accessConfig['format'], $context);

        file_put_contents($accessConfig['path'], $log . PHP_EOL, FILE_APPEND);
    }

    public function error(string $message, array $context = []): void
    {
        $context['message'] = $message;
        $errorConfig = $this->loggerTable->get('error');

        $log = $this->interpolate($errorConfig['format'], $context);

        file_put_contents($errorConfig['path'], $log . PHP_EOL, FILE_APPEND);
    }

    protected function interpolate(string $template, array $context): string
    {
        $replacements = [
            '$remote_addr' => $context['remote_addr'] ?? '-',
            'remote_port' => $context['remote_port'] ?? '-',
            '$host' => $context['host'] ?? '-',
            '$path_info' => $context['path_info'] ?? '-',
            '$request_uri' => $context['request_uri'] ?? '-',
            '$request_method' => $context['request_method'] ?? '-',
            '$uri' => $context['uri'] ?? '-',
            '$status' => $context['status'] ?? '-',
            '$upstream_addr' => $context['upstream_addr'] ?? '-',
            '$upstream_response_time' => $context['upstream_response_time'] ?? '-',
            '$request_time' => $context['request_time'] ?? '-',
            '$request_time_float' => $context['request_time_float'] ?? '-',
            '$server_protocol' => $context['server_protocol'] ?? '-',
            '$server_port' => $context['server_port'] ?? '-',
            '$http_user_agent' => $context['http_user_agent'] ?? '-',
            '$time_local' => new DateTime()->format('Y-m-d:H:i:s O'),
            '$message' => $context['message'] ?? '-',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
