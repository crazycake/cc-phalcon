<?php
/**
 * Base Model Resultset
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use \Phalcon\Mvc\Model\Resultset\Simple as Resultset;

/**
 * Base rxtended functions for resultsets
 */
class BaseResultset extends Resultset
{
    /* Resultset Methods
    --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Reduces a Resultset to native objects array
     * @param array $props - Filter properties, if empty array given filters all.
     * @return array
     */
    public function reduce($props = null) {

        //objects
        $objects = [];
        foreach ($this as $obj)
            $objects[] = $obj->reduce($props);

        return $objects;
    }

    /**
     * Splits a resultset for object properties
     * Example ticket_id, ticket_name, brand_id, brand_name
     * @return string
     */
    public function split()
    {
        $result = $this->toArray();

        $result = self::splitResult($result);

        return $result;
    }

    /**
     * Parse an array of objects for Json Struct (API WS)
     * @static
     * @param array $result - A result array
     */
    public static function splitResult($result = array())
    {
        $objects = array();

        //loop each object
        foreach ($result as $obj) {
            //get object properties
            $props = is_array($obj) ? get_object_vars((object)$obj) : get_object_vars($obj);

            if(empty($props))
                continue;

            $new_obj = new \stdClass();

            foreach ($props as $k => $v) {

                //reduce properties that has a class prefix
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
     * @param array $field - The object field name
     * @return array of Ids
     */
    public function toIdsArray($field = "id")
    {
        return self::getIdsArray($this, $field);
    }

    /**
     * Returns an array of Ids of given objects
     * @static
     * @param array $result - The resultSet array or a simple array
     * @param array $field - The object field name
     * @param boolean $unique - Flag for non repeated values
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

    /**
     * Merge all arbitray props
     * @static
     * @param array $result - The resultSet array or a simple array
     * @param array - A simple array
     */
    public static function mergeArbitraryProps(&$result = null)
    {
        if($result instanceof Resultset)
            $result = $this->toArray();

        if(is_object($result))
            $result = get_object_vars($result);

        if(empty($result) || !is_array($result))
            return;

        //anonymous function, merge _ext prop
        $mergeProps = function(&$object) {

            if(!is_array($object))
                return;

            $props = [];

            if(isset($object["_ext"]))
                $props = $object["_ext"];

            if(!is_null($props))
                $object = array_merge($props, $object);

            //unset unwanted props
            unset($object["_ext"]);
        };

        //loop & merge props
        foreach ($result as &$obj)
            $mergeProps($obj);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */
}
