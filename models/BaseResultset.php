<?php
/**
 * Base Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use \Phalcon\Mvc\Model\Resultset\Simple as Resultset;

class BaseResultset extends Resultset
{
    /* Resultset Methods
    --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Transform a result or array to JSON
     * @param mixed $result
     * @return string
     */
    public function toJson($result)
    {
        if($result instanceof Resultset)
            $result = $result->toArray();

        return json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse an array of objects for Json Struct (webservices)
     * @static
     * @param array $result An array of reduced objects
     */
    private static function splitResult($result = array())
    {
        $objects = array();

        //loop each object
        foreach ($result as $obj) {
            //get object properties
            $props = is_array($obj) ? array_keys($obj) : get_object_vars($obj);

            if(empty($props))
                continue;

            $new_obj = new \stdClass();

            foreach ($props as $k => $v) {

                //reduce properties than has a class prefix
                $namespace = explode("_", $k);

                //check property namespace, check if class exists in models (append plural noun)
                if(empty($namespace) || !class_exists(ucfirst($namespace[0]."s"))) {

                    if(is_null($v)) continue;

                    $type = "global";
                    $prop = $k;
                }
                else {
                    $type = $namespace[0];
                    $prop = str_replace($type."_", "", $k);
                }

                //creates the object struct
                if(!isset($new_obj->{$type}))
                    $new_obj->{$type} = new \stdClass();

                //set props
                $new_obj->{$type}->{$prop} = $v;
            }

            //check for a non-props object
            if(empty(get_object_vars($new_obj)))
                continue;

            array_push($objects, $new_obj);
        }

        return $objects;
    }

    /**
     * Returns an array of Ids of current resultSet object
     * @param array $field The object field name
     * @return array of Ids
     */
    public function toIdsArray($field = "id")
    {
        return self::getIdsArray($this, $field);
    }

    /**
     * Returns an array of Ids of given objects
     * @static
     * @param array $result The resultSet array or a simple array
     * @param array $field The object field name
     * @param boolean $unique Flag for non repeated values
     * @return array of Ids
     */
    public static function getIdsArray($result, $field = "id", $unique = true)
    {
        $ids = array();

        foreach ($result as $object)
            array_push($ids, $object->{$field});

        if(empty($ids))
            return false;

        return $unique ? array_unique($ids) : $ids;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */
}
