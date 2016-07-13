<?php
/**
 * BaseCrud Trait for Backend apps.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//pagination
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
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

        if(empty($this->crud_conf["model"]))
            throw new Exception("Crud requires object model class name.");

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

		//fields filter
		$this->crud_conf["fields_filter"] = array_map(create_function('$o', 'return $o->name;'),
													  $this->crud_conf["fields"]);
    }

    /**
     * TODO: Move to CRUD controller
     * View - index
     */
    public function indexAction()
    {
		//set list objects
		$module_name = strtolower($this->crud_conf["model"]);

        //load modules
        $this->loadJsModules([
            "$module_name" => [
				"actions" => true,
				"url"     => $this->baseUrl($module_name."/list"),
				"fields"  => $this->crud_conf["fields"]
			]
        ]);
    }

    /**
     * Ajax POST action for List Collection
     */
    public function listAction()
    {
		$this->onlyAjax();

		$data = $this->handleRequest([], "GET");

		//list query conditions
		if(!isset($this->crud_conf["find"]))
			$this->crud_conf["find"] = ["order" => "id DESC"];

		$model_name = $this->crud_conf["model"];
		$objects 	= $model_name::find($this->crud_conf["find"]);

		// Passing a resultset as data
		$paginator = new PaginatorModel([
		    "data"  => $objects,
		    "limit" => 10,
		    "page"  => (int)$data["page"]
		]);

		$page = $paginator->getPaginate();

		$items = [];

		foreach ($page->items as $obj)
			$items[] = $obj->toArray($this->crud_conf["fields_filter"]);

		//create response object
		$response = (object)[
			"total" 	    => $objects->count(),
			"per_page" 		=> 15,
			"from" 			=> 1,
			"to" 			=> 15,
			"current_page"  => $page->current,
			"last_page" 	=> $page->last,
			"next_page_url" => $page->next,
			"prev_page_url" => $page->before,
			"data" 			=> $items
		];

		//send response. TODO: put method in core
		$this->response->setStatusCode(200, "OK");
        $this->response->setContentType("application/json");
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_SLASHES));
        $this->response->send();
		die();
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
        catch (Exception $e) {
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
