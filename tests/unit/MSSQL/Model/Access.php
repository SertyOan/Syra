<?php

namespace Tests\MSSQL\Model;

class Access extends \Syra\MSSQL\ModelObject {
    const DATABASE_SCHEMA = 'SyraTest';
    const DATABASE_TABLE = 'Access';

    protected static $properties = Array(
        'id' => Array('class' => 'Integer'),
        'group' => Array('class' => '\Test\Group'),
        'user' => Array('class' => '\Test\User')
    );

    protected $id;
    protected $group;
    protected $user;
}
