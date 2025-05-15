<?php

namespace KrakenTide;

class RateLimiter
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(string $clientIp): bool
    {
        $configs = $this->app->getGlobalTable()->get(0);

        $now = time();
        $windowSize = $configs['rate_limiter_duration'];
        $maxRequests = $configs['rate_limiter_count'];

        $rateLimiterTable = $this->app->getRateLimiterTable();

        $data = $rateLimiterTable->get($clientIp);

        if ($data) {
            $elapsed = $now - $data['window_start'];

            if ($elapsed < $windowSize) {
                if ($data['count'] >= $maxRequests) {
                    return false;
                } else {
                    $rateLimiterTable->incr($clientIp, 'count');
                }
            } else {
                $rateLimiterTable->set($clientIp, ['count' => 1, 'window_start' => $now]);
            }
        } else {
            $rateLimiterTable->set($clientIp, ['count' => 1, 'window_start' => $now]);
        }

        return true;
    }
}
