<?php
namespace Syra\MySQL;

class Database {
    private
        $link,
        $hostname,
        $user,
        $password,
        $queries = 0;

    public function __construct($hostname, $user, $password) {
        $this->hostname = $hostname;
        $this->user = $user;
        $this->password = $password;
    }

    public function __get($property) {
        switch($property) {
            case 'user':
            case 'hostname':
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

        $dsn = 'mysql:host='.$this->hostname;
        $options = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'];

        try {
            $link = new \PDO($dsn, $this->user, $this->password, $options);
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

    public function disconnect() {
        $this->checkConnection();
        $this->link = null;
    }

    public function isConnected() {
        return $this->link instanceof \PDO;
    }

    public function query($sql, $params) {
        $this->checkConnection();
        $this->queries++;

        $statement = $this->link->prepare($sql);

        if($statement === false) {
            error_log('Could not prepare database query');
            error_log($sql);
            error_log(implode(' / ', $this->link->errorInfo()));
            throw new \Exception('Could not prepare database query');
        }

        if($statement->execute($params) === false) {
            error_log('Could not execute database query');
            error_log($sql);
            error_log(implode(' / ', $this->link->errorInfo()));
            throw new \Exception('Could not execute database query');
        }

        return $statement;
    }

    public function queryRows($sql, $params) {
        $statement = $this->query($sql, $params);

        while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function commit() {
        $this->checkConnection();

        if(!$this->link->commit()) {
            throw new \Exception('Could not commit database transaction');
        }
    }

    public function rollback() {
        $this->checkConnection();

        if(!$this->link->rollback()) {
            throw new \Exception('Could not rollback database transaction');
        }
    }

    private function checkConnection() {
        if(!$this->isConnected()) {
            throw new \Exception('Database connection not established');
        }
    }
}
