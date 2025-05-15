<?php

namespace KrakenTide\Dependencies;

use KrakenTide\Tables\GlobalTable;
use KrakenTide\Tables\RateLimiterTable;

class RateLimiter extends AbstrctDependency
{
    public function handle(string $clientIp): bool
    {
        $configs = $this->app->getGlobalTable()->get(GlobalTable::GLOBAL_KEY);

        $now = time();
        $windowSize = $configs[GlobalTable::RATE_LIMITER_DURATION];
        $maxRequests = $configs[GlobalTable::RATE_LIMITER_COUNT];

        $rateLimiterTable = $this->app->getRateLimiterTable();

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
}
