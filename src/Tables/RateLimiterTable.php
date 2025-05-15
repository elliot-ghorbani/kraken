<?php

namespace KrakenTide\Tables;

use Swoole\Table;

class RateLimiterTable
{
    public const string COUNT = 'count';
    public const string WINDOW_START = 'window_start';

    public static function create(): Table
    {
        $rateLimiterTable = new Table(10240);
        $rateLimiterTable->column(self::COUNT, Table::TYPE_INT);
        $rateLimiterTable->column(self::WINDOW_START, Table::TYPE_INT);
        $rateLimiterTable->create();

        return $rateLimiterTable;
    }
}
