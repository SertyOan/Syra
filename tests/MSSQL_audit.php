<?php
namespace Test;
define('STARTED_AT', microtime(true));

chdir(dirname(__FILE__));
include('../src/AbstractRequest.php');
include('../src/AbstractDatabase.php');
include('../src/DatabaseInterface.php');
include('../src/MSSQL/ModelObject.php');
include('../src/MSSQL/Database.php');
include('../src/MSSQL/Request.php');

class Request extends \Syra\MSSQL\Request {
    const DATABASE_CLASS = '\\Test\\Database';

    protected function buildClassFromTable($table) {
        return '\\Test\\'.$table;
    }
}

class Database implements \Syra\DatabaseInterface {
    private static $writer;
    private static $reader;

    public static function getWriter() {
		if(is_null(self::$writer)) {
			self::$writer = new \Syra\MSSQL\Database('localhost', 'root', '');
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

class Group extends \Syra\MSSQL\ModelObject {
    const DATABASE_SCHEMA = 'SyraTest';
    const DATABASE_TABLE = 'Group';
    protected static $properties = [
        'id' => Array('class' => 'Integer'),
        'name' => Array('class' => 'String')
    ];

    protected $id;
    protected $name;
}

class User extends \Syra\MSSQL\ModelObject {
    const DATABASE_SCHEMA = 'SyraTest';
    const DATABASE_TABLE = 'User';
    protected static $properties = [
        'id' => Array('class' => 'Integer'),
        'name' => Array('class' => 'String')
    ];
    protected $id;
    protected $name;
}

class Audit extends \Syra\MSSQL\ModelObject {
    const DATABASE_SCHEMA = 'SyraTest';
    const DATABASE_TABLE = 'Audit';
    protected static $properties = [
        'id' => Array('class' => 'Integer'),
        'model' => Array('class' => 'String'),
        'modelID' => Array('class' => 'Integer')
    ];
    protected $id;
    protected $model;
    protected $modelID;
}

$request = Request::get('Audit')->withFields('id')
    ->leftJoin('Group', 'Groups')->on('Group', 'id', 'modelID')->with(table: 'Audit', field: 'model', operator: '=', value: 'Group')->withFields('id', 'name')
    ->leftJoin('User', 'Users')->on('User', 'id', 'modelID')->with(table: 'Audit', field: 'model', operator: '=', value: 'User')->withFields(...['id', 'name']);

print_r($request->generateDataSQL());

$duration = microtime(true) - STARTED_AT;
print("\n".'Duration: '.$duration);
print("\n".'Memory: '.memory_get_peak_usage());
print("\n");
