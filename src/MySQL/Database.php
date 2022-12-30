<?php
namespace Syra\MySQL;

use Syra\AbstractDatabase;

class Database extends AbstractDatabase {
    private string $charset;

    public function __construct($dsn, $user, $password, $charset = 'utf8mb4') {
        parent::__construct($dsn, $user, $password);
        $this->charset = $charset;
    }

    public function __get($property) {
        switch($property) {
        case 'dsn':
        case 'user':
        case 'link':
        case 'charset':
        case 'queries':
            return $this->{$property};
        case 'password':
            throw new \Exception('Property is private');
        default:
            throw new \Exception('Property does not exist');
        }
    }

    public function connect() {
        if($this->isConnected()) {
            throw new \Exception('Database connection already established');
        }

        $options = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$charset];

        try {
            $link = new \PDO($this->dsn, $this->user, $this->password, $options);
        }
        catch(\Exception $e) {
            error_log('MySQL connect failed: '.$e->getMessage());
            throw new \Exception('Could not connect to database server');
        }

        $this->link = $link;

        if(!$link->beginTransaction()) {
            throw new \Exception('Could not begin transaction on database connection');
        }
    }
}
