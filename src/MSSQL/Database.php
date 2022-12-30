<?php
namespace Syra\MSSQL;

use Syra\AbstractDatabase;

class Database extends AbstractDatabase {
    public function __get($property) {
        switch($property) {
        case 'dsn':
        case 'user':
        case 'link':
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

        try {
            $link = new \PDO($this->dsn, $this->user, $this->password);
        }
        catch(\Exception $e) {
            error_log('MSSQL connect failed: '.$e->getMessage());
            throw new \Exception('Could not connect to database server');
        }

        $this->link = $link;

        if(!$link->beginTransaction()) {
            throw new \Exception('Could not begin transaction on database connection');
        }
    }
}
