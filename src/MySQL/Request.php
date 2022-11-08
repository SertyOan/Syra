<?php
namespace Syra\MySQL;

use Syra\Reference; // TODO ????

abstract class Request {
    const OBJECTS_CLASS = '\\Syra\\MySQL\\ModelObject';
    const LEFT_JOIN = 1;
    const INNER_JOIN = 2;
    const OPTION_DAY = 1;
    const OPTION_MONTH = 2;
    const OPTION_YEAR = 4;

    private $customs = [];
    private $classes = [];
    private $index = [];
    private $fields = [];
    private $orderBy = [];
    private $links = [];
    private $conditions = [];
    private $bindings;
    private $offset = 0;
    private $lines = 0;

    public static function get($table) {
        return new static($table);
    }

    private function __construct($table, $customSQL = null) {
        $class = static::DATABASE_CLASS;

        if(!is_subclass_of($class, '\\Syra\\DatabaseInterface')) {
            throw new \Exception('Invalid database class');
        }

        $this->database = $class::getReader();

        if(!($this->database instanceof Database)) {
            throw new \Exception('Invalid reader database class');
        }

        $this->addTable($table, customSQL: $customSQL);
    }

    // NOTE request build methods

    private function addTable($table, $customSQL = null) {
        $class = $this->buildClassFromTable($table);

        if(!is_subclass_of($class, self::OBJECTS_CLASS)) {
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
                throw new \InvalidArgumentException('All arguments must be strings');
            }

            if(!$class::hasProperty($property)) {
                throw new \InvalidArgumentException('A property does not exist');
            }

            $this->fields[$index][] = $property;
        }

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

    public function leftJoin($table, $alias = null, $customSQL = null) {
        return $this->link($table, alias: $alias, customSQL: $customSQL);
    }

    public function innerJoin($table, $alias = null, $customSQL = null) {
        return $this->link($table, alias: $alias, strictly: true, customSQL: $customSQL);
    }

    private function link($table, $alias = null, $strictly = false, $customSQL = null) {
        $this->addTable($table, customSQL: $customSQL);
        $index = sizeof($this->classes) - 1;

        $this->links[$index] = Array(
            'joinType' => ($strictly ? self::INNER_JOIN : self::LEFT_JOIN),
            'alias' => $alias,
            'conditions' => Array()
        );
        return $this;
    }

