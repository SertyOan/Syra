<?php
namespace Syra\MySQL;

abstract class Mapper {
    private
        $database,
        $request,
        $objects = Array(),
        $pathes = Array();

	public static function count(Request $request) {
		$mapper = new static($request);
		$statement = $mapper->generateCountSQL();
		$data = $mapper->database->queryRows($statement);
		return ((Integer) $data[0]['C']);
	}

    public static function mapAsObjects(Request $request) {
        $mapper = new static($request);
        return $mapper->mapRequest();
    }

    public static function mapAsObject(Request $request) {
        $records = self::mapAsObjects($request);
        return end($records);
    }

    public static function mapAsArrays(Request $request) {
        return self::objectsAsArrays(self::map($request));
    }

    public static function mapAsArray(Request $request) {
        $object = self::mapAsObject($request);
        return $object instanceof Object ? $object->asArray() : $object;
    }

    public static function objectsAsArrays(Array $objects = Array()) {
        $arrays = Array();

        foreach($objects as $object) {
            if(!($object instanceof Object)) {
                throw new LogicException('Can only transform data objects to array');
            }

            $arrays[] = $object->asArray();
        }

        return $arrays;
    }

    private function __construct(Request $request) {
        $this->request = $request;
        $class = static::DATABASE_CLASS;
        $this->database = $class::getReader();
    }

