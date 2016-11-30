<?php
class Model_Parser {
	private static
		$language;

	private
		$request,
		$stmt = '';

	public static function setLanguage($language) {
		if(!Kernel_G11n::isActiveLanguage($language)) {
			throw new Exception('CodeError::invalid language for parser');
		}

		self::$language =& $language;
	}

	public static function getLanguage() {
		return self::$language;
	}

	public static function parse(Model_Request &$request) {
		$parser = new Model_Parser($request);
		$parser->parseToSQL();
		return $parser->stmt;
	}

	public static function count(Model_Request &$request) {
		$parser = new Model_Parser($request);
		$parser->parseToCount();
		// DEBUG debug('request object parsing result : '.$parser->stmt);
		$data = Model_Database::get()->queryRows($parser->stmt);
		return ((Integer) $data[0]['C']);
	}

	private function __construct(Model_Request &$request) {
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

		// DEBUG debug('request object parsing result : '.$this->stmt);
	}

	private function parseToCount() {
		$this->stmt .= 'SELECT COUNT(';

		if($this->request->distinctLines) {
			$this->stmt .= 'DISTINCT ';
		}
		
		$this->stmt .= ' T0.id) AS C';
		$this->setTables();
		$this->setWhereClause();
	}

	private function setSelectedFields() {
		$selectedFields = Array();

		foreach($this->request->fields as $key => $fields) {
			foreach($fields as $field) {
				if(!is_null(self::$language)
				&& $this->request->tables[$key]->isTranslated($field)
				&& !isset($this->request->notTranslatedTables[$key])) {
					$selectedFields[] = 'IFNULL(TRL'.$key.'.'.$field.', T'.$key.'.'.$field.') AS T'.$key.'_'.$field;
				}
				else {
					$selectedFields[] = 'T'.$key.'.'.$field.' AS T'.$key.'_'.$field;
				}
			}
		}

		$this->stmt .= 'SELECT ';

		if($this->request->distinctLines) {
			$this->stmt .= 'DISTINCT ';
		}

		$this->stmt .= implode(',', $selectedFields);
	}

	private function setTables() {
		$rootTable = $this->request->tables[0];
		$this->stmt .= "\n".'FROM `'.$rootTable->myTable().'` T0';

		if(!is_null(self::$language)
		&& $rootTable::TRANSLATED
		&& !isset($this->request->notTranslatedTables[0])) {
			$this->stmt .= "\n".'LEFT JOIN `'.$rootTable->myTable().'_TRL` TRL0 ';
			$this->stmt .= 'ON (T0.id=TRL0.id AND TRL0.language=\''.self::$language.'\')';
		}

		foreach($this->request->links as $table => $links) {
			foreach($links as $link) {
				if($link['joinType'] == Model_Request::LEFT_JOIN) {
					$this->stmt .= "\n".'LEFT JOIN ';
				}
				else if($link['joinType'] == Model_Request::INNER_JOIN) {
					$this->stmt .= "\n".'INNER JOIN ';
				}
				else {
					throw new Exception('CodeError::no join type defined');
				}

				$object = $this->request->tables[$link['table']];

				$this->stmt .= '`'.$object->myTable().'` T'.$link['table'];
				$this->stmt .= ' ON (T'.$table.'.`'.$link['originField'].'`=T'.$link['table'].'.`'.$link['destinationField'].'`';
				$this->tableJoinCondition($link['table']);
				$this->stmt .= ')';

				if(!empty(self::$language)
				&& $object::TRANSLATED
				&& !isset($this->request->notTranslatedTables[$link['table']])) {
					$this->stmt .= "\n".'LEFT JOIN `'.$object->myTable().'_TRL` TRL'.$link['table'].' ON (T'.$link['table'].'.id=TRL'.$link['table'].'.id AND TRL'.$link['table'].'.language=\''.self::$language.'\')';
				}
			}
		}
	}

	private function tableJoinCondition($index) {
		if(isset($this->request->conditions[$index]) && sizeof($this->request->conditions[$index]) != 0) {
			$this->stmt .= ' AND (';
			$this->setConditions($this->request->conditions[$index]);
			$this->stmt .= ')';
		}
	}

	private function setWhereClause() {
		if(sizeof($this->request->resultsConditions) != 0) {
			$this->stmt .= "\n".'WHERE ';
			$this->setConditions($this->request->resultsConditions);
		}
	}

	private function setConditions($conditions) {
		$openedGroups = 0;

		foreach($conditions as $condition) {
			$field = $condition['field'];

			if($condition['closeGroup'] === true) {
				$this->stmt .= ')';
				--$openedGroups;
			}

			if(!is_null($condition['logic'])) {
				$this->stmt .= ' '.$condition['logic'].' ';
			}

			if($condition['openGroup'] === true) {
				$this->stmt .= '(';
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

			$this->stmt .= $this->translateOperator('T'.$condition['table'].'.`'.$condition['field'].'`', $condition['operator'], $value);
		}

		$this->stmt .= str_repeat(')', $openedGroups);
	}

	private function setOrderByClause() {
		$this->stmt .= "\n".'ORDER BY ';
		$orders = Array();

		foreach($this->request->orderBy as $clause) {
			switch($clause['option']) {
				case Model_Request::DAY:
					$orders[] = 'DAY(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
					break;
				case Model_Request::MONTH:
					$orders[] = 'MONTH(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
					break;
				case Model_Request::YEAR:
					$orders[] = 'YEAR(T'.$clause['table'].'.`'.$clause['field'].'`) '.$clause['direction'];
					break;
				default:
					$orders[] = 'T'.$clause['table'].'.`'.$clause['field'].'` '.$clause['direction'];
			}
		}

		$this->stmt .= implode(',', $orders);
	}

	private function setLimit() {
		$this->stmt .= "\n".'LIMIT '.$this->request->offset.','.$this->request->lines;
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
			case Model_Request::IS_NULL:
				$clause = $field.' IS NULL';
				break;
			case Model_Request::IS_NOT_NULL:
				$clause = $field.' IS NOT NULL';
				break;
			case Model_Request::GREATER_THAN:
				$clause = $field.'>'.$value;
				break;
			case Model_Request::LOWER_THAN:
				$clause = $field.'<'.$value;
				break;
			case Model_Request::EQUAL_OR_GREATER_THAN:
				$clause = $field.'>='.$value;
				break;
			case Model_Request::EQUAL_OR_LOWER_THAN:
				$clause = $field.'<='.$value;
				break;
			case Model_Request::EQUAL:
				$clause = $field.'='.$value;
				break;
			case Model_Request::DIFFERENT:
				$clause = $field.'!='.$value;
				break;
			case Model_Request::LIKE:
				$clause = $field.' LIKE '.$value;
				break;
			case Model_Request::LIKE_CI:
				$clause = 'UPPER('.$field.') LIKE UPPER('.$value.')';
				break;
			case Model_Request::IN:
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
			case Model_Request::NOT_IN:
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
				throw new Exception('CodeError::invalid operator');
		}

		return $clause;
	}
}
