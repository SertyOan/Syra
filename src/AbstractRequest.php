<?php
namespace Syra;

abstract class AbstractRequest {
    const LEFT_JOIN = 1;
    const INNER_JOIN = 2;

    private $database;
    protected $customs = [];
    protected $classes = [];
    protected $index = [];
    protected $fields = [];
    protected $orderBy = [];
    protected $links = [];
    protected $conditions = [];
    protected $bindings;
    protected $offset = 0;
    protected $lines = 0;

    public static function get($table) {
        return new static($table);
    }

    public static function objectsAsArrays(Array $objects = Array()) {
        $arrays = Array();

        foreach($objects as $object) {
            if(!is_subclass_of($object, static::OBJECTS_CLASS)) {
                throw new \LogicException('Can only transform data objects to array');
            }

            $arrays[] = $object->asArray();
        }

        return $arrays;
    }

    private function __construct(string $table, string $customSQL = null) {
        $this->addTable($table, customSQL: $customSQL);
    }

    private function addTable($table, $customSQL = null) {
        $class = $this->buildClassFromTable($table);

        if(!is_subclass_of($class, static::OBJECTS_CLASS)) {
            throw new \Exception('Class is not a child of ModelObject');
        }

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

        if(!empty($customSQL)) {
            $this->customs['TABLE_'.$index] = $customSQL;
        }
    }

    public function withFields() {
        $index = sizeof($this->classes) - 1;
        $class = end($this->classes);

        for($i = 0, $c = func_num_args(); $i < $c; $i++) {
            $property = func_get_arg($i);

            if(!is_string($property)) {
                throw new \InvalidArgumentException('All properties must be strings');
            }

            if(!$class::hasProperty($property)) {
                throw new \InvalidArgumentException('Property ' . $property . ' does not exist');
            }

            $this->fields[$index][] = $property;
        }

        return $this;
    }

    public function leftJoin($table, $alias = null, $customSQL = null) {
        return $this->link($table, alias: $alias, customSQL: $customSQL);
    }

    public function innerJoin($table, $alias = null, $customSQL = null) {
        return $this->link($table, alias: $alias, strictly: true, customSQL: $customSQL);
    }

    protected function link($table, $alias = null, $strictly = false, $customSQL = null) {
        $this->addTable($table, customSQL: $customSQL);
        $index = sizeof($this->classes) - 1;

        $this->links[$index] = [
            'joinType' => ($strictly ? self::INNER_JOIN : self::LEFT_JOIN),
            'alias' => $alias,
            'conditions' => []
        ];

        return $this;
    }

    public function on($leftTable, $leftTableField, $rightTableField = 'id') {
        if(!isset($this->index[$leftTable])) {
            throw new \InvalidArgumentException('Invalid table referenced');
        }

        $rightTableIndex = sizeof($this->classes) - 1;
        $leftTableIndex = $this->index[$leftTable];
        $this->links[$rightTableIndex]['rightTableField'] = $rightTableField;
        $this->links[$rightTableIndex]['leftTableIndex'] = $leftTableIndex;
        $this->links[$rightTableIndex]['leftTableField'] = $leftTableField;
        return $this;
    }

