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
        $class = static::DATABASE_CLASS;

        if(!is_subclass_of($class, '\\Syra\\DatabaseInterface')) {
            throw new \Exception('Invalid database class');
        }

        $this->database = $class::getReader();
        $this->addTable($table);
    }

    // NOTE request build methods

    private function addTable($table) {
        $class = $this->buildClassFromTable($table);

        print($class."\n");

        if(!is_subclass_of($class, '\\Syra\\MySQL\\Object')) {
            throw new \Exception('Class is not a child of Object');
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
        $this->offset = max(0, $i);
        return $this;
    }

    public function lines($i) { // number of SQL rows to output, not number of objects of first table
        $this->lines = max(1, $i);
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
        // TODO if $logic does not match a logic, admit it to be the first condition and call same method with '' as first argument preceeding
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

    // NOTE mapping methods

    public static function objectsAsArrays(Array $objects = Array()) {
        $arrays = Array();

        foreach($objects as $object) {
            if(!is_subclass_of($object, '\\Syra\\MySQL\\Object')) {
                throw new \LogicException('Can only transform data objects to array');
            }

            $arrays[] = $object->asArray();
        }

        return $arrays;
    }

    public function count() {
		$statement = $this->generateCountSQL();
		$data = $this->database->queryRows($statement);
		return ((Integer) $data[0]['C']);
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
        return $object instanceof Object ? $object->asArray() : $object;
    }

    public function mapAsObjects() {
        $objects = Array();
        $pathes = Array();
        $lastIndex = null;
        $statement = $this->generateDataSQL();
        $result = $this->database->query($statement);

        while($line = $result->fetch_assoc()) {
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
                                    throw new Exception('Could not find parent object');
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

        return $objects;
    }

    private function linkToRootClass(&$pathes, $rightTableIndex) {
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

		if(sizeof($this->orderBy) !== 0) {
			$statement .= $this->generateSQLOrderBy();
		}

		if($this->lines !== 0 || $this->offset !== 0) {
			$statement .= $this->generateSQLLimit();
		}

        return $statement;
	}

	private function generateCountSQL() {
		$statement = 'SELECT COUNT(';

		if($this->distinctLines) {
			$statement .= 'DISTINCT ';
		}
		
		$statement .= ' T0.id) AS C';
		$statement .= $this->generateSQLJoins();
		$statement .= $this->generateSQLWhere();
        return $statement;
	}

	private function generateSQLSelect() {
		$selectedFields = Array();

		foreach($this->fields as $key => $fields) {
			foreach($fields as $field) {
                $selectedFields[] = 'T'.$key.'.'.$field.' AS T'.$key.'_'.$field;
			}
		}

		$sql = 'SELECT ';

		if($this->distinctLines) {
			$sql .= 'DISTINCT ';
		}

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

	private function generateSQLConditions($conditions) {
		$opened = 0;
        $sql = '';

		foreach($conditions as $condition) {
			$field = $condition['field'];

			if($condition['close'] === true) {
                if($opened === 0) {
                    throw new Exception('Cannot close not opened parenthesis');
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

			$value = '';

			if($condition['value'] !== false && !is_array($condition['value'])) {
                $class = $this->classes[$condition['table']];

				switch($class::getPropertyClass($field)) {
					case 'String':
					case 'JSON':
						$value = "'".$this->database->escapeString($condition['value'])."'";
						break;
					case 'Integer':
					case 'Timestamp':
						$value = (Integer) $condition['value'];
						break;
					case 'Float':
						$value = (Float) str_replace(',', '.', $condition['value']);
						break;
					case 'DateTime':
						$value = "'".$this->database->escapeString($condition['value'])."'";
						break;
					default:
						$value = (Integer) $condition['value'];
				}
			}
			else {
				$value = $condition['value'];
			}

			$sql .= $this->generateSQLOperator('T'.$condition['table'].'.`'.$condition['field'].'`', $condition['operator'], $value);
		}

		$sql .= str_repeat(')', $opened);
        return $sql;
	}

	private function generateSQLOrderBy() {
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

	private function generateSQLLimit() {
		return "\n".'LIMIT '.$this->offset.','.$this->lines;
	}

	private function generateSQLOperator($field, $operator, $value) {
		$clause = '';

		if(is_array($value)
		&& isset($value[0])
		&& isset($value[1])
		&& isset($this->index[$value[0]])) {
			$value = 'T'.$this->index[$value[0]].'.'.$value[1];
		}

		switch($operator) {
			case 'IS NULL':
				$clause = $field.' IS NULL';
				break;
			case 'IS NOT NULL':
				$clause = $field.' IS NOT NULL';
				break;
			case '>':
				$clause = $field.'>'.$value;
				break;
			case '<':
				$clause = $field.'<'.$value;
				break;
			case '>=':
				$clause = $field.'>='.$value;
				break;
			case '<=':
				$clause = $field.'<='.$value;
				break;
			case '=':
				$clause = $field.'='.$value;
				break;
			case '!=':
				$clause = $field.'!='.$value;
				break;
			case 'LIKE':
				$clause = $field.' LIKE '.$value;
				break;
			case 'IN':
				if(is_array($value) && sizeof($value) != 0) {
					if(is_string($value[0])) {
						foreach($value as $key => $val) {
							$value[$key] = "'".$this->database->escapeString($val)."'";
						}
					}

					$clause = $field.' IN ('.implode(',', $value).')';
				}
				else {
					$clause = $field.' IN (-1)';
				}
				break;
			case 'NOT IN':
				if(is_array($value) && sizeof($value) != 0) {
					if(is_string($value[0])) {
						foreach($value as $key => $val) {
							$value[$key] = "'".$this->database->escapeString($val)."'";
						}
					}

					$clause = $field.' NOT IN ('.implode(',', $value).')';
				}
				else {
					$clause = $field.' NOT IN (-1)';
				}
				break;
			default:
				throw new \Exception('Invalid operator');
		}

		return $clause;
	}
}
