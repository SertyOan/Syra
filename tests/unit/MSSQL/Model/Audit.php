<?php

namespace Tests\MSSQL\Model;

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
