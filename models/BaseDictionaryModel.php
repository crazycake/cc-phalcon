<?php
/**
 * Base Dictionary Model
 * This simple model is used for key => array structs
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Models;

class BaseDictionaryModel
{
     /* properties */
     
     /**
      * @var int
      */
     public $id;

     /**
      * key-value object_id => data
      * @var array
      */
     public $objects;

     /**
      * contructor
      * @param int $obj_id The object id
      */
     function __construct($obj_id = null)
     {
        if(is_null($obj_id))
            throw new \Exception("BaseDictionaryModel::__construct -> param obj_id is required and must be an non-empty value.");

        //set properties
        $this->id = $obj_id;
        $this->objects = array();
    }

    /**
     * Set some value to self array
     * Struct: $key => $data
     * @param string $key An ID for the data
     * @param mixed $data Any object
     */
    public function setValueForKey($key = "0", $data = null)
    {
        if(empty($key))
            throw new \Exception("BaseDictionaryModel::setValueForKey -> key must not be empty.");
         
        $this->objects[$key] = $data;
    }
}
