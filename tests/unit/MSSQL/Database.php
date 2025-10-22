<?php

namespace Tests\MSSQL;

class Database implements \Syra\DatabaseInterface {
    private static $writer;
    private static $reader;

    public static function getWriter() {
		if(is_null(self::$writer)) {
			self::$writer = new \Syra\MySQL\Database('sqlsrv:Server=127.0.0.1\INSTANCE;Database=SyraTest', 'root', '');
			self::$writer->connect();
        }

        return self::$writer;
    }

    public static function getReader() {
        if(is_null(self::$reader)) {
            self::$reader = self::getWriter();
        }

        return self::$reader;
    }
}
