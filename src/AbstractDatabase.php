<?php
namespace Syra;

abstract class AbstractDatabase {
    protected ?\PDO $link = null;
    protected string $dsn;
    protected string $user;
    protected string $password;
    protected Array $options = []; // PDO options
    protected int $queries = 0;

    public function __construct($dsn, $user, $password, Array $options = []) {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
    }

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
            $link = new \PDO($this->dsn, $this->user, $this->password, $this->options);
        }
        catch(\Exception $e) {
            error_log(__METHOD__ . ' | Database connection failed | '.$e->getMessage());
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

    public function query($sql, $params = []) {
        $this->checkConnection();
        $this->queries++;

        $statement = $this->link->prepare($sql);

        if($statement === false) {
            error_log('Could not prepare database query');
            error_log($sql);
            error_log(implode(' / ', $this->link->errorInfo()));
            throw new \Exception('Could not prepare database query');
        }

        $i = 1;

        foreach($params as $param) {
            $driverOptions = $param['driverOptions'] ?? null;
            $statement->bindParam($i, $param['value'], $param['type'], 0, $driverOptions);
            $i++;
        }

        if($statement->execute() === false) {
            error_log('Could not execute database query');
            error_log($sql);
            error_log(implode(' / ', $statement->errorInfo()));
            throw new \Exception('Could not execute database query');
        }

        return $statement;
    }

    public function queryRows($sql, $params = []) {
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

        if(!$this->link->beginTransaction()) {
            throw new \Exception('Could not begin transaction on database connection');
        }
    }

    public function rollback() {
        $this->checkConnection();

        if(!$this->link->rollback()) {
            throw new \Exception('Could not rollback database transaction');
        }

        if(!$this->link->beginTransaction()) {
            throw new \Exception('Could not begin transaction on database connection');
        }
    }

    protected function checkConnection() {
        if(!$this->isConnected()) {
            throw new \Exception('Database connection not established');
        }
    }
}
