<?php namespace Syra;

class Request {
	const
		LEFT_JOIN = 1,
		INNER_JOIN = 2,
		UPPER = 1,
		LOWER = 2,
		DAY = 1,
		MONTH = 2,
		YEAR = 3;

	private
		$tables = Array(),
		$notTranslatedTables = Array(),
		$index = Array(),
		$fields = Array(),
		$orderBy = Array(),
		$links = Array(),
		$conditions = Array(),
		$resultsConditions = Array(),
		$tablesIndexed = Array(),
		$distinctLines = false,
		$offset = 0,
		$lines = 0;

	public static function get($table) {
		return new self($table);
	}

	private function __construct($table) {
		$class = str_replace('.', '_Model_', $table);
		$object = new $class;

		// DEBUG if(!($object instanceof Model_Object)) {
		// DEBUG 	throw new Exception('CodeError::needs a Model_Object');
		// DEBUG }

		$this->tables[0] =& $object;
		$this->fields[0] = Array();
		$this->index[$table] = 0;
		$this->index[$table.'::1'] = 0;
		$this->tablesIndexed = Array(0 => 0);
	}

	public function __get($property) {
		return $this->$property;
	}
	
	private function orderBy($table, $field, $option, $direction) {
		// DEBUG if(!isset($this->index[$table])) {
		// DEBUG 	throw new Exception('Bug::invalid table '.$table);
		// DEBUG }

		$index = $this->index[$table];

		// DEBUG if(!isset($this->tables[$index]->$field)) {
		// DEBUG 	throw new Exception('CodeError::property '.$field.' does not exist');
		// DEBUG }

		$this->orderBy[] = Array(
			'table' => $index,
			'field' => $field,
			'option' => $option,
			'direction' => $direction
		);
		return $this;
	}

	public function orderAscBy($table, $field, $option = false) {
		return $this->orderBy($table, $field, $option, 'ASC');
	}

	public function orderDescBy($table, $field, $option = false) {
		return $this->orderBy($table, $field, $option, 'DESC');
	}

	public function withFields() {
		$index = sizeof($this->tables) - 1;

		for($i = 0, $c = func_num_args(); $i < $c; ++$i) {
			$this->fields[$index][] = func_get_arg($i);
		}

		return $this;
	}

	public function withoutField() {
		$this->fields[sizeof($this->tables) - 1] = Array();
		return $this;
	}

	public function withoutTranslation() {
		$this->notTranslatedTables[sizeof($this->tables) - 1] = true;
		return $this;
	}

	public function onlyDistinct() {
		$this->distinctLines = true;
		return $this;
	}

	public function offset($i) {
		// DEBUG if((Integer) $i < 0) {
		// DEBUG 	throw new Exception('CodeError::called with invalid argument');
		// DEBUG }

		$this->offset = (Integer) $i;
		return $this;
	}

	public function lines($i) { // number of SQL rows to output
		// DEBUG if((Integer) $i < 1) {
		// DEBUG 	throw new Exception('CodeError::called with invalid argument');
		// DEBUG }

		$this->lines = (Integer) $i;
		return $this;
	}

	public function on($originField, $destinationField = null) {
		$index = sizeof($this->links[end($this->tablesIndexed)]) - 1;
		$this->links[end($this->tablesIndexed)][$index]['originField'] = $originField;
		$this->links[end($this->tablesIndexed)][$index]['destinationField'] = (is_null($destinationField) ? 'id' : $destinationField);
		return $this;
	}

	private function link($table, $alias = false, $strictly = false) {
		$class = str_replace('.', '_Model_', $table);

		// DEBUG if(!class_exists($class)) {
		// DEBUG 	throw new Exception('CodeError::undefined class '.$class);
		// DEBUG }

		$this->tables[] = new $class;
		$index = sizeof($this->tables) - 1;

		if(!isset($this->index[$table])) {
			$this->index[$table] = $index;
			$this->index[$table.'::1'] = $index;
		}
		else {
			$i = 2;

			while(true) {
				if(!isset($this->index[$table.'::'.$i])) {
					$this->index[$table.'::'.$i] = $index;
					break;
				}

				$i++;
			}
		}

		$this->links[end($this->tablesIndexed)][] = Array(
			'table' => $index,
			'joinType' => ($strictly ? self::INNER_JOIN : self::LEFT_JOIN),
			'alias' => $alias
		);
		$this->fields[$index] = Array();
		return $this;
	}

	public function linkedTo($table, $alias = false, $strictly = false) {
		$this->tablesIndexed = Array(0 => 0);
		return $this->link($table, $alias, $strictly);
	}

	public function strictlyLinkedTo($table, $alias = false) {
		return $this->linkedTo($table, $alias, true);
	}

	public function andLinkedTo($table, $alias = false, $strictly = false) {
		// DEBUG if(!isset($this->links[end($this->tablesIndexed)])) {
		// DEBUG 	throw new Exception('CodeError::no link is defined for current table');
		// DEBUG }

		return $this->link($table, $alias, $strictly);
	}

	public function andStrictlyLinkedTo($table, $alias = false) {
		return $this->andLinkedTo($table, $alias, true);
	}

	public function itselfLinkedTo($table, $alias = false, $strictly = false) {
		// DEBUG if(sizeof($this->tables) == 1) {
		// DEBUG 	throw new Exception('CodeError::no other class has been set');
		// DEBUG }

		$this->tablesIndexed[] = (end($this->tables) instanceof Model_Object ? sizeof($this->tables) - 1 : sizeof($this->tables) - 2);
		return $this->link($table, $alias, $strictly);
	}

	public function itselfStrictlyLinkedTo($table, $alias = false) {
		return $this->itselfLinkedTo($table, $alias, true);
	}

	private function joinCondition($logic, $field, $operator, $value, $option, $openGroup, $closeGroup) {
		// DEBUG if(sizeof($this->tables) <= 1) {
		// DEBUG 	throw new Exception('CodeError::valid for linked tables only');
		// DEBUG }

		// DEBUG if(!isset(end($this->tables)->$field)) {
		// DEBUG 	throw new Exception('Bug::property '.$field.' does not exist');
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
		// DEBUG 	throw new Exception('CodeError::condition already set');
		// DEBUG }

		return $this->joinCondition(null, $field, $operator, $value, $option, false, false);
	}

	public function withInGroup($field, $operator, $value = false, $option = false) {
		// DEBUG if(isset($this->conditions[sizeof($this->tables) - 1])) {
		// DEBUG 	throw new Exception('CodeError::condition already set');
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
		// DEBUG 	throw new Exception('CodeError::invalid table '.$table);
		// DEBUG }

		$index = $this->index[$table];

		// DEBUG if(!isset($this->tables[$index]->$field)) {
		// DEBUG 	throw new Exception('CodeError::property '.$field.' does not exists');
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
		// DEBUG 	throw new Exception('CodeError::condition already set');
		// DEBUG }

		return $this->whereCondition(null, $table, $field, $operator, $value, $option, false, false);
	}

	public function whereInGroup($table, $field, $operator, $value = false, $option = false) {
		// DEBUG if(sizeof($this->resultsConditions) != 0) {
		// DEBUG 	throw new Exception('CodeError::condition already set');
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

	public function getBack() {
		// DEBUG if(sizeof($this->tablesIndexed) <= 1) {
		// DEBUG  	throw new Exception('CodeError::no class registered');
		// DEBUG }

		array_pop($this->tablesIndexed);
		return $this;
	}
}
