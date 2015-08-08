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
    public function filter($split = false)
    {
        return self::filterResultset($this, $split);
    }

    public static function filterResultset($result, $split = false)
    {
        if(!method_exists($result,'count') || empty($result->count()))
            return array();

        $objects = array();
        foreach ($result as $object) {
            $object = (object) array_filter((array) $object);
            array_push($objects, $object);
        }

        return $split ? self::_splitObjects($objects) : $objects;
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
     * @return array of Ids
     */
    public static function getIdsArray($result, $field = "id")
    {
        $ids = array();

        foreach ($result as $object)
            array_push($ids, $object->{$field});

        return empty($ids) ? false : $ids;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Parse an array of objects for Json Struct (webservices)
     * @static
     * @param array $result An array of filtered objects
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
                //filter properties than has a class prefix
                $namespace = explode("_", $k);

                //validate property namespace, check if class exists in models (append plural noun)
                if(empty($namespace) || !class_exists(ucfirst($namespace[0]."s")))
                    continue;

                $type = $namespace[0];
                $prop = str_replace($type."_","",$k);

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
