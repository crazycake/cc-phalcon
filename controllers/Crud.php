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
     * Event on before render list
     */
    abstract protected function onBeforeRenderList();

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

        if(empty($this->crud_conf["model"]))
            throw new Exception("Crud requires object model class name.");
    }

    /**
     * TODO: Move to CRUD controller
     * View - index
     */
    public function indexAction()
    {
		//call listeners
		$this->onBeforeRenderList();

		//list conditions
		if(!isset($this->crud_conf["list_conditions"]))
			$this->crud_conf["list_conditions"] = ["order" => "id DESC"];

		//set default fields?
		if(!isset($this->crud_conf["fields"])) {

			$this->crud_conf["fields"] = [
				(object)[
					"title" 	=> "ID",
					"name" 		=> "id",
					"sortField" => "id",
					"dataClass" => "text-center"
				],
				(object)[
					"title" 	=> "Nombre",
					"name" 		=> "name",
					"sortField" => "name"
				],
				(object)[
					"title" 	=> "Creado",
					"name" 		=> "created_at",
					"sortField" => "created_at"
				]
			];
		}

		//set list objects
		$model_name  = $this->crud_conf["model"];
		$module_name = strtolower($model_name);
		$fields  	 = array_map(create_function('$o', 'return $o->name;'), $this->crud_conf["fields"]);
		$objects 	 = $model_name::find($this->crud_conf["list_conditions"]);

        //load modules
        $this->loadJsModules([
            "$module_name" => [
				"actions" => true,
				"fields"  => $this->crud_conf["fields"],
				"objects" => $objects ? $objects->toArray($fields) : []
			]
        ]);
    }

    /**
     * Ajax POST action for List Collection
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
     * Ajax POST action for update an Object
     */
    public function updateAction()
    {

    }

    /**
     * Ajax POST Action for delete an Object
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
