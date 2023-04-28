<?php
namespace Syra\Oracle;

use Syra\AbstractDatabase;

class Database extends AbstractDatabase {
    public function connect() {
        parent::connect();

        $this->link->query("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
        $this->link->query("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '. '");
    }
}
