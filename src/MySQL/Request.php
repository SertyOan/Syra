<?php
namespace Syra\MySQL;

abstract class Request {
    const
        LEFT_JOIN = 1,
        INNER_JOIN = 2,
        OPTION_DAY = 1,
        OPTION_MONTH = 2,
        OPTION_YEAR = 4;

    private
        $tables = Array(),
        $index = Array(),
        $fields = Array(),
        $orderBy = Array(),
        $links = Array(),
        $conditions = Array(),
        $resultsConditions = Array(),
        $distinctLines = false,
        $offset = 0,
        $lines = 0;

    public static function get($table) {
        return new static($table);
    }

    private function __construct($table) {
        // DEBUG if(!defined('static::OBJECTS_NAMESPACE')) {
        // DEBUG     throw new \Exception('Objects namespace constant is not defined');
        // DEBUG }

        $this->addTable($table);
    }

    public function __get($property) {
        return $this->$property;
    }

    private function addTable($table) {
        $class = static::OBJECTS_NAMESPACE.'\\'.$table;

        // DEBUG if(!($class instanceof Object)) {
        // DEBUG     throw new Exception('Class is not a child of Object');
        // DEBUG }

        $this->tables[] = $class;

        $index = sizeof($this->tables) - 1;

        if(!isset($this->index[$table])) {
            $this->index[$table] = $index;
        }

        $i = 1;

        while(isset($this->index[$table.'::'.$i])) {
            $i++;
        }
            
        $this->index[$table.'::'.$i] = $index;
        $this->fields[$index] = Array();
    }

    public function withFields() {
        $index = sizeof($this->tables) - 1;

        for($i = 0, $c = func_num_args(); $i < $c; $i++) {
            $this->fields[$index][] = func_get_arg($i);
        }

        return $this;
    }

    public function onlyDistinct() {
        $this->distinctLines = true;
        return $this;
    }

    public function offset($i) {
        // DEBUG if((Integer) $i < 0) {
        // DEBUG     throw new Exception('Invalid offset');
        // DEBUG }

        $this->offset = (Integer) $i;
        return $this;
    }

    public function lines($i) { // number of SQL rows to output
        // DEBUG if((Integer) $i < 1) {
        // DEBUG     throw new Exception('CodeError::called with invalid argument');
        // DEBUG }

        $this->lines = (Integer) $i;
        return $this;
    }

    public function on($baseTable, $baseField, $destinationField = null) {
        $destinationTableIndex = sizeof($this->tables) - 1;
        $destinationField = is_null($destinationField) ? 'id' : $destinationField;



        $this->links[end($this->tablesIndexed)][$index]['originField'] = $originField;
        $this->links[end($this->tablesIndexed)][$index]['destinationField'] = (is_null($destinationField) ? 'id' : $destinationField);
        return $this;
    }

    private function link($table, $alias = false, $strictly = false) {
        $this->addTable($table);

        $this->links[end($this->tablesIndexed)][] = Array(
            'table' => $index,
            'joinType' => ($strictly ? self::INNER_JOIN : self::LEFT_JOIN),
            'alias' => $alias
        );
        $this->fields[$index] = Array();
        return $this;
    }

    public function linkedTo($table, $alias = false) {
        return $this->link($table, $alias, false);
    }

    public function strictlyLinkedTo($table, $alias = false) {
        return $this->link($table, $alias, true);
    }

    private function joinCondition($logic, $field, $operator, $value, $option, $openGroup, $closeGroup) {
        // DEBUG if(sizeof($this->tables) <= 1) {
        // DEBUG     throw new Exception('CodeError::valid for linked tables only');
        // DEBUG }

        // DEBUG if(!isset(end($this->tables)->$field)) {
        // DEBUG     throw new Exception('Bug::property '.$field.' does not exist');
        // DEBUG }

        $index = sizeof($this->tables) - 1;
        $this->conditions[$index][] = Array(
            'table' => $index,
            'logic' => $logic,
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'option' => $option,
            'openGroup' => $openGroup,
            'closeGroup' => $closeGroup
        );
        return $this;
    }

    public function with($field, $operator, $value = false, $option = false) {
        // DEBUG if(isset($this->conditions[sizeof($this->tables) - 1])) {
        // DEBUG     throw new Exception('CodeError::condition already set');
        // DEBUG }

        return $this->joinCondition(null, $field, $operator, $value, $option, false, false);
    }

