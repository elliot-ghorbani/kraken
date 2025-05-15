<?php

namespace KrakenTide\Tables;

use Swoole\Table;

class GlobalTable
{
    public const string GLOBAL_KEY = 'global';
    public const string LAST_INDEX = 'last_index';
    public const string CONFIG_FILE_TIME = 'config_file_time';
    public const string STRATEGY = 'strategy';
    public const string RATE_LIMITER_COUNT = 'rate_limiter_count';
    public const string RATE_LIMITER_DURATION = 'rate_limiter_duration';

    public static function create(): Table
    {
        $globalTable = new Table(1024);
        $globalTable->column(self::LAST_INDEX, Table::TYPE_INT, 4);
        $globalTable->column(self::CONFIG_FILE_TIME, Table::TYPE_INT, 4);
        $globalTable->column(self::STRATEGY, Table::TYPE_STRING, 32);
        $globalTable->column(self::RATE_LIMITER_COUNT, Table::TYPE_INT, 4);
        $globalTable->column(self::RATE_LIMITER_DURATION, Table::TYPE_INT, 4);
        $globalTable->create();

        return $globalTable;
    }
}
