<?php

namespace KrakenTide\Tables;

use Swoole\Table;

class ReportsTable
{
    public const string PATH = 'path';
    public const string FORMAT = 'format';

    public const string ACCESS = 'access';
    public const string ERROR = 'error';

    public static function create(): Table
    {
        $reportsTable = new Table(1024);
        $reportsTable->column(self::PATH, Table::TYPE_STRING, 128);
        $reportsTable->column(self::FORMAT, Table::TYPE_STRING, 128);
        $reportsTable->create();

        return $reportsTable;
    }
}
