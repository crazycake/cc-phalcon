<?php
/**
 * Base Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

class BaseModel extends \Phalcon\Mvc\Model
{
    /* properties */
    
    /**
     * @var int
     */
    public $id;

    /** ------------------------------------------ § ------------------------------------------------- **/

    /**
     * Find Object by ID
     * @param int $id
     * @return Object
     */
    public static function getObjectById($id)
    {
        return self::findFirst(array(
            "id = '".$id."'" //conditions
        ));
    }

    /**
     * Get an object property value by executing a SQL query
     * @param  string $sql  The SQL string
     * @param  string $prop The object property
     * @return mixed
     */
    public static function getObjectPropertyByQuery($sql = "SELECT 1", $prop = "id")
    {
        $object = new self();
        $result = new \Phalcon\Mvc\Model\Resultset\Simple(null, $object, $object->getReadConnection()->query($sql));
        $result = $result->getFirst();

        return $result ? $result->{$prop} : 0;
    }

    /**
     * Get a Resulset by SQL query.
     * @todo Fix child afterFetch call
     * @param string $sql  The SQL string
     * @return mixed
     */
    public static function getObjectsByQuery($sql = "SELECT 1")
    {
        $objects = new self();
        $result  = new \Phalcon\Mvc\Model\Resultset\Simple(null, $objects, $objects->getReadConnection()->query($sql));

        return empty($result) ? false : $result;
    }
}
