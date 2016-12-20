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

        $link = new \mysqli($this->hostname, $this->user, $this->password);

        if($link->connect_error) {
            error_log('MySQL connect failed: '.$link->connect_error);
            throw new \Exception('Could not connect to database server');
        }

        $this->link = $link;

        if(!$link->autocommit(false)) {
            throw new \Exception('Could not disable autocommit on database connection');
        }

        if(!$link->set_charset('utf8')) {
            throw new \Exception('Could not set charset on database connection');
        }
    }

    public function disconnect() {
        $this->checkConnection();

		if(!$this->link->close()) {
            throw new \Exception('Could not disconnect from database server');
        }

        $this->link = null;
    }

    public function isConnected() {
        return $this->link instanceof \mysqli;
    }

    public function query($statement) {
        $this->checkConnection();
        $this->queries++;

		if(($result = $this->link->query($statement)) === false) {
            error_log($statement);
            error_log($this->link->error);
			throw new \Exception('Could not execute database query');
        }

        return $result;
    }

    public function queryRows($statement) {
        $result = $this->query($statement);
        $data = Array();

		while($data[] = $result->fetch_assoc()) {
            continue;
        }

		$result->free();
        array_pop($data);
        return $data;
    }

	public function escapeString($string) {
        $this->checkConnection();

		return $this->link->real_escape_string($string);
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
