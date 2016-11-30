<?php
class Model_Mapper {
    private
        $request,
        $objects = Array(),
        $pathes = Array();

    public static function map(Model_Request $request) {
        $mapper = new Model_Mapper($request);
        return $mapper->mapRequest();
    }

    public static function mapObject(Model_Request $request) {
        $records = self::map($request);
        return end($records);
    }

    public static function mapAsArray(Model_Request $request) {
        return self::objectsToArray(self::map($request));
    }

    public static function mapObjectAsArray(Model_Request $request) {
        $object = self::mapObject($request);
        return ($object instanceof Model_Object ? $object->toArray() : $object);
    }

    public static function objectsToArray($objects) {
        $rows = Array();

        foreach($objects as $object) {
            if(!($object instanceof Model_Object)) {
                throw new Exception('CodeError::needs an array of Model_Object');
            }

            $rows[] = $object->toArray();
        }

        return $rows;
    }

    private function __construct(Model_Request $request) {
        $this->request = $request;
    }

    private function mapRequest() {
        $lastIndex = null;
        $stmt = Model_Parser::parse($this->request);
        $rows = Model_Database::get()->queryRows($stmt);

        foreach($rows as $rowID => $line) {
            for($i = 0, $c = sizeof($this->request->tables); $i < $c; $i++) {
                if(sizeof($this->request->fields[$i]) === 0) { // NOTE ignore when no field is retrieved for a table
                    continue;
                }

                $class = $this->request->tables[$i]->myClass();
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

                            if($target instanceof OsyLib_Collection) {
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

            unset($rows[$rowID]);
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

                            if(isset($this->request->tables[$source]->$linkField)
                            && $this->request->tables[$source]->$linkField instanceof Model_Object) {
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
