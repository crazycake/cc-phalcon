<?php
/**
 * Base Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use \Phalcon\Mvc\Model\Query as PHQL;

class Base extends \Phalcon\Mvc\Model
{
    /* properties */

    /**
     * @var int
     */
    public $id;

    /** ------------------------------------------ § ------------------------------------------------- **/

    /**
     * Late static binding
     * Useful for save ORM actions
     * @link http://php.net/manual/en/language.oop5.late-static-bindings.php
     * @return string The current class name
     */
    public static function who()
    {
        return __CLASS__;
    }

    /* Get Methods
    --------------------------------------------------- § -------------------------------------------------------- */

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
     * Get a Resulset by SQL query.
     * @static
     * @param string $sql  The SQL string
     * @param array $binds The query bindings (optional)
     * @param string $className A different class name than self (optional)
     * @return mixed
     */
    public static function getObjectsByQuery($sql = "SELECT 1", $binds = array(), $className = null)
    {
        $className = is_null($className) ? self : $className;

        if(is_null($binds))
            $binds = array();

        $objects = new $className();
        $result  = new BaseResultset(null, $objects, $objects->getReadConnection()->query($sql, $binds));

        return empty($result->count()) ? false : $result;
    }

    /**
     * Get an object property value by executing a SQL query
     * @static
     * @param  string $sql  The SQL string
     * @param  string $prop The object property
     * @param  array $binds The query bindings (optional)
     * @param  string $className A different class name than self (optional)
     * @return mixed
     */
    public static function getObjectPropertyByQuery($sql = "SELECT 1", $prop = "id", $binds = array(), $className = null)
    {
        $className = is_null($className) ? self : $className;

        if(is_null($binds))
            $binds = array();

        $object = new $className();
        $result = new BaseResultset(null, $object, $object->getReadConnection()->query($sql, $binds));
        $result = $result->getFirst();

        return $result ? $result->{$prop} : 0;
    }

    /**
     * Get Objects by PHQL language
     * @static
     * @param string $sql The PHQL query string
     * @param array $binds The binding params array
     * @param boolean $filter Filters the ResultSet (optional)
     * @param boolean $split Splits the ResultSet (optional)
     * @return array
     */
    public static function getObjectsByPhql($phql = "SELECT 1", $binds = array(), $filter = false, $split = false)
    {
        if(is_null($binds))
            $binds = array();

        $query  = new PHQL($phql, \Phalcon\DI::getDefault());
        $result = $query->execute($binds);

        if(empty($result->count()))
            return false;

        return $filter ? BaseResultset::filterResultset($result, $split) : $result;
    }

    /**
     * Executes a PHQL Query, used for INSERT, UPDATE, DELETE
     * @static
     * @param string $phql The PHQL query string
     * @param array $binds The binding params array
     * @return boolean
     */
    public static function executePhql($phql = "SELECT 1", $binds = array())
    {
        if(is_null($binds))
            $binds = array();

        $query = new PHQL($phql, \Phalcon\DI::getDefault());
        //Executing with bound parameters
        $status = $query->execute($binds);

        return $status ? $status->success() : false;
    }

    /**
     * Get messages from a created or updated object
     * @param boolean $json_encode Returns a json string
     * @return mixed array|string
     */
    public function parseOrmMessages($json_encode = false)
    {
        $data = array();

        if (!method_exists($this, 'getMessages'))
            return ($data[0] = "Unknown ORM Error");

        foreach ($this->getMessages() as $msg)
            array_push($data, $msg->getMessage());

        if($json_encode)
            $data = json_encode($data);

        return $data;
    }
}
