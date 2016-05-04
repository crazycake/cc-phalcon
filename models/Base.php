<?php
/**
 * Base Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

//imports
use \Phalcon\Mvc\Model\Query as PHQL;

/**
 * Base extended functions for models
 */
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
     * @link http://php.net/manual/en/language.oop5.late-static-bindings.php
     * @return string The current class name
     */
    public static function who()
    {
        return __CLASS__;
    }

    /* Events
    ---------------------------------------------- § -------------------------------------------------- */

    /**
     * Set external props to always be null on updates
     */
    public function beforeValidationOnUpdate()
    {
        if (isset($this->_ext))
            $this->_ext = null;
    }

    /* Get Methods
    ---------------------------------------------- § -------------------------------------------------- */

    /**
     * Find Object by ID
     * @param int $id - The object ID
     * @return Object
     */
    public static function getById($id)
    {
        $object = self::findFirst(["id = ?1", "bind" => [1 => $id]]);
        return $object;
    }

    /**
     * Get a Resulset by SQL query.
     * @static
     * @param string $sql - The SQL string
     * @param array $binds - The query bindings (optional)
     * @param string $className - A different class name than self (optional)
     * @return mixed
     */
    public static function getByQuery($sql = "SELECT 1", $binds = [], $className = null)
    {
        if (is_null($binds))
            $binds = [];

        if (is_null($className))
            $className = static::who();

        $objects = new $className();
        $result  = new BaseResultset(null, $objects, $objects->getReadConnection()->query($sql, $binds));

        return empty($result->count()) ? false : $result;
    }

    /**
     * Get an object property value by executing a SQL query
     * @static
     * @param string $sql - The SQL string
     * @param string $prop - The object property
     * @param array $binds - The query bindings (optional)
     * @param string $className - A different class name than self (optional)
     * @return object
     */
    public static function getPropertyByQuery($sql = "SELECT 1", $prop = "id", $binds = [], $className = null)
    {
        if (is_null($binds))
            $binds = [];

        if (is_null($className))
            $className = static::who();

        $object = new $className();
        $result = new BaseResultset(null, $object, $object->getReadConnection()->query($sql, $binds));
        $result = $result->getFirst();

        return $result ? $result->{$prop} : null;
    }

    /**
     * Get Objects by PHQL language
     * @static
     * @param string $sql - The PHQL query string
     * @param array $binds - The binding params array
     * @return array
     */
    public static function getByPhql($phql = "SELECT 1", $binds = [])
    {
        if (is_null($binds))
            $binds = [];

        $query  = new PHQL($phql, \Phalcon\DI::getDefault());
        $result = $query->execute($binds);

        if (empty($result->count()))
            return false;

        return $result;
    }

    /**
     * Executes a PHQL Query, used for INSERT, UPDATE, DELETE
     * @static
     * @param string $phql - The PHQL query string
     * @param array $binds - The binding params array
     * @return boolean
     */
    public static function executePhql($phql = "SELECT 1", $binds = [])
    {
        if (is_null($binds))
            $binds = [];

        $query = new PHQL($phql, \Phalcon\DI::getDefault());
        //Executing with bound parameters
        $status = $query->execute($binds);

        return $status ? $status->success() : false;
    }

    /**
     * Reduces a model object losing ORM properties
     * array $props - Filter properties. if empty array given filters all.
     * @return object
     */
    public function reduce($props = null) {

        return (object)$this->toArray($props);
    }

    /**
     * Get messages from a created or updated object
     * @param boolean $json_encode - Returns a json string
     * @return mixed [array|string]
     */
    public function filterMessages($json_encode = false)
    {
        $data = [];

        if (!method_exists($this, "getMessages"))
            return ($data[0] = "Unknown ORM Error");

        foreach ($this->getMessages() as $msg)
            array_push($data, $msg->getMessage());

        if ($json_encode)
            $data = json_encode($data);

        return $data;
    }
}
