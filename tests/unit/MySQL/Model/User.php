<?php

namespace Tests\MySQL\Model;

class User extends \Syra\MySQL\ModelObject {
    const DATABASE_SCHEMA = 'SyraTest';
    const DATABASE_TABLE = 'User';

    protected static $properties = Array(
        'id' => Array('class' => 'Integer'),
        'name' => Array('class' => 'String')
    );

    protected $id;
    protected $name;
}
