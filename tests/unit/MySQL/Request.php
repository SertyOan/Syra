<?php

namespace Tests\MySQL;

class Request extends \Syra\MySQL\Request {
    const DATABASE_CLASS = '\\Tests\\MySQL\\Database';

    protected function buildClassFromTable($table) {
        return '\\Tests\\MySQL\\Model\\'.$table;
    }
}
