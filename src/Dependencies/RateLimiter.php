<?php

namespace KrakenTide\Dependencies;

use KrakenTide\Tables\GlobalTable;
use KrakenTide\Tables\RateLimiterTable;
use Swoole\Http\Request;

class RateLimiter extends AbstrctDependency
{
    public function handle(Request $request): bool
    {
        $configs = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY);

        $now = time();
        $windowSize = $configs[GlobalTable::RATE_LIMITER_DURATION];
        $maxRequests = $configs[GlobalTable::RATE_LIMITER_COUNT];

        $rateLimiterTable = $this->app->getRateLimiterTable();

        $clientIp = $request->server['remote_addr'] ?? '127.0.0.1';
        $data = $rateLimiterTable->get($clientIp);

        if ($data) {
            $elapsed = $now - $data[RateLimiterTable::WINDOW_START];

            if ($elapsed < $windowSize) {
                if ($data[RateLimiterTable::COUNT] >= $maxRequests) {
                    return false;
                } else {
                    $rateLimiterTable->incr($clientIp, RateLimiterTable::COUNT);
                }
            } else {
                $rateLimiterTable->set($clientIp, [RateLimiterTable::COUNT => 1, RateLimiterTable::WINDOW_START => $now]);
            }
        } else {
            $rateLimiterTable->set($clientIp, [RateLimiterTable::COUNT => 1, RateLimiterTable::WINDOW_START => $now]);
        }

        return true;
    }

    public function cleanUp()
    {
        $configs = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY);

        $now = time();
        $windowSize = $configs[GlobalTable::RATE_LIMITER_DURATION];

        foreach ($this->app->getRateLimiterTable() as $ip => $item) {
            $elapsed = $now - $item[RateLimiterTable::WINDOW_START];

            if ($elapsed > $windowSize) {
                $this->app->getRateLimiterTable()->del($ip);
            }
        }
    }
}
