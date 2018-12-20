Syra
====

**Syra** is a PHP Object-Relational Mapping library.

# Installation

You can install it via composer: osyra/syra

# Usage

You need to create two classes.

```php
class CustomDatabase implements \Syra\MySQL\Database {
    private static
        $writer,
        $reader;

    public static function getWriter() {
        if(is_null(self::$writer)) {
            self::$writer = new \Syra\MySQL\Database('hostname', 'user', 'password');
            self::$writer->connect();
        }

        return self::$writer;
    }

    public static function getReader() {
        if(is_null(self::$reader)) {
            // if you have only one server
            self::$reader = self::getWriter();

            // if you have a slave for read only you could write this :
            //   self::$reader = new \Syra\MySQL\Database('ro-hostname', 'ro-user', 'ro-password');
            //   self::$reader->connect();
        }

        return self::$reader;
    }
}

class CustomRequest extends \Syra\MySQL\DataRequest {
    const
        DATABASE_CLASS = 'CustomDatabase';

    protected function buildClassFromTable($table) {
        return '\\Path\\To\\'.$table; // this must return the name of the class matched by the table
    }
}
```

Then for each table of your database you will add a class.

```php
class Foobar extends \Syra\MySQL\Object {
    const
        DATABASE_CLASS = 'CustomDabase',
        DATABASE_SCHEMA = 'Schema',
        DATABASE_TABLE = 'Foobar';

    protected static
        $properties = [
            'id' => ['class' => 'Integer'],
            'name' => ['class' => 'String'],
            'parent' => ['class' => 'Foobar']
        ];

    protected
        $id,
        $name,
        $parent;
}
```
