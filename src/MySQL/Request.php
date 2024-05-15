<?php
namespace Syra\MySQL;

use Syra\AbstractRequest;
use Syra\Reference;

abstract class Request extends AbstractRequest {
    const OBJECTS_CLASS = '\\Syra\\MySQL\\ModelObject';
    const DATABASE_ABSTRACT_CLASS = '\\Syra\\MySQL\\Database';

    public function generateDataSQL() {
        $orderBy = $this->generateSQLOrderBy();

        $statement = $this->generateSQLSelect();
        $statement .= $this->generateSQLJoins();

        $hasAlias = false;

        foreach($this->links as $link) {
            if(!empty($link['alias'])) {
                $hasAlias = true;
                break;
            }
        }

        if($hasAlias && ($this->lines !== 0 || $this->offset !== 0)) {
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

            $customKey = 'TABLE_'.$rightTableIndex;

            if(!empty($this->customs[$customKey])) {
                $sql .= ' '.$this->customs[$customKey].' ';
            }

            $sql .= ' ON (T'.$link['leftTableIndex'].'.`'.$link['leftTableField'].'`=T'.$rightTableIndex.'.`'.$link['rightTableField'].'`';
            $sql .= $this->generateSQLJoinConditions($rightTableIndex);
            $sql .= ')';
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
            $matches = [];
            $field = 'T'.$clause['table'].'.`'.$clause['field'].'`';

            switch($clause['option']) {
                case null:
                    $orders[] = $field . ' ' . $clause['direction'];
                    break;
                case preg_match('@^JSON_VALUE:(\$(?:\.[a-z0-9_]+)+)$@i', $clause['option']) === 1:
                    $orders[] = 'JSON_VALUE(' . $field . ', \'' . $matches[1] . '\')';
                    break;
            }
        }

        $sql .= implode(',', $orders);
        return $sql;
    }

    private function generateSQLOperator($condition) {
        $table =& $condition['table'];
        $operator =& $condition['operator'];
        $field = 'T'.$condition['table'].'.`'.$condition['field'].'`';

        if(!empty($condition['option'])) {
            $matches = [];

            if(preg_match('@^JSON_VALUE:(\$(?:\.[a-z0-9_]+)+)$@i', $condition['option'], $matches)) {
                $field = 'JSON_VALUE(' . $field . ', \'' . $matches[1] . '\')';
            }
        }

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
                $clause = $field.$operator.($link === false ? '?' : $link).'!=0';
                break;
            case '!&':
                $clause = $field.'&'.($link === false ? '?' : $link).'=0';
                break;
            case '!|':
                $clause = $field.'|'.($link === false ? '?' : $link).'=0';
                break;
            case '>':
            case '<':
            case '>=':
            case '<=':
            case '=':
            case '!=':
                $clause = $field.$operator.($link === false ? '?' : $link);
                break;
            case 'LIKE':
            case 'NOT LIKE':
                $clause = $field . ' ' . $operator . ' ?';
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
        } 
        else if(enum_exists($propertyClass)) {
            if($value instanceof $propertyClass) {
                $value = $value->value;
            }

            $propertyClass = gettype($value);
            $propertyClass = ucfirst($propertyClass);
        }

        switch($propertyClass) {
            case 'String':
            case 'JSON':
            case 'DateTime':
                $this->bindings[] = ['value' => (String) $value, 'type' => \PDO::PARAM_STR];
                break;
            case 'Float':
                $this->bindings[] = ['value' => (Float) $value, 'type' => \PDO::PARAM_STR];
                break;
            case 'Integer':
            case 'Timestamp':
                $this->bindings[] = ['value' => (Integer) $value, 'type' => \PDO::PARAM_INT];
                break;
            default:
                throw new \LogicException('Unhandled property class');
        }
    }
}
