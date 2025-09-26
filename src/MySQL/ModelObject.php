<?php
namespace Syra\MySQL;

abstract class ModelObject {
    private $__inDatabase = false; // TODO review naming
    private $__nulled = Array();
    private $__collections = Array();

    final public static function hasProperty($property) {
        return isset(static::$properties[$property]);
    }

    final public static function getPropertyClass($property) {
        if(!preg_match('@^[a-z0-9_]+$@i', $property)) {
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
                    case 'Boolean': $this->$property = (Boolean) $value; break;
                    case 'Float': $this->$property = (Float) $value; break;
                    case 'String': $this->$property = (String) $value; break;
                    case 'JSON': $this->$property = is_string($value) ? json_decode($value) : $value; break;
                    case 'DateTime': $this->$property = $value instanceof \DateTime ? $value : new \DateTime((String) $value); break;
                    case 'Timestamp': $this->$property = $value instanceof \DateTime ? $value : new \DateTime('@'.((Integer) $value)); break;
                    default:
                        if(enum_exists($class)) {
                            if($value instanceof $class) {
                                $this->{$property} = $value;
                            }
                            else {
                                $back = $class::tryFrom($value);

                                if(is_null($back)) {
                                    throw new \Exception('Property '.$property.' set with an invalid backing value for enum');
                                }

                                $this->{$property} = $back;
                            }
                        }
                        else if(is_object($value)) {
                            if(!($value instanceof $class)) {
                                throw new \Exception('Property '.$property.' set with wrong class for '.get_called_class());
                            }

                            $this->{$property} = $value;
                        }
                        else {
                            $this->{$property} = new $class();
                            $this->{$property}->__set('id', $value);
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

                $this->__collections[$property][$object->id] = $object;
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
                    case 'Boolean':
                    case 'Float':
                    case 'String':
                    case 'DateTime':
                    case 'JSON':
                    case 'Timestamp':
                        break;
                    default:
                        if(enum_exists($class)) { // enum can't be instantiated
                            break;
                        }

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

    public function asArray() {
        $array = Array();

        foreach(static::$properties as $property => $description) {
            if(!is_null($this->$property)) {
                $class = $description['class'];

                switch($class) {
                    case 'Integer': $array[$property] = (Integer) $this->$property; break;
                    case 'Boolean': $array[$property] = (Boolean) $this->$property; break;
                    case 'Float': $array[$property] = (Float) $this->$property; break;
                    case 'String': $array[$property] = (String) $this->$property; break;
                    case 'DateTime': $array[$property] = $this->$property->format('Y-m-d H:i:s'); break;
                    case 'Timestamp': $array[$property] = (Integer) $this->$property->format('U'); break;
                    case 'JSON': $array[$property] = $this->$property; break;
                    default:
                        if(($this->$property instanceof ModelObject) && !is_null($this->$property->id)) {
                            $array[$property] = $this->$property->asArray();
                        }
                        else if(enum_exists($class)) {
                            $array[$property] = $this->$property;
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

            $sql = 'DELETE FROM '.self::myTable().' WHERE `id`=?';
            $params = [];

            switch(static::$properties['id']['class']) {
                case 'Integer':
                    $params[] = ['value' => (Integer) $this->id, 'type' => \PDO::PARAM_INT];
                    break;
                case 'String':
                    $params[] = ['value' => (String) $this->id, 'type' => \PDO::PARAM_STR];
                    break;
                default:
                    throw new \Exception('Invalid class for id field');
            }

            $database->query($sql, $params);
            $this->setSaved(false);
        }
    }

    public function save() {
        $class = static::DATABASE_CLASS;
        $database = $class::getWriter();
        $fields = [];
        $params = [];

        foreach(static::$properties as $property => $description) {
            if($property == 'id') {
                continue;
            }

            if(is_null($this->{$property})) {
                if(!empty($this->__nulled[$property])) {
                    $fields[] = '`'.$property.'`';
                    $params[] = ['value' => null, 'type' => \PDO::PARAM_NULL];
                }
            }
            else {
                $class = $description['class'];

                switch($class) {
                    case 'Integer':
                        $fields[] = '`'.$property.'`';
                        $params[] = ['value' => (Integer) $this->$property, 'type' => \PDO::PARAM_INT];
                        break;
                    case 'Boolean':
                        $fields[] = '`'.$property.'`';
                        $params[] = ['value' => (Boolean) $this->$property, 'type' => \PDO::PARAM_INT];
                        break;
                    case 'Float':
                        $fields[] = '`'.$property.'`';
                        $params[] = ['value' => (Float) $this->$property, 'type' => \PDO::PARAM_STR];
                        break;
                    case 'String':
                        $fields[] = '`'.$property.'`';
                        $value = (String) $this->$property;

                        if(isset($description['maxLength'])) {
                            $value = substr($value, 0, $description['maxLength']);
                        }

                        $params[] = ['value' => $value, 'type' => \PDO::PARAM_STR];
                        break;
                    case 'DateTime':
                        $fields[] = '`'.$property.'`';
                        $params[] = ['value' => $this->$property->format('Y-m-d H:i:s'), 'type' => \PDO::PARAM_STR];
                        break;
                    case 'Timestamp':
                        $fields[] = '`'.$property.'`';
                        $params[] = ['value' => (Integer) $this->$property->format('U'), 'type' => \PDO::PARAM_INT];
                        break;
                    case 'JSON':
                        $fields[] = '`'.$property.'`';
                        $params[] = ['value' => json_encode($this->$property, JSON_UNESCAPED_SLASHES), 'type' => \PDO::PARAM_STR];
                        break;
                    default:
                        if(enum_exists($class)) {
                            $fields[] = '`'.$property.'`';

                            switch(gettype($this->$property->value)) {
                                case 'integer':
                                    $params[] = ['value' => (Integer) $this->$property->value, 'type' => \PDO::PARAM_INT];
                                    break;
                                case 'string':
                                    $params[] = ['value' => (String) $this->$property->value, 'type' => \PDO::PARAM_STR];
                                    break;
                                default:
                                    throw new \Exception('ORM Error: invalid type for enum field');
                            }
                        }
                        else if(isset($this->$property->id) && !is_null($this->$property->id)) {
                            $fields[] = '`'.$property.'`';

                            switch(gettype($this->$property->id)) {
                                case 'integer':
                                    $params[] = ['value' => $this->$property->id, 'type' => \PDO::PARAM_INT];
                                    break;
                                case 'string':
                                    $params[] = ['value' => $this->$property->id, 'type' => \PDO::PARAM_STR];
                                    break;
                                default:
                                    throw new \Exception('ORM Error: invalid type for field');
                            }
                        }
                }
            }
        }

        if($this->isSaved()) {
            $sql = 'UPDATE '.self::myTable().' SET ';

            $fields = array_map(function($field) {
                return $field.'=?';
            }, $fields);

            $sql .= implode(',', $fields).' WHERE `id`=?';

            switch(static::$properties['id']['class']) {
                case 'Integer':
                    $params[] = ['value' => (Integer) $this->id, 'type' => \PDO::PARAM_INT];
                    break;
                case 'String':
                    $params[] = ['value' => (String) $this->id, 'type' => \PDO::PARAM_STR];
                    break;
                default:
                    throw new \Exception('Invalid class for id field');
            }
        }
        else {
            if(!is_null($this->id)) {
                $fields[] = '`id`';

                switch(static::$properties['id']['class']) {
                    case 'Integer':
                        $params[] = ['value' => (Integer) $this->id, 'type' => \PDO::PARAM_INT];
                        break;
                    case 'String':
                        $params[] = ['value' => (String) $this->id, 'type' => \PDO::PARAM_STR];
                        break;
                    default:
                        throw new \Exception('Invalid class for id field');
                }
            }

            $sql = 'INSERT INTO '.self::myTable().' (';
            $sql .= implode(',', $fields);
            $sql .= ') VALUES (';

            $marks = array_fill(0, count($fields), '?');
            $sql .= implode(',', $marks).')';
        }

        $database->query($sql, $params);

        if(!$this->isSaved() && is_null($this->id)) {
            $this->__set('id', $database->link->lastInsertId());
        }

        $this->__nulled = Array();
        $this->setSaved(true);
    }
}
