<?php

namespace Tests\MySQL\Model;

class Audit extends \Syra\MySQL\ModelObject {
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