    public function with($logic, $field, $operator, $value = null, $option = null) {
        // TODO if $logic does not match a logic, admit it to be the first condition and call same method with '' as first argument preceeding
        if(sizeof($this->classes) <= 1) {
            throw new \InvalidArgumentException('No class linked yet');
        }

        $index = sizeof($this->classes) - 1;
        $class = $this->classes[$index];

        if(!$class::hasProperty($field)) {
            throw new \Exception('Field does not exist');
        }

        $conditions =& $this->links[$index]['conditions'];

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

    public function orderAscBy($table, $field, $option = 0) {
        return $this->orderBy($table, $field, $option, 'ASC');
    }

    public function orderDescBy($table, $field, $option = 0) {
        return $this->orderBy($table, $field, $option, 'DESC');
    }

    private function orderBy($table, $field, $option, $direction) {
        if(!isset($this->index[$table])) {
            throw new \Exception('Table not included in request');
        }

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

    // NOTE mapping methods

    public static function objectsAsArrays(Array $objects = Array()) {
        $arrays = Array();

        foreach($objects as $object) {
            if(!is_subclass_of($object, self::OBJECTS_CLASS)) {
                throw new \LogicException('Can only transform data objects to array');
            }

            $arrays[] = $object->asArray();
        }

        return $arrays;
    }

    public function count($field = 'id', $distinct = false) {
        $this->bindings = [];
        $query = $this->generateCountSQL($field, $distinct);
        $count = null;

        foreach($this->database->queryRows($query, $this->bindings) as $row) {
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

        foreach($this->database->queryRows($query, $this->bindings) as $line) {
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

    // NOTE SQL generation methods

    public function generateDataSQL() {
        $orderBy = $this->generateSQLOrderBy();

        $statement = $this->generateSQLSelect();
        $statement .= $this->generateSQLJoins();

        // TODO no need if no link is 1..n 
        if($this->lines !== 0 || $this->offset !== 0) {
            $statement .= "\n".'INNER JOIN (';
            $statement .= "\n".'SELECT DISTINCT T0.`id` ';
            $statement .= $this->generateSQLJoins();
            $statement .= $this->generateSQLWhere();
            $statement .= "\n".$orderBy;
            $statement .= "\n".'LIMIT '.$this->offset.','.$this->lines;
            $statement .= ') AS Subset ON (Subset.id = T0.id)';
        }
        else {
            $statement .= $this->generateSQLWhere();
        }

        $statement .= $orderBy;
        return $statement;
    }

    public function generateCountSQL($field, $distinct) {
        $statement = 'SELECT COUNT(';

        if($distinct) {
            $statement .= 'DISTINCT ';
        }
        
        $statement .= 'T0.`'.$field.'`) AS C';
        $statement .= $this->generateSQLJoins();
        $statement .= $this->generateSQLWhere();
        return $statement;
    }

    private function generateSQLSelect() {
        $selectedFields = Array();

        foreach($this->fields as $key => $fields) {
            foreach($fields as $field) {
                $selectedFields[] = 'T'.$key.'.`'.$field.'` AS T'.$key.'_'.$field;
            }
        }

        $sql = 'SELECT DISTINCT ';
        $sql .= implode(',', $selectedFields);
        return $sql;
    }

    private function generateSQLJoins() {
        $root = $this->classes[0];
        $sql = "\n".'FROM '.$root::myTable().' T0';
        $customKey = 'TABLE_0';

        if(!empty($this->customs[$customKey])) {
            $sql .= ' '.$this->customs[$customKey].' ';
        }

        foreach($this->links as $rightTableIndex => $link) {
            if($link['joinType'] == Request::LEFT_JOIN) {
                $sql .= "\n".'LEFT JOIN ';
            }
            else if($link['joinType'] == Request::INNER_JOIN) {
                $sql .= "\n".'INNER JOIN ';
            }
            else {
                throw new \Exception('No join type defined');
            }

            $rightClass = $this->classes[$rightTableIndex];

            $sql .= $rightClass::myTable().' T'.$rightTableIndex;
            $sql .= ' ON (T'.$link['leftTableIndex'].'.`'.$link['leftTableField'].'`=T'.$rightTableIndex.'.`'.$link['rightTableField'].'`';
            $sql .= $this->generateSQLJoinConditions($rightTableIndex);
            $sql .= ')';

            $customKey = 'TABLE_'.$rightTableIndex;

            if(!empty($this->customs[$customKey])) {
                $sql .= ' '.$this->customs[$customKey].' ';
            }
        }

        return $sql;
    }

    private function generateSQLJoinConditions($index) {
        $sql = '';
        $conditions = $this->links[$index]['conditions'];

        if(sizeof($conditions) !== 0) {
            $sql .= ' AND (';
            $sql .= $this->generateSQLConditions($conditions);
            $sql .= ')';
        }

        return $sql;
    }

    private function generateSQLWhere() {
        $sql = '';

        if(sizeof($this->conditions) != 0) {
            $sql .= "\n".'WHERE ';
            $sql .= $this->generateSQLConditions($this->conditions);
        }

        return $sql;
    }

    private function generateSQLConditions(&$conditions) {
        $opened = 0;
        $sql = '';

        foreach($conditions as &$condition) {
            if($condition['close'] > 0) {
                if($opened === 0) {
                    throw new \Exception('Cannot close not opened parenthesis');
                }

                $sql .= str_repeat(')', $condition['close']);
                $opened -= $condition['close'];
            }

            if(!empty($condition['logic'])) {
                $sql .= ' '.$condition['logic'].' ';
            }

            if($condition['open'] > 0) {
                $sql .= str_repeat('(', $condition['open']);
                $opened += $condition['open'];
            }

            $sql .= $this->generateSQLOperator($condition);
        }

        $sql .= str_repeat(')', $opened);
        return $sql;
    }

    private function generateSQLOrderBy() {
        if(sizeof($this->orderBy) === 0) {
            $keys = array_keys($this->index);
            $this->orderAscBy($keys[0], 'id');
        }

        $sql = "\n".'ORDER BY ';
        $orders = Array();

        foreach($this->orderBy as $clause) {
            switch($clause['option']) {
                case Request::OPTION_DAY:
                    $orders[] = 'DAY(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
                    break;
                case Request::OPTION_MONTH:
                    $orders[] = 'MONTH(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
                    break;
                case Request::OPTION_YEAR:
                    $orders[] = 'YEAR(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
                    break;
                default:
                    $orders[] = 'T'.$clause['table'].'.`'.$clause['field'].'` '.$clause['direction'];
            }
        }

        $sql .= implode(',', $orders);
        return $sql;
    }

    private function generateSQLOperator($condition) {
        $table =& $condition['table'];
        $operator =& $condition['operator'];

        $field = 'T'.$condition['table'].'.`'.$condition['field'].'`';
        $tableClass = $this->classes[$table];
        $propertyClass = $tableClass::getPropertyClass($condition['field']);
        $link = false;

        if(!is_null($condition['value'])) {
            if($condition['value'] instanceof \stdClass) {
                $reference = $condition['value'];

                if(!isset($this->index[$reference->table])) {
                    throw new \Exception('Invalid table');
                }

                $link = 'T'.$this->index[$reference->table].'.'.$reference->field;
            }
            else {
                if(is_array($condition['value'])) {
                    foreach($condition['value'] as $key => $value) {
                        $this->addBinding($propertyClass, $condition['value'][$key]);
                    }
                }
                else {
                    $this->addBinding($propertyClass, $condition['value']);
                }
            }
        }

        switch($operator) {
            case 'IS NULL':
            case 'IS NOT NULL':
                $clause = $field.' '.$operator;
                break;
            case '&':
            case '|':
            case '>':
            case '<':
            case '>=':
            case '<=':
            case '=':
            case '!=':
                $clause = $field.$operator.($link === false ? '?' : $link);
                break;
            case 'LIKE':
                $clause = $field.' LIKE ?';
                break;
            case 'IN':
            case 'NOT IN':
                if(!is_array($condition['value'])) {
                    throw new \InvalidArgumentException('Invalid values for operator '.$operator);
                }

                $c = sizeof($condition['value']);

                if($c !== 0) {
                    $clause = $field.' '.$operator.' ('.str_repeat('?,', $c - 1).'?)';
                }
                else {
                    $clause = $field.' '.$operator.' (-1)';
                }
                break;
            case 'SQL':
                $clause = $field.' '.$condition['option'];
                break;
            default:
                throw new \LogicException('Invalid operator');
        }

        return $clause;
    }

    private function addBinding($propertyClass, &$value) {
        if(is_subclass_of($propertyClass, self::OBJECTS_CLASS)) {
            $propertyClass = $propertyClass::getPropertyClass('id');
            $value = is_subclass_of($value, self::OBJECTS_CLASS) ? $value->id : $value;
        }  elseif (enum_exists($propertyClass)) {
            if ($value instanceof $propertyClass) {
                $value = $value->value;
            }
            $propertyClass = gettype($value);
        }

        switch($propertyClass) {
            case 'string':  // from above gettype
            case 'String':
            case 'JSON':
            case 'DateTime':
                $this->bindings[] = ['value' => (String) $value, 'type' => \PDO::PARAM_STR];
                break;
            case 'Float':
                $this->bindings[] = ['value' => (Float) $value, 'type' => \PDO::PARAM_STR];
                break;
            case 'integer':  // from above gettype
            case 'Integer':
            case 'Timestamp':
                $this->bindings[] = ['value' => (Integer) $value, 'type' => \PDO::PARAM_INT];
                break;
            default:
                throw new \LogicException('Unhandled property class');
        }
    }
}
