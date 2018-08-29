<?php
namespace Syra\MySQL;

abstract class Object {
	private
		$__inDatabase = false, // TODO review naming
		$__nulled = Array(),
		$__collections = Array();

    final public static function hasProperty($property) {
		return isset(static::$properties[$property]);
	}

    final public static function getPropertyClass($property) {
        if(!preg_match('@^[a-z0-9]+$@i', $property)) {
            throw new \Exception('Property name is invalid');
        }

		if(empty(static::$properties[$property])) {
            throw new \Exception('Property not defined ('.$property.')');
		}

		return static::$properties[$property]['class'];
	}

	final public static function myTable() {
		return '`'.static::DATABASE_SCHEMA.'`.`'.static::DATABASE_TABLE.'`';
	}

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
							if(!($value instanceof $class)) {
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
			throw new \LogicException('Property '.$property.' does not exist');
		}
	}

	public function &__get($property) {
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
			throw new \LogicException('Property '.$property.' does not exist');
		}
	}

	final public function isSaved() { // TODO review naming
		return $this->__inDatabase;
	}

	final public function setSaved($bool = true) { // TODO review naming
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
            $class = static::DATABASE_CLASS;
            $database = $class::getWriter();

			$stmt = 'DELETE FROM '.self::myTable().' WHERE `id`='.$this->id.' LIMIT 1';
            $database->query($stmt);
			$this->setSaved(false);
		}
	}

	public function save() {
        $class = static::DATABASE_CLASS;
        $database = $class::getWriter();
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
							$fields['`'.$property.'`'] = "'".$database->escapeString(substr($this->$property, 0, $description['maxLength']))."'";
						}
						else {
							$fields['`'.$property.'`'] = "'".$database->escapeString($this->$property)."'";
						}
						break;
					case 'DateTime':
						$fields['`'.$property.'`'] = "'".$this->$property->format('Y-m-d H:i:s')."'";
						break;
					case 'Timestamp':
						$fields['`'.$property.'`'] = (Integer) $this->$property->format('U');
						break;
					case 'JSON':
						$fields['`'.$property.'`'] = "'".$database->escapeString(json_encode($this->$property, JSON_UNESCAPED_SLASHES))."'";
						break;
					default:
						if(isset($this->$property->id) && !is_null($this->$property->id)) {
                            switch(gettype($this->$property->id)) {
                                case 'integer':
                                    $fields['`'.$property.'`'] = $this->$property->id;
                                    break;
                                case 'string':
                                    $fields['`'.$property.'`'] = "'".$database->escapeString($this->$property->id)."'";
                                    break;
                                default:
                                    throw new Exception('ORM Error: invalid type for field');
                            }
						}
				}
			}
		}

		if($this->isSaved()) {
			$stmt = 'UPDATE '.self::myTable().' SET ';
			$updatedFields = Array();

			foreach($fields as $key => $value) {
				$updatedFields[] = $key.'='.$value;
			}

            switch(static::$properties['id']['class']) {
                case 'Integer':
                    $id = (Integer) $this->id;
                    break;
                case 'String':
                    $id = "'".$database->escapeString($this->id)."'";
                    break;
                default:
                    throw new Exception('Invalid class for id field');
            }

			$stmt .= implode(',', $updatedFields).' WHERE `id`='.$id.' LIMIT 1';
		}
		else {
            if(!is_null($this->id)) {
                switch(static::$properties['id']['class']) {
                    case 'Integer':
                        $fields['`id`'] = (Integer) $this->id;
                        break;
                    case 'String':
                        $fields['`id`'] = "'".$database->escapeString($this->id)."'";
                        break;
                    default:
                        throw new Exception('Invalid class for id field');
                }
            }

			$stmt = 'INSERT INTO '.self::myTable().' (';
			$stmt .= implode(',', array_keys($fields));
			$stmt .= ') VALUES (';
			$stmt .= implode(',', $fields).')';
		}

        $database->query($stmt);

		if(!$this->isSaved() && is_null($this->id)) {
			$this->id = $database->link->insert_id;
		}

		$this->__nulled = Array();
		$this->setSaved(true);
	}
}
