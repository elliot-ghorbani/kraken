<?php

namespace KrakenTide\Tables;

use Swoole\Table;

class ServersTable
{
    public const string HOST = 'host';
    public const string HEALTH_CHECK_PATH = 'health_check_path';
    public const string PORT = 'port';
    public const string SSL = 'ssl';
    public const string WEIGHT = 'weight';
    public const string IS_HEALTHY = 'is_healthy';
    public const string CONNECTIONS = 'connections';
    public const string RESPONSE_TIME = 'response_time';

    public static function create(): Table
    {
        $serversTable = new Table(1024);
        $serversTable->column(self::HOST, Table::TYPE_STRING, 64);
        $serversTable->column(self::HEALTH_CHECK_PATH, Table::TYPE_STRING, 64);
        $serversTable->column(self::PORT, Table::TYPE_INT, 2);
        $serversTable->column(self::SSL, Table::TYPE_INT, 1);
        $serversTable->column(self::WEIGHT, Table::TYPE_INT, 2);
        $serversTable->column(self::IS_HEALTHY, Table::TYPE_INT, 1);
        $serversTable->column(self::CONNECTIONS, Table::TYPE_INT, 4);
        $serversTable->column(self::RESPONSE_TIME, Table::TYPE_INT, 4);
        $serversTable->create();

        return $serversTable;
    }
}