    public function with($logic = '', $field = null, $operator = null, $value = null, $option = null, $table = null) {
        // TODO if $logic does not match a logic, admit it to be the first condition and call same method with '' as first argument preceeding
        if(sizeof($this->classes) <= 1) {
            throw new \InvalidArgumentException('No class linked yet');
        }

        $linkIndex = sizeof($this->classes) - 1;

        if(is_null($table)) {
            $index = $linkIndex;
        }
        else {
            if(!isset($this->index[$table])) {
                throw new \Exception('Invalid table specified');
            }

            $index = $this->index[$table];
        }

        $class = $this->classes[$index];

        if(!$class::hasProperty($field)) {
            throw new \Exception('Field does not exist');
        }

        $conditions =& $this->links[$linkIndex]['conditions'];

        if(sizeof($conditions) === 0) {
            if(preg_match('/^(\(+)?$/', $logic, $matches) === false) {
                throw new \Exception('Invalid logic operator');
            }

            $logic = null;
            $close = 0;
            $open = empty($matches[1]) ? 0 : strlen(trim($matches[1]));
        }
        else {
            if(preg_match('/^(\)+ )?(AND|OR)( \(+)?$/', $logic, $matches) === false) {
                throw new \Exception('Invalid logic operator');
            }
    
            $logic = $matches[2];
            $close = empty($matches[1]) ? 0 : strlen(trim($matches[1]));
            $open = empty($matches[3]) ? 0 : strlen(trim($matches[3]));
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
            if(preg_match('/^(\(+)?(AND)?$/', $logic, $matches) === false) { // ignoring AND in case it is the first condition
                throw new \Exception('Invalid logic operator for first condition');
            }

            $logic = null;
            $close = 0;
            $open = empty($matches[1]) ? 0 : strlen(trim($matches[1]));
        }
        else {
            if(preg_match('/^(\)+ )?(AND|OR)( \(+)?$/', $logic, $matches) === false) {
                throw new \Exception('Invalid logic operator');
            }
    
            $logic = $matches[2];
            $close = empty($matches[1]) ? 0 : strlen(trim($matches[1]));
            $open = empty($matches[3]) ? 0 : strlen(trim($matches[3]));
        }

        if(!isset($this->index[$table])) {
            throw new \Exception('Invalid table specified');
        }

        $index = $this->index[$table];
        $class = $this->classes[$index];

        if(!$class::hasProperty($field)) {
            throw new \Exception('Property specified does not exist in '.$table);
        }

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

    public function orderAscBy($table, $field, $option = null) {
        return $this->orderBy($table, $field, $option, 'ASC');
    }

    public function orderDescBy($table, $field, $option = null) {
        return $this->orderBy($table, $field, $option, 'DESC');
    }

    private function orderBy($table, $field, $option = null, $direction = 'ASC') {
        if(!isset($this->index[$table])) {
            throw new \Exception('Table ' . $table. ' is not included in request');
        }

        $index = $this->index[$table];
        $class = $this->classes[$index];

        if($class::hasProperty($field) === false) {
            throw new \Exception('Property ' . $field . ' does not exist in ' . $table);
        }

        $this->orderBy[] = Array(
            'table' => $index,
            'field' => $field,
            'option' => $option,
            'direction' => $direction
        );
        return $this;
    }

    public function offset($i) {
        $this->offset = max(0, $i);
        return $this;
    }

    public function lines($i) { // number of SQL rows to output, not number of objects of first table
        $this->lines = max(1, $i);
        return $this;
    }

    // NOTE mapping methods

    public function count($field = 'id', $distinct = false) {
        $this->bindings = [];
        $query = $this->generateCountSQL($field, $distinct);
        $count = null;

        foreach($this->getDatabase()->queryRows($query, $this->bindings) as $row) {
            $count = (Integer) $row['C'];
        }

        $this->bindings = [];
        return $count;
    }

    public function mapAsObject() {
        $records = $this->mapAsObjects();
        return end($records);
    }

    public function mapAsArrays() {
        return self::objectsAsArrays($this->mapAsObjects());
    }

    public function mapAsArray() {
        $object = $this->mapAsObject();
        return $object instanceof ModelObject ? $object->asArray() : $object;
    }

    public function mapAsObjects() {
        $this->bindings = [];
        $objects = Array();
        $pathes = Array();
        $lastIndex = null;
        $query = $this->generateDataSQL();

        foreach($this->getDatabase()->queryRows($query, $this->bindings) as $line) {
            for($i = 0, $c = sizeof($this->classes); $i < $c; $i++) {
                if(sizeof($this->fields[$i]) === 0) { // NOTE ignore when no field is retrieved for a table
                    continue;
                }

                $objectID = $line['T'.$i.'_id'];

                if($i === 0) {
                    if(!isset($objects[$objectID])) {
                        $class = $this->classes[$i];
                        $object = new $class;
                        $object->map($line, 'T'.$i);

                        $objects[$objectID] = $object;
                    }
                }
                else if(!empty($objectID)) {
                    $this->linkToRootClass($pathes, $i);
                    $target =& $objects[$line['T0_id']];

                    for($j = sizeof($pathes[$i]) - 1; $j >= 0; $j--) {
                        list($step, $tableIndex) = $pathes[$i][$j];

                        if($j !== 0) {
                            $target =& $target->$step;

                            if(preg_match('/^my/', $step)) {
                                $stepID = $line['T'.$tableIndex.'_id'];

                                if(!isset($target[$stepID])) {
                                    throw new \Exception('Could not find parent object');
                                }

                                $target =& $target[$stepID];
                            }
                        }
                        else { // NOTE here we place the object if needed
                            if(preg_match('/^my/', $step)) {
                                if(!isset($target->{$step}[$objectID])) {
                                    $class = $this->classes[$i];
                                    $object = new $class;
                                    $object->map($line, 'T'.$i);

                                    $target->{$step}[$objectID] = $object;
                                }
                            }
                            else if(!$target->{$step}->isSaved()) {
                                $class = $this->classes[$i];
                                $object = new $class;
                                $object->map($line, 'T'.$i);
                                $target->$step = $object;
                            }
                        }
                    }
                }
            }
        }

        $this->bindings = [];
        return $objects;
    }

    private function linkToRootClass(&$pathes, $rightTableIndex) { // TODO check if we cannot build $pathes during request build
        if(empty($pathes[$rightTableIndex])) {
            $pathes[$rightTableIndex] = Array();

            $link = $this->links[$rightTableIndex];
            $leftTableIndex = $link['leftTableIndex'];

            $linkField = $link['leftTableField'];
            $class = $this->classes[$leftTableIndex];

            if(!empty($link['alias'])) {
                $pathes[$rightTableIndex][] = Array('my'.$link['alias'], $rightTableIndex);
            }
            else {
                $pathes[$rightTableIndex][] = Array($linkField, $rightTableIndex);
            }

            if($link['leftTableIndex'] !== 0) {
                $this->linkToRootClass($pathes, $leftTableIndex);

                foreach($pathes[$leftTableIndex] as $step) {
                    $pathes[$rightTableIndex][] = $step;
                }
            }
        }
    }

    protected function getDatabase() {
        if(empty($this->database)) {
            $class = static::DATABASE_CLASS;

            if(!is_subclass_of($class, '\\Syra\\DatabaseInterface')) {
                throw new \Exception('Invalid database class');
            }

            $this->database = $class::getReader();

            if(!is_a($this->database, static::DATABASE_ABSTRACT_CLASS)) {
                throw new \Exception('Invalid reader database class');
            }
        }
    
        return $this->database;
    }
}
