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
        $classes = Array(),
        $index = Array(),
        $fields = Array(),
        $orderBy = Array(),
        $links = Array(),
        $conditions = Array(),
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

        $this->classes[] = $class;

        $index = sizeof($this->classes) - 1;

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
        $index = sizeof($this->classes) - 1;
        $class = end($this->classes);

        for($i = 0, $c = func_num_args(); $i < $c; $i++) {
            $property = func_get_arg($i);

            if(!$class::hasProperty($property)) {
                throw new \Exception('Property does not exist');
            }

            $this->fields[$index][] = $property;
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

    public function on($leftTable, $leftTableField, $rightTableField = 'id') {
        // DEBUG if(!isset($this->index[$leftTable])) {
        // DEBUG     throw new Exception('Invalid table');
        // DEBUG }

        $rightTableIndex = sizeof($this->classes) - 1;
        $leftTableIndex = $this->index[$leftTable];
        $this->links[$rightTableIndex]['rightTableField'] = $rightTableField;
        $this->links[$rightTableIndex]['leftTableIndex'] = $leftTableIndex;
        $this->links[$rightTableIndex]['leftTableField'] = $leftTableField;
        return $this;
    }

    public function leftJoin($table, $alias = false) {
        return $this->link($table, $alias, false);
    }

    public function innerJoin($table, $alias = false) {
        return $this->link($table, $alias, true);
    }

    private function link($table, $alias = false, $strictly = false) {
        $this->addTable($table);
        $index = sizeof($this->classes) - 1;

        $this->links[$index] = Array(
            'joinType' => ($strictly ? self::INNER_JOIN : self::LEFT_JOIN),
            'alias' => $alias,
            'conditions' => Array()
        );
        return $this;
    }

    public function with($logic, $field, $operator, $value, $option) {
        // DEBUG if(sizeof($this->classes) <= 1) {
        // DEBUG     throw new Exception('No class linked yet');
        // DEBUG }

        $index = sizeof($this->classes) - 1;

        // DEBUG $class = $this->classes[$index];

        // DEBUG if(!$class::hasProperty($field)) {
        // DEBUG     throw new Exception('Field does not exists');
        // DEBUG }

        $conditions =& $this->links[$index]['conditions'];

        if(sizeof($conditions) === 0) {
            if(preg_match('/^(\()?$/', $logic, $matches) === false) {
                throw new Exception('Invalid logic operator');
            }

            $logic = null;
            $close = false;
            $open = !empty($matches[2]);
        }
        else {
            if(preg_match('/^(\) )?(AND|OR)( \()?$/', $logic, $matches) === false) {
                throw new Exception('Invalid logic operator');
            }
    
            $logic = $matches[2];
            $close = !empty($matches[1]);
            $open = !empty($matches[3]);
        }

        $conditions[] = Array(
            'logic' => $logic,
            'table' => $index,
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'option' => $option,
            'open' => $open,
            'close' => $close
        );

        return $this;
    }

    public function where($logic, $table, $field, $operator, $value = null, $option = null) {
        if(sizeof($this->conditions) === 0) {
            if(preg_match('/^(\()?$/', $logic, $matches) === false) {
                throw new Exception('Invalid logic operator');
            }

            $logic = null;
            $close = false;
            $open = !empty($matches[2]);
        }
        else {
            if(preg_match('/^(\) )?(AND|OR)( \()?$/', $logic, $matches) === false) {
                throw new Exception('Invalid logic operator');
            }
    
            $logic = $matches[2];
            $close = !empty($matches[1]);
            $open = !empty($matches[3]);
        }

        // DEBUG if(!isset($this->index[$table])) {
        // DEBUG     throw new Exception('Invalid table');
        // DEBUG }

        $index = $this->index[$table];

        // DEBUG $class = $this->classes[$index];

        // DEBUG if(!$class::hasProperty($field)) {
        // DEBUG     throw new Exception('CodeError::property '.$field.' does not exists');
        // DEBUG }

        $this->conditions[] = Array(
            'logic' => $logic,
            'table' => $index,
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'option' => $option,
            'close' => $close,
            'open' => $open
        );

        return $this;
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
        $class = $this->classes[$index];

        if($class::hasProperty($field) === false) {
            throw new \Exception('Property does not exist');
        }

        $this->orderBy[] = Array(
            'table' => $index,
            'field' => $field,
            'option' => $option,
            'direction' => $direction
        );
        return $this;
    }
}
