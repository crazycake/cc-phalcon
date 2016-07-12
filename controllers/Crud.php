<?php
/**
 * BaseCrud Trait for Backend apps.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use CrazyCake\Phalcon\AppModule;

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
     * Event on after save
     */
    abstract protected function onAfterSave(&$data);

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
    public function listAction()
    {

    }

    /**
     * Ajax POST action for New Object
     */
    public function newAction()
    {
        $this->onlyAjax();

        //get data
        $data = $this->handleRequest();

        //merge paylod if set
        $this->_mergePayload($data);

        try {
            //call listener
            $this->onBeforeSave($data);
        }
        catch(Exception $e) {
            $this->jsonResponse(200, $e->getMessage(), "alert");
        }

        //save object
        $object_class = AppModule::getClass($this->crud_conf["model"]);

        $object = new $object_class();
        //save object
        if(!$object->save($data))
            throw new Exception($object->getMessages());

        //call listener
        $this->onAfterSave($object);

        //send response
        $this->jsonResponse(200, $object->toArray());
    }

    /**
     * View for Update Object
     */
    public function updateAction()
    {

    }

    /**
     * Ajax POST Action for deleting an Object
     */
    public function deleteAction()
    {

    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    private function _mergePayload(&$data)
    {
        //merge payload if set
        if(!isset($data["payload"]))
            return;

        $payload = json_decode($data["payload"], true);

        //merge
        $data = array_merge($data, $payload);

        //unset payload
        unset($data["payload"]);
    }
}
