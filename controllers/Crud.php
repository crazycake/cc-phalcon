<?php
/**
 * BaseCrud Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

/**
 * Base CRUD Controller
 */
trait Crud
{
    /**
     * Event on before save
     */
    abstract protected function onBeforeSave(&$data);

    /**
	 * Config var
	 * @var array
	 */
	protected $crud_conf;

    /* --------------------------------------------------- § -------------------------------------------------------- */


    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    protected function initCrud($conf = [])
    {
        $this->crud_conf = $conf;

        if(empty($conf["model"]))
            throw new Exception("Crud requires object model class name.");

    }

    /**
     * View to get a collection of objects
     */
    protected function listAction()
    {

    }

    /**
     * View for New Object
     */
    protected function newAction()
    {
        //get data
        $data = $this->handleRequest([
            "payload" => "",
        ]);

        //call listener
        $this->onBeforeSave($data);

        //save object
        $object_class = AppModule::getClass($this->crud_conf["model"]);

        $object = new $object_class();
        //save object
        if(!$object->save($data))
            throw new Exception($object->getMessages());

        //send response
        $this->jsonResponse(200, $object->toArray());
    }

    /**
     * View for Update Object
     */
    protected function updateAction()
    {

    }

    /**
     * Ajax POST Action for deleting an Object
     */
    protected function deleteAction()
    {

    }
}