    private function mapRequest() {
        $lastIndex = null;
        $statement = $this->generateDataSQL();
        $result = $this->database->query($statement);

        while($line = $result->fetch_assoc()) {
            for($i = 0, $c = sizeof($this->request->classes); $i < $c; $i++) {
                if(sizeof($this->request->fields[$i]) === 0) { // NOTE ignore when no field is retrieved for a table
                    continue;
                }

                $objectID = $line['T'.$i.'_id'];

                if($i === 0) {
                    if(!isset($this->objects[$objectID])) {
                        $class = $this->request->classes[$i];
                        $object = new $class;
                        $object->map($line, 'T'.$i);

                        $this->objects[$objectID] = $object;
                    }
                }
                else if(!empty($objectID)) {
                    $this->linkToRootClass($i);
                    $target =& $this->objects[$line['T0_id']];

                    for($j = sizeof($this->pathes[$i]) - 1; $j >= 0; $j--) {
                        list($step, $tableIndex) = $this->pathes[$i][$j];

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
                                    $class = $this->request->classes[$i];
                                    $object = new $class;
                                    $object->map($line, 'T'.$i);

                                    $target->{$step}[$objectID] = $object;
                                }
                            }
                            else if(!$target->{$step}->isSaved()) {
                                $class = $this->request->classes[$i];
                                $object = new $class;
                                $object->map($line, 'T'.$i);
                                $target->$step = $object;
                            }
                        }
                    }
                }
            }
        }

        return $this->objects;
    }

    private function linkToRootClass($rightTableIndex) {
        if(empty($this->pathes[$rightTableIndex])) {
            $this->pathes[$rightTableIndex] = Array();

            $link = $this->request->links[$rightTableIndex];
            $leftTableIndex = $link['leftTableIndex'];

            $linkField = $link['leftTableField'];
            $class = $this->request->classes[$leftTableIndex];

            if(!empty($link['alias'])) {
                $this->pathes[$rightTableIndex][] = Array('my'.$link['alias'], $rightTableIndex);
            }
            else {
                $this->pathes[$rightTableIndex][] = Array($linkField, $rightTableIndex);
            }

            if($link['leftTableIndex'] !== 0) {
                $this->linkToRootClass($leftTableIndex);

                foreach($this->pathes[$leftTableIndex] as $step) {
                    $this->pathes[$rightTableIndex][] = $step;
                }
            }
        }
    }

	private function generateDataSQL() {
		$statement = $this->generateSQLSelect();
		$statement .= $this->generateSQLJoins();
		$statement .= $this->generateSQLWhere();

		if(sizeof($this->request->orderBy) !== 0) {
			$statement .= $this->generateSQLOrderBy();
		}

		if($this->request->lines !== 0 || $this->request->offset !== 0) {
			$statement .= $this->generateSQLLimit();
		}

        return $statement;
	}

	private function generateCountSQL() {
		$statement = 'SELECT COUNT(';

		if($this->request->distinctLines) {
			$statement .= 'DISTINCT ';
		}
		
		$statement .= ' T0.id) AS C';
		$statement .= $this->generateSQLJoins();
		$statement .= $this->generateSQLWhere();
        return $statement;
	}

	private function generateSQLSelect() {
		$selectedFields = Array();

		foreach($this->request->fields as $key => $fields) {
			foreach($fields as $field) {
                $selectedFields[] = 'T'.$key.'.'.$field.' AS T'.$key.'_'.$field;
			}
		}

		$sql = 'SELECT ';

		if($this->request->distinctLines) {
			$sql .= 'DISTINCT ';
		}

		$sql .= implode(',', $selectedFields);
        return $sql;
	}

	private function generateSQLJoins() {
		$root = $this->request->classes[0];
		$sql = "\n".'FROM '.$root::myTable().' T0';

		foreach($this->request->links as $rightTableIndex => $link) {
            if($link['joinType'] == Request::LEFT_JOIN) {
                $sql .= "\n".'LEFT JOIN ';
            }
            else if($link['joinType'] == Request::INNER_JOIN) {
                $sql .= "\n".'INNER JOIN ';
            }
            else {
                throw new \Exception('No join type defined');
            }

            $rightClass = $this->request->classes[$rightTableIndex];

            $sql .= $rightClass::myTable().' T'.$rightTableIndex;
            $sql .= ' ON (T'.$link['leftTableIndex'].'.`'.$link['leftTableField'].'`=T'.$rightTableIndex.'.`'.$link['rightTableField'].'`';
            $sql .= $this->generateSQLJoinConditions($rightTableIndex);
            $sql .= ')';
		}

        return $sql;
	}

	private function generateSQLJoinConditions($index) {
        $sql = '';
        $conditions = $this->request->links[$index]['conditions'];

        if(sizeof($conditions) !== 0) {
			$sql .= ' AND (';
			$sql .= $this->generateSQLConditions($conditions);
			$sql .= ')';
		}

        return $sql;
	}

	private function generateSQLWhere() {
        $sql = '';

		if(sizeof($this->request->conditions) != 0) {
			$sql .= "\n".'WHERE ';
			$sql .= $this->generateSQLConditions($this->request->conditions);
		}

        return $sql;
	}

	private function generateSQLConditions($conditions) {
		$opened = 0;

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
                $class = $this->request->classes[$condition['table']];

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

			$sql .= $this->translateOperator('T'.$condition['table'].'.`'.$condition['field'].'`', $condition['operator'], $value);
		}

		$sql .= str_repeat(')', $opened);
        return $sql;
	}

	private function generateSQLOrderBy() {
		$sql = "\n".'ORDER BY ';
		$orders = Array();

		foreach($this->request->orderBy as $clause) {
			switch($clause['option']) {
				case Request::DAY:
					$orders[] = 'DAY(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
					break;
				case Request::MONTH:
					$orders[] = 'MONTH(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
					break;
				case Request::YEAR:
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
		return "\n".'LIMIT '.$this->request->offset.','.$this->request->lines;
	}

	private function translateOperator($field, $operator, $value) {
		$clause = '';

		if(is_array($value)
		&& isset($value[0])
		&& isset($value[1])
		&& isset($this->request->index[$value[0]])) {
			$value = 'T'.$this->request->index[$value[0]].'.'.$value[1];
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
			case Model_Request::LIKE_CI: // TODO
				$clause = 'UPPER('.$field.') LIKE UPPER('.$value.')';
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
