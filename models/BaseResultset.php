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
     * Parse ORM properties and returns a simple objects array
     * NOTE: toArray() method ignores afterFetch event
     * @param boolean $split Split objects flag
     * @return mixed array
     */
    public function reduce($split = false)
    {
        return self::reduceResultset($this, $split);
    }

    /**
     * reduces a ResultSet
     * @param array $result A ResultSet object
     * @param boolean $split The split flag
     */
    public static function reduceResultset($result, $split = false, $single = false)
    {
        if(!$result)
            return false;

        //check if result is a single object
        if($single)
            $result = [$result];

        $objects = array();

        foreach ($result as $object) {

            //get object properties & creates a new clean object
            $new_obj = new \stdClass();
            $props   = get_object_vars($object);

            if(empty($props))
                continue;

            foreach ($props as $key => $value)
                $new_obj->{$key} = $value;

            array_push($objects, $new_obj);
        }

        $result = $split ? self::_splitObjects($objects) : $objects;

        return $single ? $result[0] : $result;
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

    /**
     * Parse an array of objects for Json Struct (webservices)
     * @static
     * @param array $result An array of reduced objects
     */
    private static function _splitObjects($result = array())
    {
        $objects = array();

        //loop each object
        foreach ($result as $obj) {
            //get object properties
            $props = get_object_vars($obj);

            if(empty($props))
                continue;

            $new_obj = new \stdClass();

            foreach ($props as $k => $v) {

                //reduce properties than has a class prefix
                $namespace = explode("_", $k);

                //check property namespace, check if class exists in models (append plural noun)
                if(empty($namespace) || !class_exists(ucfirst($namespace[0]."s"))) {
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
}
