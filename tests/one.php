<?php
namespace Test;
define('STARTED_AT', microtime(true));

chdir(dirname(__FILE__));
include('../src/MySQL/Database.php');
include('../src/MySQL/Object.php');
include('../src/MySQL/Request.php');
include('../src/MySQL/Parser.php');
include('../src/MySQL/Mapper.php');

class Request extends \Syra\MySQL\Request {
    const
        OBJECTS_NAMESPACE = '\\Test';
}

class Database extends \Syra\MySQL\Database {
    private static
        $instance;

    public static function get() {
		if(is_null(self::$instance)) {
			self::$instance= new self('localhost', 'root', '');
			self::$instance->connect();
        }

        return self::$instance;
    }
}

class Mapper extends \Syra\MySQL\Mapper {
    const
        DATABASE_CLASS = '\Test\Database';
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

$request = Request::get('User')->withFields('id', 'name');
$tasks = Mapper::mapAsObjects($request);

print_r($tasks);



$duration = microtime(true) - STARTED_AT;
print("\n".'Duration: '.$duration);
print("\n".'Memory: '.memory_get_peak_usage());
print("\n");
