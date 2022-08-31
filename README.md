Syra
====

**Syra** is a PHP Object-Relational Mapping library.

# Installation

You can install it via composer: osyra/syra

# Defining needed classes

You need to create two classes.

```php
namespace App;
class CustomDatabase implements \Syra\DatabaseInterface {
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
```

```php
namespace App;
class CustomRequest extends \Syra\MySQL\DataRequest {
    const
        DATABASE_CLASS = '\\App\\CustomDatabase';

    protected function buildClassFromTable($table) {
        return '\\App\\Model\\'.$table; // this must return the name of the class matched by the table
    }
}
```

Then for each table of your database you will add a class.
The table primary key must be `id`, it can be an integer or a string.

```php
namespace App\Model;
class Foobar extends \Syra\MySQL\ModelObject {
    const
        DATABASE_CLASS = '\\App\\CustomDatabase',
        DATABASE_SCHEMA = 'Schema',
        DATABASE_TABLE = 'Foobar';

    protected static
        $properties = [
            'id' => ['class' => 'Integer'],
            'name' => ['class' => 'String'],
            'parent' => ['class' => '\\App\\Model\\Bar']
        ];

    protected
        $id,
        $name,
        $parent;
}

class Bar extends \Syra\MySQL\ModelObject {
    const
        DATABASE_CLASS = '\\App\\CustomDatabase',
        DATABASE_SCHEMA = 'Schema',
        DATABASE_TABLE = 'Bar';

    protected static
        $properties = [
            'id' => ['class' => 'Integer'],
            'name' => ['class' => 'String'],
            'createdAt' => ['class' => 'DateTime']
        ];

    protected
        $id,
        $name,
        $createdAt;
}
```

# Requesting data

## Requesting objects
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->mapAsObjects();
    
$foobar = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->where('', 'Foobar', 'id', '=', 1)
    ->mapAsObject();
```

## Requesting objects with conditions
Condition is the first argument of any where() function.
It can start with a closing parenthesis followed by AND or OR and can end with an opening parenthesis.
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->where('', 'Foobar', 'parent', '=', $parentID)
    ->where('AND (', 'Foobar', 'name', 'LIKE', '%Hello%')
    ->where('OR', 'Foobar', 'name', 'LIKE', '%World')
    ->mapAsObjects();
```

## Ordering
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->orderAscBy('Foobar', 'name')
    ->orderDescBy('Foobar', 'id')
    ->mapAsObjects();
```

## Limit and offset
This will get 10 rows from database starting at 10th row.
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->lines(10)
    ->offset(10)
    ->mapAsObjects();
```
Note: be aware this is a limitation of the number of rows, not the number of objects.

## Linking tables to get sub-object
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->leftJoin('Bar')->on('Foobar', 'parent')->withFields('id', 'name', 'createdAt')
    ->mapAsObjects();
```

## Linking tables to get a collection
```php
$bars = \App\CustomRequest::get('Bar')->withFields('id', 'name', 'createdAt')
    ->leftJoin('Foobar', 'Foobars')->on('Bar', 'id', 'parent')->withFields('id', 'name')
    ->mapAsObjects();
    
foreach($bars as $bar) {
    foreach($bar->myFoobars as $foobar) {
        ...
    }
}
```

## Condition on linked table
```php
$bars = \App\CustomRequest::get('Bar')->withFields('id', 'name', 'createdAt')
    ->leftJoin('Foobar', 'Foobars')->on('Bar', 'id', 'parent')->withFields('id', 'name')->with('', 'name', 'LIKE', '%Hello%')
    ->mapAsObjects();
```

## Add additional condition between fields of two tables of same request
```php
$reference = new \stdClass;
$reference->table = 'Bar';
$reference->field = 'language';

$foobars = \App\CustomRequest::get('Foo')->withFields('id', 'language', 'name')
    ->leftJoin('Bar', 'Bars')->on('Foo', 'id', 'foo')->with('', 'language', '=', $reference)->withFields('id', 'foo', 'language')
    ->mapAsObject();
```

## Using same table multiple times
```php
$bars = \App\CustomRequest::get('Bar')->withFields('id', 'name')
    ->leftJoin('Bar')->on('Bar', 'parent')->withFields('id', 'name') # equivalent to ->on('Bar::1', 'parent')
    ->leftJoin('Bar')->on('Bar::2', 'parent')->withFields('id', 'name')
    ->mapAsObjects();
```

## Requesting as associative arrays (for latter JSON encoding)
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->mapAsArrays();
```

## Transforming objects to arrays
```php
$foobars = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->mapAsObjects();
$arrays = \App\CustomRequest::objectsAsArrays($foobars);

$foobar = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->where('', 'Foobar', 'id', '=', 1)
    ->mapAsObject();
$array = $foobar->asArray();
```

# Modifying objects

## Saving
```php
$foobar = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->where('', 'Foobar', 'id', '=', 1)
    ->mapAsObject();
$foobar->name = 'Something';
$foobar->save();
\App\Database::getWriter()->commit();
```

## Deleting
```php
$foobar = \App\CustomRequest::get('Foobar')->withFields('id', 'name')
    ->where('', 'Foobar', 'id', '=', 1)
    ->mapAsObject();
$foobar->delete();
\App\Database::getWriter()->commit();
```
