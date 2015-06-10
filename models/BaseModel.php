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
     * late static binding
     * @link http://php.net/manual/en/language.oop5.late-static-bindings.php
     * @return string
     */
    public static function who() {
        return __CLASS__;
    }

    /**
     * Find Object by ID
     * @param int $id the object ID
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

        return empty($result->count()) ? false : $result;
    }

    /**
     * Get Objects by PHQL language
     * @param string $sql The PHQL query string
     * @param array $binds The binding params array
     * @return array
     */
    public static function getObjectsByPhql($phql = "SELECT 1", $binds = array())
    {
        $query = new \Phalcon\Mvc\Model\Query($phql, \Phalcon\DI::getDefault());
        //Executing with bound parameters
        $objects = $query->execute($binds);

        return empty($objects->count()) ? false : $objects;
    }

    /**
     * Executes a PHQL Query, used for INSERT, UPDATE, DELETE
     * @param string $phql The PHQL query string
     * @param array $binds The binding params array
     * @return boolean
     */
    public static function executePhql($phql = "SELECT 1", $binds = array())
    {
        $query = new \Phalcon\Mvc\Model\Query($phql, \Phalcon\DI::getDefault());
        //Executing with bound parameters
        $status = $query->execute($binds);

        return $status ? $status->success() : false;
    }
}
