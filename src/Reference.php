<?php
namespace Syra;

class Reference {
    public readonly string $table;
    public readonly string $field;

    public static function create(string $table, string $field) {
        $reference = new self();
        $reference->table = $table;
        $reference->field = $field;
        return $reference;
    }
}
