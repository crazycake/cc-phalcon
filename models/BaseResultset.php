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

        return self::reduceResultset($this, $props);
    }

    /**
     * Reduces a Resultset to native objects array
     * @param object $resultset - Phalcon simple resultset
     * @param array $props - Filter properties, if empty array given filters all.
     * @return array
     */
    public static function reduceResultset($resultset = null, $props = null) {

        if(!$resultset)
            return [];

        //objects
        $objects = [];
        foreach ($resultset as $obj)
            $objects[] = method_exists($obj, "reduce") ? $obj->reduce($props) : (object)$obj->toArray($props);

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
     * Returns an array of distinct ids of given objects
     * @static
     * @param array $result - The resultSet array or a simple array
     * @param array $field - The object field name
     * @param boolean $unique - Flag for non repeated values
     * @return array of Ids
     */
    public static function getIdsArray($result, $field = "id", $unique = true)
    {
        $ids = [];

        foreach ($result as $object)
            array_push($ids, $object->{$field});

        if (empty($ids))
            return false;

        return $unique ? array_unique($ids) : $ids;
    }

    /**
     * Merge arbitrary props in _ext array property.
     * @static
     * @param array $result - The resultset array or a native array
     * @param array
     */
    public static function mergeArbitraryProps(&$result = null)
    {
        if ($result instanceof Resultset)
            $result = $this->toArray();

        if (is_object($result))
            $result = get_object_vars($result);

        if (empty($result) || !is_array($result))
            return;

        //anonymous function, merge _ext prop
        $mergeProps = function(&$object) {

            if (!is_array($object))
                return;

            $props = [];

            if (isset($object["_ext"]))
                $props = $object["_ext"];

            if (!is_null($props))
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
