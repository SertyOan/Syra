<?php

namespace Tests\MSSQL;

class Request extends \Syra\MSSQL\Request {
    const DATABASE_CLASS = '\\Tests\\MSSQL\\Database';

    protected function buildClassFromTable($table) {
        return '\\Tests\\MSSQL\\Model\\'.$table;
    }
}
