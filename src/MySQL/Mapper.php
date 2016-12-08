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
        $records = self::map($request);
        return end($records);
    }

    public static function mapAsArrays(Request $request) {
        return self::objectsAsArrays(self::map($request));
    }

    public static function mapAsArray(Request $request) {
        $object = self::mapObject($request);
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
        $this->database = $class::get();
        // TODO check database implements reader/writer methods
    }

    private function mapRequest() {
        $lastIndex = null;
        $statement = Parser::parse($this->request);
        $rows = $this->database->queryRows($statement);
		$result = $this->database->query($statement);

        while($line = $result->fetch_assoc()) {
            for($i = 0, $c = sizeof($this->request->tables); $i < $c; $i++) {
                if(sizeof($this->request->fields[$i]) === 0) { // NOTE ignore when no field is retrieved for a table
                    continue;
                }

                $class = $this->request->tables[$i];
                $object = new $class;
                $object->map($line, 'T'.$i);
                $objectID = $object->id;

                if($i === 0) {
                    if(!isset($this->objects[$objectID])) {
                        $this->objects[$objectID] = $object;
                    }
                }
                else if(!empty($objectID)) {
                    $this->linkToRootClass($i);
                    $target =& $this->objects[$line['T0_id']];

                    for($j = sizeof($this->pathes[$i]) - 1; $j >= 0; $j--) {
                        list($step, $tableID) = explode('-', $this->pathes[$i][$j]);

                        if($j !== 0) {
                            $target =& $target->$step;

                            if(preg_match('/^my/', $step)) {
                                $stepID = $line['T'.$tableID.'_id'];
                                $target =& $target->at($stepID);

                                if($target === false) { // NOTE should never happen and is not possible "a priori"
                                    throw new Exception('CodeError::could not find parent object');
                                }
                            }
                        }
                        else { // NOTE here we place the object if needed
                            if($target->$step instanceof OsyLib_Collection) {
                                $found = $target->$step->at($objectID);

                                if($found === false) {
                                    $target->$step->putAt($object, $objectID);
                                }
                            }
                            else if(!$target->$step->isSaved()) {
                                $target->$step = $object;
                            }
                        }
                    }
                }
            }
        }

        return $this->objects;
    }

    private function linkToRootClass($destination) {
        if(empty($this->pathes[$destination])) {
            $this->pathes[$destination] = Array();

            if(sizeof($this->request->fields[$destination]) !== 0) {
                foreach($this->request->links as $source => $links) {
                    foreach($links as $link) {
                        if($link['table'] === $destination) {
                            $linkField = $link['originField'];

                            if(isset($this->request->tables[$source]->$linkField) && $this->request->tables[$source]->$linkField instanceof Object) {
                                $this->pathes[$destination][] = $linkField.'-'.$destination;
                            }
                            else {
                                // DEBUG if(empty($link['alias'])) {
                                // DEBUG     throw new Exception('CodeError::alias is not set');
                                // DEBUG }

                                $this->pathes[$destination][] = 'linked'.$link['alias'].'-'.$destination;
                            }

                            if($source !== 0) {
                                $this->linkToRootClass($source);

                                foreach($this->pathes[$source] as $step) {
                                    $this->pathes[$destination][] = $step;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