    public function withInGroup($field, $operator, $value = false, $option = false) {
        // DEBUG if(isset($this->conditions[sizeof($this->tables) - 1])) {
        // DEBUG     throw new Exception('CodeError::condition already set');
        // DEBUG }

        return $this->joinCondition(null, $field, $operator, $value, $option, true, false);
    }

    public function andWith($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('AND', $field, $operator, $value, $option, false, false);
    }

    public function orWith($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('OR', $field, $operator, $value, $option, false, false);
    }

    public function andWithInGroup($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('AND', $field, $operator, $value, $option, true, false);
    }

    public function orWithInGroup($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('OR', $field, $operator, $value, $option, true, false);
    }

    public function andWithNotInGroup($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('AND', $field, $operator, $value, $option, false, true);
    }

    public function orWithNotInGroup($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('OR', $field, $operator, $value, $option, false, true);
    }

    public function andWithInNewGroup($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('AND', $field, $operator, $value, $option, true, true);
    }

    public function orWithInNewGroup($field, $operator, $value = false, $option = false) {
        return $this->joinCondition('OR', $field, $operator, $value, $option, true, true);
    }

    private function whereCondition($logic, $table, $field, $operator, $value, $option, $openGroup, $closeGroup) {
        // DEBUG if(!isset($this->index[$table])) {
        // DEBUG     throw new Exception('CodeError::invalid table '.$table);
        // DEBUG }

        $index = $this->index[$table];

        // DEBUG if(!isset($this->tables[$index]->$field)) {
        // DEBUG     throw new Exception('CodeError::property '.$field.' does not exists');
        // DEBUG }

        $this->resultsConditions[] = Array(
            'table' => $index,
            'logic' => $logic,
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'option' => $option,
            'openGroup' => $openGroup,
            'closeGroup' => $closeGroup
        );
        return $this;
    }

    public function where($table, $field, $operator, $value = false, $option = false) {
        // DEBUG if(sizeof($this->resultsConditions) != 0) {
        // DEBUG     throw new Exception('First condition already set');
        // DEBUG }

        return $this->whereCondition(null, $table, $field, $operator, $value, $option, false, false);
    }

    public function whereInGroup($table, $field, $operator, $value = false, $option = false) {
        // DEBUG if(sizeof($this->resultsConditions) != 0) {
        // DEBUG     throw new Exception('First condition already set');
        // DEBUG }

        return $this->whereCondition(null, $table, $field, $operator, $value, $option, true, false);
    }

    public function andWhere($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('AND', $table, $field, $operator, $value, $option, false, false);
    }

    public function orWhere($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('OR', $table, $field, $operator, $value, $option, false, false);
    }

    public function andWhereInGroup($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('AND', $table, $field, $operator, $value, $option, true, false);
    }

    public function orWhereInGroup($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('OR', $table, $field, $operator, $value, $option, true, false);
    }

    public function andWhereNotInGroup($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('AND', $table, $field, $operator, $value, $option, false, true);
    }

    public function orWhereNotInGroup($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('OR', $table, $field, $operator, $value, $option, false, true);
    }

    public function andWhereInNewGroup($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('AND', $table, $field, $operator, $value, $option, true, true);
    }

    public function orWhereInNewGroup($table, $field, $operator, $value = false, $option = false) {
        return $this->whereCondition('OR', $table, $field, $operator, $value, $option, true, true);
    }

    public function orderAscBy($table, $field, $option = 0) {
        return $this->orderBy($table, $field, $option, 'ASC');
    }

    public function orderDescBy($table, $field, $option = 0) {
        return $this->orderBy($table, $field, $option, 'DESC');
    }

    private function orderBy($table, $field, $option, $direction) {
        // DEBUG if(!isset($this->index[$table])) {
        // DEBUG     throw new \Exception('Table not included in request');
        // DEBUG }

        $index = $this->index[$table];

        // DEBUG if(!isset($this->tables[$index]->$field)) {
        // DEBUG     throw new \Exception('Property does not exist');
        // DEBUG }

        $this->orderBy[] = Array(
            'table' => $index,
            'field' => $field,
            'option' => $option,
            'direction' => $direction
        );
        return $this;
    }
}
