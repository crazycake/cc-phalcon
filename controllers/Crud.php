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
		//validations
        if(empty($conf["entity"]))
            throw new Exception("Crud requires object model class name.");

		//set default fields?
		if(!isset($conf["fields"]))
			throw new Exception("Crud requires fields array option.");

		$fields_meta = [];
		//create fields metadata
		foreach ($conf["fields"] as $field) {

			$obj = (object)[
				"title" 	=> current($field),
				"name" 		=> key($field),
				"sortField" => key($field)
			];

			//a date?
			if(in_array($obj->name, ["created_at", "date"]))
				$obj->callback = "formatDate|D/MM/Y";

			$fields_meta[] = $obj;
		}

		//fields filter
		$conf["fields"] = array_map(create_function('$o', 'return key($o);'), $conf["fields"]);
		//fields js metadata
		$conf["fields_meta"] = $fields_meta;

		//finally set conf
        $this->crud_conf = $conf;
    }

    /**
     * TODO: Move to CRUD controller
     * View - index
     */
    public function indexAction()
    {
		//set list objects
		$entity = strtolower($this->crud_conf["entity"]);

		//set current_view
		$this->view->setVar("current_view", $entity);
		//set common layout
		$this->view->setLayout("crud");

        //load modules
        $this->loadJsModules([
            "crud" => [
				"actions" => true,
				"entity"  => $entity,
				"fields"  => $this->crud_conf["fields_meta"],
				"sfields" => $this->crud_conf["sfields"]
			]
        ]);
    }

    /**
     * Ajax GET action to retrieve List Collection
     * params: sort, filter, page, per_page
     */
    public function listAction()
    {
		$this->onlyAjax();

		$data = $this->handleRequest([
			"@sort"   	=> "string",
			"@filter" 	=> "string",
			"@page" 	=> "int",
			"@per_page" => "int"
		], "GET");

		//list query conditions
		if(!isset($this->crud_conf["find"]))
			$this->crud_conf["find"] = ["order" => "id DESC"];

		//sort param
		if(!empty($data["sort"]))
			$this->crud_conf["find"]["order"] = str_replace("|", " ", $data["sort"]);

		//filter param
		if(!empty($data["filter"])) {

			//create filter syntax
			$search_fields = isset($this->crud_conf["sfields"]) ?
								   $this->crud_conf["sfields"] : ["id"];

			$syntax = [];
			foreach ($search_fields as $k => $v)
				$syntax[] = "$v LIKE '%".$data["filter"]."%'";

			$this->crud_conf["find"]["conditions"] = implode(" OR ", $syntax);
		}
		//print_r($this->crud_conf["find"]);exit;

		//find objects
		$model_name   = $this->crud_conf["entity"];
		$objects      = $model_name::find($this->crud_conf["find"]);
		$current_page = (int)$data["page"];
		$per_page     = (int)$data["per_page"];

		// Passing a resultset as data
		$paginator = new PaginatorModel([
		    "data"  => $objects,
		    "limit" => $per_page,
		    "page"  => $current_page
		]);
		//page object
		$page = $paginator->getPaginate();

		//set data items
		$items = [];
		foreach ($page->items as $obj)
			$items[] = $obj->toArray();

		//baseUrl
		$url = $this->baseUrl(strtolower($model_name)."/list?page=");
		//limits
		$total = $objects->count();
		$from  = $current_page == 1 ? 1 : ($per_page*$current_page - $per_page + 1);
		$to    = $from + $per_page - 1;

		if($to > $total) $to = $total;

		//create response object
		$response = (object)[
			"total" 	    => $total,
			"per_page" 		=> $per_page,
			"current_page"  => $page->current,
			"last_page" 	=> $page->last,
			//just for indexing in view
			"from" 			=> $from,
			"to" 			=> $to,
			//urls
			"next_page_url" => $url.$page->next,
			"prev_page_url" => $url.$page->before,
			//objects
			"data" 			=> $items
		];

		//output json response
		$this->outputJsonResponse($response);
    }

    /**
     * Ajax POST action for New Object
     */
    public function newAction()
    {
        $this->onlyAjax();

        //get data
        $data = $this->handleRequest([], "POST");

        //merge paylod if set
        $this->_mergePayload($data);

        try {
            //call listener
            $this->onBeforeSave($data);

			//save object
	        $object_class = AppModule::getClass($this->crud_conf["entity"]);

	        $object = new $object_class();
	        //save object
	        if(!$object->save($data))
	            throw new Exception($object->getMessages());

	        //call listener
	        $this->onAfterSave($object);

			//send response
	        $this->jsonResponse(200);
        }
        catch (Exception $e) {
            $this->jsonResponse(200, $e->getMessage(), "alert");
        }
    }

    /**
     * Ajax POST action for update an Object
     */
    public function updateAction()
    {
		$this->onlyAjax();

		$data = $this->handleRequest([
			"id" => "int"
		], "POST");

		$this->jsonResponse(200, $data);
    }

    /**
     * Ajax POST Action for delete an Object
     */
    public function deleteAction()
    {
		$this->onlyAjax();

		$data = $this->handleRequest([
			"id" => "int"
		], "POST");

		//find object
		$model_name = $this->crud_conf["entity"];
		$object 	= $model_name::getById($data["id"]);

		$deleted = false;

		if($object)
			$object->delete();

		//send response
        $this->jsonResponse(200, ["deleted" => $deleted]);
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
