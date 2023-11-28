<?php
namespace Syra;

interface RequestInterface {
    public static function get($table);
    public function addTable($table, $customSQL = null);
}
