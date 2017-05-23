<?php
namespace Test;
define('STARTED_AT', microtime(true));

chdir(dirname(__FILE__));
include('../src/DatabaseInterface.php');
include('../src/MySQL/Database.php');
include('../src/MySQL/Object.php');
include('../src/MySQL/Request.php');

class Request extends \Syra\MySQL\Request {
    const
        DATABASE_CLASS = '\\Test\\Database';

    protected function buildClassFromTable($table) {
        return '\\Test\\'.$table;
    }
}

class Database implements \Syra\DatabaseInterface {
    private static
        $writer,
        $reader;

    public static function getWriter() {
		if(is_null(self::$writer)) {
			self::$writer = new \Syra\MySQL\Database('localhost', 'root', '');
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

class Group extends \Syra\MySQL\Object {
    const
        DATABASE_SCHEMA = 'SyraTest',
        DATABASE_TABLE = 'Group';

    protected static
        $properties = Array(
            'id' => Array('class' => 'Integer'),
            'name' => Array('class' => 'String')
        );

    protected
        $id,
        $name;
}

class User extends \Syra\MySQL\Object {
    const
        DATABASE_SCHEMA = 'SyraTest',
        DATABASE_TABLE = 'User';

    protected static
        $properties = Array(
            'id' => Array('class' => 'Integer'),
            'name' => Array('class' => 'String')
        );

    protected
        $id,
        $name;
}

class Access extends \Syra\MySQL\Object {
    const
        DATABASE_SCHEMA = 'SyraTest',
        DATABASE_TABLE = 'Access';

    protected static
        $properties = Array(
            'id' => Array('class' => 'Integer'),
            'group' => Array('class' => '\Test\Group'),
            'user' => Array('class' => '\Test\User')
        );

    protected
        $id,
        $group,
        $user;
}

$request = Request::get('User')->withFields('id', 'name')
    ->leftJoin('Access', 'Accesses')->on('User', 'id', 'user')->withFields('id')
    ->leftJoin('Group')->on('Access', 'group')->withFields('id', 'name')
    ->where('', 'User', 'id', '=', 1)
    ->where('OR', 'User', 'id', '=', 2);
$tasks = $request->mapAsObjects();

print_r($tasks);

$duration = microtime(true) - STARTED_AT;
print("\n".'Duration: '.$duration);
print("\n".'Memory: '.memory_get_peak_usage());
print("\n");
