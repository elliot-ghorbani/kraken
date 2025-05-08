<?php

namespace LoadBalancer;

use DateTime;

class Logger
{
    protected string $accessLogPath;
    protected string $errorLogPath;
    protected string $accessLogFormat;
    protected string $errorLogFormat;

    public function __construct(
        string $accessLogPath,
        string $errorLogPath,
        string $accessLogFormat,
        string $errorLogFormat,
    ) {
        $this->updateConfig($accessLogPath, $errorLogPath, $accessLogFormat, $errorLogFormat);
    }

    public function updateConfig(
        string $accessLogPath,
        string $errorLogPath,
        string $accessLogFormat,
        string $errorLogFormat,
    ): void {
        $this->accessLogPath = $accessLogPath;
        $this->errorLogPath = $errorLogPath;
        $this->accessLogFormat = $accessLogFormat;
        $this->errorLogFormat = $errorLogFormat;
    }

    public function access(array $context): void
    {
        $log = $this->interpolate($this->accessLogFormat, $context);

        file_put_contents($this->accessLogPath, $log . PHP_EOL, FILE_APPEND);
    }

    public function error(string $message, array $context = []): void
    {
        $context['message'] = $message;
        $log = $this->interpolate($this->errorLogFormat, $context);

        file_put_contents($this->errorLogPath, $log . PHP_EOL, FILE_APPEND);
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
