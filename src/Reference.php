<?php
namespace Syra;

class Reference {
    public $table;
    public $field;

    public static function create(string $table, string $field) {
        $reference = new self();
        $reference->table = $table;
        $reference->field = $field;
        return $reference;
    }
}
