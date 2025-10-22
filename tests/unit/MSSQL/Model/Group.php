<?php

namespace Tests\MSSQL\Model;

class Group extends \Syra\MSSQL\ModelObject {
    const DATABASE_SCHEMA = 'SyraTest';
    const DATABASE_TABLE = 'Group';

    protected static $properties = Array(
        'id' => Array('class' => 'Integer'),
        'name' => Array('class' => 'String')
    );

    protected $id;
    protected $name;
}
