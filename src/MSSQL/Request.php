<?php
namespace Syra\MSSQL;

abstract class Request {
    const
        OBJECTS_CLASS = '\\Syra\\MSSQL\\ModelObject',
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
        $bindings,
        $offset = 0,
        $lines = 0;

    public static function get($table) {
        return new static($table);
    }

    private function __construct($table) {
        $class = static::DATABASE_CLASS;

        if(!is_subclass_of($class, '\\Syra\\DatabaseInterface')) {
            throw new \Exception('Invalid database class');
        }

        $this->database = $class::getReader();

        if(!($this->database instanceof Database)) {
            throw new \Exception('Invalid reader database class');
        }

        $this->addTable($table);
    }

    // NOTE request build methods

    private function addTable($table) {
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
        // DEBUG if(!isset($this->index[$leftTable])) {
        // DEBUG     throw new \Exception('Invalid table');
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

    public function with($logic, $field, $operator, $value = null, $option = null) {
        // TODO if $logic does not match a logic, admit it to be the first condition and call same method with '' as first argument preceeding
        // DEBUG if(sizeof($this->classes) <= 1) {
        // DEBUG     throw new \Exception('No class linked yet');
        // DEBUG }

        $index = sizeof($this->classes) - 1;

        // DEBUG $class = $this->classes[$index];

        // DEBUG if(!$class::hasProperty($field)) {
        // DEBUG     throw new \Exception('Field does not exists');
        // DEBUG }

        $conditions =& $this->links[$index]['conditions'];

        if(sizeof($conditions) === 0) {
            if(preg_match('/^(\()?$/', $logic, $matches) === false) {
                throw new \Exception('Invalid logic operator');
            }

            $logic = null;
            $close = false;
            $open = !empty($matches[1]);
        }
        else {
            if(preg_match('/^(\) )?(AND|OR)( \()?$/', $logic, $matches) === false) {
                throw new \Exception('Invalid logic operator');
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
                throw new \Exception('Invalid logic operator');
            }

            $logic = null;
            $close = false;
            $open = !empty($matches[1]);
        }
        else {
            if(preg_match('/^(\) )?(AND|OR)( \()?$/', $logic, $matches) === false) {
                throw new \Exception('Invalid logic operator');
            }
    
            $logic = $matches[2];
            $close = !empty($matches[1]);
            $open = !empty($matches[3]);
        }

        // DEBUG if(!isset($this->index[$table])) {
        // DEBUG     throw new \Exception('Invalid table');
        // DEBUG }

        $index = $this->index[$table];

        // DEBUG $class = $this->classes[$index];

        // DEBUG if(!$class::hasProperty($field)) {
        // DEBUG     throw new \Exception('CodeError::property '.$field.' does not exists');
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

	private function generateDataSQL() {
		$statement = $this->generateSQLSelect();
		$statement .= $this->generateSQLJoins();
        $statement .= $this->generateSQLWhere();

        if(($this->lines !== 0 || $this->offset !== 0) && sizeof($this->orderBy) === 0) {
            $keys = array_keys($this->index);
            $this->orderAscBy($keys[0], 'id');
        }

		if(sizeof($this->orderBy) !== 0) {
			$statement .= $this->generateSQLOrderBy();
		}

		if($this->lines !== 0 || $this->offset !== 0) {
			$statement .= $this->generateSQLLimit();
		}

        return $statement;
	}

	private function generateCountSQL($field, $distinct) {
		$statement = 'SELECT COUNT(';

		if($distinct) {
			$statement .= 'DISTINCT ';
		}
		
		$statement .= 'T0.['.$field.']) AS C';
		$statement .= $this->generateSQLJoins();
		$statement .= $this->generateSQLWhere();
        return $statement;
	}

	private function generateSQLSelect() {
		$selectedFields = Array();

		foreach($this->fields as $key => $fields) {
			foreach($fields as $field) {
                $selectedFields[] = 'T'.$key.'.['.$field.'] AS T'.$key.'_'.$field;
			}
		}

		$sql = 'SELECT DISTINCT ';
		$sql .= implode(',', $selectedFields);
        return $sql;
	}

	private function generateSQLJoins() {
		$root = $this->classes[0];
		$sql = "\n".'FROM '.$root::myTable().' T0';

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
            $sql .= ' ON (T'.$link['leftTableIndex'].'.['.$link['leftTableField'].']=T'.$rightTableIndex.'.['.$link['rightTableField'].']';
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
			if($condition['close'] === true) {
                if($opened === 0) {
                    throw new \Exception('Cannot close not opened parenthesis');
                }

				$sql .= ')';
				$opened--;
			}

			if(!empty($condition['logic'])) {
				$sql .= ' '.$condition['logic'].' ';
			}

			if($condition['open'] === true) {
				$sql .= '(';
				$opened++;
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
					$orders[] = 'DAY(T'.$clause['table'].'.['.$clause['field'].']) '.$clause['direction'];
					break;
				case Request::OPTION_MONTH:
					$orders[] = 'MONTH(T'.$clause['table'].'.['.$clause['field'].']) '.$clause['direction'];
					break;
				case Request::OPTION_YEAR:
					$orders[] = 'YEAR(T'.$clause['table'].'.['.$clause['field'].']) '.$clause['direction'];
					break;
				default:
					$orders[] = 'T'.$clause['table'].'.['.$clause['field'].'] '.$clause['direction'];
			}
		}

		$sql .= implode(',', $orders);
        return $sql;
	}

    private function generateSQLLimit() {
		return "\n".'OFFSET '.$this->offset.' ROWS FETCH NEXT '.$this->lines.' ROWS ONLY';
	}

	private function generateSQLOperator($condition) {
        $table =& $condition['table'];
        $operator =& $condition['operator'];

        $field = 'T'.$condition['table'].'.['.$condition['field'].']';
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
                        $value = is_subclass_of($value, self::OBJECTS_CLASS) ? $value->id : $value;
                        $this->bindings[] = $value;
                    }
                }
                else {
                    $value = $condition['value'];
                    $value = is_subclass_of($value, self::OBJECTS_CLASS) ? $value->id : $value;
                    $this->bindings[] = $value;
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
			default:
				throw new \LogicException('Invalid operator');
		}

		return $clause;
	}
}
