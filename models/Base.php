<?php
/**
 * Base Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

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

    /* Resulset Methods
    --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Split ORM Resulset object properties (not static)
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

    /**
     * Parse ORM properties and returns a simple objects array
     * @param object $result Phalcon Resulset
     * @param boolean $split Split objects flag
     * @return mixed array
     */
    public static function parseOrmResultset($result, $split = false)
    {
        if(!method_exists($result,'count') || empty($result->count()))
            return array();

        $objects = array();
        foreach ($result as $object) {
            $object = (object) array_filter((array) $object);
            array_push($objects, $object);
        }

        return $split ? self::splitOrmResulset($objects) : $objects;
    }

    /**
     * Parse ORM resultset for Json Struct (webservices)
     * @access protected
     * @param array $result
     */
    public static function splitOrmResulset($result)
    {
        if(!$result)
            return array();

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
