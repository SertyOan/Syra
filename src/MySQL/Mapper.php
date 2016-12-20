<?php
namespace Syra\MySQL;

abstract class Mapper {
    private
        $database,
        $request,
        $objects = Array(),
        $pathes = Array();

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
        $statement = Parser::parse($this->request);
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
}
