<?php
namespace Syra\MySQL;

abstract class Object {
	protected
		$__inDatabase = false,
		$__nulled = Array(),
		$__collections = Array();

	public function __isset($property) {
		return property_exists($this, $property) || isset($this->__collections[$property]);
	}

	public function __set($property, $value) {
		if(isset(static::$properties[$property])) {
			if(is_null($value)) {
				$this->__nulled[$property] = true;
				$this->$property = null;
			}
			else {
				unset($this->__nulled[$property]);
                $class = static::$properties[$property]['class'];

				switch($class) {
					case 'Integer': $this->$property = (Integer) $value; break;
					case 'Float': $this->$property = (Float) $value; break;
					case 'String': $this->$property = (String) $value; break;
					case 'JSON': $this->$property = is_string($value) ? json_decode($value) : $value; break;
					case 'DateTime': $this->$property = $value instanceof \DateTime ? $value : new \DateTime((String) $value); break;
					case 'Timestamp': $this->$property = $value instanceof \DateTime ? $value : new \DateTime('@'.((Integer) $value)); break;
					default:
						if(is_object($value)) {
							if(get_class($value) !== $class) {
								throw new \Exception('Property '.$property.' set with wrong class for '.get_called_class());
							}

							$this->{$property} = $value;
						}
						else {
							$this->{$property} = new $class();
							$this->{$property}->id = $value;
						}
				}
			}
		}
		else if(preg_match('/^my/', $property)) {
			if(!isset($this->__collections[$property])) {
				$this->__collections[$property] = Array();
			}

            $objects = is_array($value) ? $value : Array($value);

            foreach($objects as $object) {
                if(!($object instanceof self)) {
                    throw new \Exception('Collections can only contain model objects');
                }

                $this->__collections[$property][$value->id] = $value;
            }
		}
		else {
			throw new \LogicException('Property does not exist');
		}
	}

	public function __get($property) {
		if(isset(static::$properties[$property])) {
			if(is_null($this->{$property})) {
				$class = static::$properties[$property]['class'];

				switch($class) {
					case 'Integer':
					case 'Float':
					case 'String':
					case 'DateTime':
					case 'JSON':
					case 'Timestamp':
						break;
					default:
						$this->{$property} = new $class();
				}
			}

			return $this->{$property};
		}
		else if(preg_match('/^my/', $property)) {
			if(!isset($this->__collections[$property])) {
				$this->__collections[$property] = Array();
			}

			return $this->__collections[$property];
		}
		else {
			throw new \Exception('Property does not exist');
		}
	}

	public function getPropertyClass($property) {
		// DEBUG if(empty(static::$properties[$property])) {
		// DEBUG 	throw new \Exception('CodeError::property '.$property.' does not exist');
		// DEBUG }
		return static::$properties[$property]['class'];
	}

	final public function myTable() {
		return static::DATABASE_SCHEMA.'`.`'.static::DATABASE_TABLE;
	}

	final public function myClass() {
		return get_class($this);
	}

	final public function isSaved() {
		return $this->__inDatabase;
	}

	final public function setSaved($bool = true) {
		$this->__inDatabase = (Boolean) $bool;
	}

	final public function map($data, $prefix) {
		$this->__inDatabase = true;

		foreach(static::$properties as $property => $description) {
			if(isset($data[$prefix.'_'.$property])) {
				$this->__set($property, $data[$prefix.'_'.$property]);
			}
		}
	}

	final public function asArray() {
		$array = Array();

		foreach(static::$properties as $property => $description) {
			if(!is_null($this->$property)) {
				switch($description['class']) {
					case 'Integer': $array[$property] = (Integer) $this->$property; break;
					case 'Float': $array[$property] = (Float) $this->$property; break;
					case 'String': $array[$property] = (String) $this->$property; break;
					case 'DateTime': $array[$property] = $this->$property->format('Y-m-d H:i:s'); break;
					case 'Timestamp': $array[$property] = (Integer) $this->$property->format('U'); break;
					case 'JSON': $array[$property] = $this->$property; break;
					default:
						if(!is_null($this->$property->id)) {
							$array[$property] = $this->$property->asArray();
						}
				}
			}
		}

		foreach($this->__collections as $link => $collection) {
			$array[$link] = Array();

			foreach($collection as $object) {
                $array[$link][] = $object->asArray();
			}
		}

		return $array;
	}

	public function delete() {
		if($this->isSaved()) {
			$stmt = 'DELETE FROM `'.$this->myTable().'` WHERE `id`='.$this->id.' LIMIT 1';
            $database = ${static::DATABASE_CLASS}::get();
            $database->writer->query($stmt); // TODO how do we get writer database access in current model
			$this->setSaved(false);
		}
	}

	public function save() {
		$fields = Array();

		foreach(static::$properties as $property => $description) {
			if($property == 'id') {
				continue;
			}

			if(is_null($this->{$property})) {
				if(!empty($this->__nulled[$property])) {
					$fields['`'.$property.'`'] = 'NULL';
				}
			}
			else {
				switch($description['class']) {
					case 'Integer': $fields['`'.$property.'`'] = (Integer) $this->$property; break;
					case 'Float': $fields['`'.$property.'`'] = (Float) $this->$property; break;
					case 'String':
						if(isset($description['maxLength'])) {
							$fields['`'.$property.'`'] = "'".Model_Database::get()->escapeString(substr($this->$property, 0, $description['maxLength']))."'";
						}
						else {
							$fields['`'.$property.'`'] = "'".Model_Database::get()->escapeString($this->$property)."'";
						}
						break;
					case 'DateTime':
						$fields['`'.$property.'`'] = "'".$this->$property->format('Y-m-d H:i:s')."'";
						break;
					case 'Timestamp':
						$fields['`'.$property.'`'] = (Integer) $this->$property->format('U');
						break;
					case 'JSON':
						$fields['`'.$property.'`'] = "'".json_encode($this->$property)."'";
						break;
					default:
						if(isset($this->$property->id) && !is_null($this->$property->id)) {
							$fields['`'.$property.'`'] = $this->$property->id;
						}
				}
			}
		}

		if($this->isSaved()) {
			$stmt = 'UPDATE `'.$this->myTable().'` SET ';
			$updatedFields = Array();

			foreach($fields as $key => $value) {
				$updatedFields[] = $key.'='.$value;
			}

			$stmt .= implode(',', $updatedFields).' WHERE `id`='.$this->id.' LIMIT 1';
		}
		else {
			$stmt = 'INSERT INTO `'.$this->myTable().'` (';
			$stmt .= implode(',', array_keys($fields));
			$stmt .= ') VALUES (';
			$stmt .= implode(',', $fields).')';
		}

		// DEBUG debug('saving object : '.$stmt);

        $database = ${static::DATABASE_CLASS}::get()->writer;
        $database->query($stmt);

		if(!$this->isSaved() && is_null($this->id)) {
			$this->id = $database->link->insert_id;
		}

		$this->__nulled = Array();
		$this->setSaved(true);
	}
}
