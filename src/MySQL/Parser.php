<?php
namespace Syra\MySQL;

class Parser {
	private
		$request,
		$statement = '';

	public static function parse(Request &$request) {
		$parser = new self($request);
		$parser->parseToSQL();
		return $parser->statement;
	}

	public static function count(Model_Request &$request) {
		$parser = new self($request);
		$parser->parseToCount();
		// DEBUG debug('request object parsing result : '.$parser->statement);
		$data = Model_Database::get()->queryRows($parser->statement);
		return ((Integer) $data[0]['C']);
	}

	private function __construct(Request &$request) {
		$this->request =& $request;
	}

	private function parseToSQL() {
		$this->setSelectedFields();
		$this->setTables();
		$this->setWhereClause();

		if(sizeof($this->request->orderBy) !== 0) {
			$this->setOrderByClause();
		}

		if($this->request->lines !== 0 || $this->request->offset !== 0) {
			$this->setLimit();
		}

		// DEBUG debug('request object parsing result : '.$this->statement);
	}

	private function parseToCount() {
		$this->statement .= 'SELECT COUNT(';

		if($this->request->distinctLines) {
			$this->statement .= 'DISTINCT ';
		}
		
		$this->statement .= ' T0.id) AS C';
		$this->setTables();
		$this->setWhereClause();
	}

	private function setSelectedFields() {
		$selectedFields = Array();

		foreach($this->request->fields as $key => $fields) {
			foreach($fields as $field) {
                $selectedFields[] = 'T'.$key.'.'.$field.' AS T'.$key.'_'.$field;
			}
		}

		$this->statement .= 'SELECT ';

		if($this->request->distinctLines) {
			$this->statement .= 'DISTINCT ';
		}

		$this->statement .= implode(',', $selectedFields);
	}

	private function setTables() {
		$rootTable = $this->request->classes[0];
		$this->statement .= "\n".'FROM '.$rootTable::myTable().' T0';

		foreach($this->request->links as $table => $links) {
			foreach($links as $link) {
				if($link['joinType'] == Request::LEFT_JOIN) {
					$this->statement .= "\n".'LEFT JOIN ';
				}
				else if($link['joinType'] == Request::INNER_JOIN) {
					$this->statement .= "\n".'INNER JOIN ';
				}
				else {
					throw new \Exception('CodeError::no join type defined');
				}

				$object = $this->request->tables[$link['table']];

				$this->statement .= $object::myTable().' T'.$link['table'];
				$this->statement .= ' ON (T'.$table.'.`'.$link['originField'].'`=T'.$link['table'].'.`'.$link['destinationField'].'`';
				$this->tableJoinCondition($link['table']);
				$this->statement .= ')';
			}
		}
	}

	private function tableJoinCondition($index) {
		if(isset($this->request->conditions[$index]) && sizeof($this->request->conditions[$index]) != 0) {
			$this->statement .= ' AND (';
			$this->setConditions($this->request->conditions[$index]);
			$this->statement .= ')';
		}
	}

	private function setWhereClause() {
		if(sizeof($this->request->resultsConditions) != 0) {
			$this->statement .= "\n".'WHERE ';
			$this->setConditions($this->request->resultsConditions);
		}
	}

	private function setConditions($conditions) {
		$openedGroups = 0;

		foreach($conditions as $condition) {
			$field = $condition['field'];

			if($condition['closeGroup'] === true) {
				$this->statement .= ')';
				--$openedGroups;
			}

			if(!is_null($condition['logic'])) {
				$this->statement .= ' '.$condition['logic'].' ';
			}

			if($condition['openGroup'] === true) {
				$this->statement .= '(';
				++$openedGroups;
			}

			$value = '';

			if($condition['value'] !== false && !is_array($condition['value'])) {
				switch($this->request->tables[$condition['table']]->getPropertyClass($field)) {
					case 'String':
					case 'JSON':
						$value = "'".Model_Database::get()->escapeString($condition['value'])."'";
						break;
					case 'Integer':
					case 'Timestamp':
						$value = (Integer) $condition['value'];
						break;
					case 'Float':
						$value = (Float) str_replace(',', '.', $condition['value']);
						break;
					case 'DateTime':
						$value = "'".Model_Database::get()->escapeString($condition['value'])."'";
						break;
					default:
						$value = (Integer) $condition['value'];
				}
			}
			else {
				$value = $condition['value'];
			}

			$this->statement .= $this->translateOperator('T'.$condition['table'].'.`'.$condition['field'].'`', $condition['operator'], $value);
		}

		$this->statement .= str_repeat(')', $openedGroups);
	}

	private function setOrderByClause() {
		$this->statement .= "\n".'ORDER BY ';
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

		$this->statement .= implode(',', $orders);
	}

	private function setLimit() {
		$this->statement .= "\n".'LIMIT '.$this->request->offset.','.$this->request->lines;
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
							$value[$key] = "'".Model_Database::get()->escapeString($val)."'";
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
							$value[$key] = "'".Model_Database::get()->escapeString($val)."'";
						}
					}

					$clause = $field.' NOT IN ('.implode(',', $value).')';
				}
				else {
					$clause = $field.' NOT IN (-1)';
				}
				break;
			default:
				throw new \Exception('CodeError::invalid operator');
		}

		return $clause;
	}
}
