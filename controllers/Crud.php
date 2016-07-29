<?php
/**
 * BaseCrud Trait for Backend apps.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//phalcon
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use \Phalcon\Mvc\Model\Resultset\Simple as Resultset;
use Phalcon\Exception;
//imports
use CrazyCake\Phalcon\AppModule;

/**
 * Base CRUD Controller
 */
trait Crud
{
	//uploader trait
	use Uploader;

    /**
     * Event on before save
     */
    abstract protected function onBeforeSave(&$data, $action);

    /**
     * Event on after save
     */
    abstract protected function onAfterSave(&$data, $action);

	/**
     * On after fetch resultset
     * @param object $resultset - Phalcon PDO resultset
     */
    abstract protected function onListResultset(&$resultset);

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
		//default configurations
        $defaults = [
			"id"	   	  => 0,
            "entity"   	  => "",
			"find"		  => [],
            "dfields"  	  => [],
            "sfields"  	  => [],
			"cfields"  	  => [],
			"actions"	  => true,
            "entity_text" => "Colección",
            "new_text" 	  => "Nuevo",
        ];

        //merge confs
        $conf = array_merge($defaults, $conf);

		//set default fields?
		if(empty($conf["entity"]) || empty($conf["dfields"]) || empty($conf["sfields"]))
			throw new \Exception("Crud requires entity, dfields & sfields options.");

		//set entity lower case
		$conf["entity"] = strtolower($conf["entity"]);
		$conf["uploader"]["entity"] = $conf["entity"];

		//prepare fields data for rendering
		$dfields = []; //datatable
		$cfields = []; //categories
		//create fields metadata
		foreach ($conf["dfields"] as $field) {

			$obj = (object)[
				"title" 	=> current($field),
				"name" 		=> key($field),
				"sortField" => key($field)
			];

			//format dates
			if(in_array($obj->name, ["created_at", "date", "datetime"]))
				$obj->callback = "formatDate|D/MM/Y";

			//save categories and set format callback
			if(!empty($field["format"])) {

				//set object callback
				$obj->callback = "formatCategory|".json_encode($field["format"], JSON_UNESCAPED_SLASHES);
				//append new category
				$cfields[$obj->name] = $field["format"];
			}

			$dfields[] = $obj;
		}

		//fields filter
		$conf["dfields"] = $dfields;
		$conf["cfields"] = $cfields;

		//append actions
		if($conf["actions"])
			array_push($conf["dfields"], ["name" => "__actions", "dataClass" => "text-center"]);

		//append fetch url
		$conf["fetch_url"] = $this->baseUrl($conf["entity"]."/list");

		//init uploader?
		if(isset($conf["uploader"]))
        	$this->initUploader($conf["uploader"]);

		//finally set conf
        $this->crud_conf = $conf;
    }

    /**
     * TODO: Move to CRUD controller
     * View - index
     */
    public function indexAction()
    {
		//set current_view
		$this->view->setVars($this->crud_conf);
		//set layout
		$this->view->setLayout("crud");
		$this->view->pick("crud/index");

        //load modules
        $this->loadJsModules([
            "crud" => $this->crud_conf
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

		//find objects
		$entity		= $this->crud_conf["entity"];
		$class_name = AppModule::getClass($entity, false);
		$namespace  = "\\$class_name";
		$query 		= $namespace::query();

		//conditions
		if(isset($this->crud_conf["find"]))
			$query->conditions = $this->crud_conf["find"];

		//joins handler
		$this->crud_conf["joins"] = [];

		//default order
		$query->order($this->_handleBuilderSyntax($query, "id DESC"));

		if(!empty($data["sort"])) {
			//parse sort data from js
			$syntax = str_replace("|", " ", $data["sort"]);
			//set order
			$query->order($this->_handleBuilderSyntax($query, $syntax));
		}

		//filter param for search
		if(!empty($data["filter"])) {

			//create filter syntax
			$search_fields = isset($this->crud_conf["sfields"]) ? $this->crud_conf["sfields"] : ["id"];

			//loop through search fields
			foreach ($search_fields as $index => $fname) {

				$syntax    = "$fname LIKE '%".$data["filter"]."%'";
				$condition = $this->_handleBuilderSyntax($query, $syntax);

				//1st condition
				if(empty($index)) {
					$query->where($condition);
					continue;
				}
				//append other condition
				$query->orWhere($condition);
			}
		}

		//inner joins
		foreach ($this->crud_conf["joins"] as $join)
			$query->join($join);

		//set vars
		$resultset = $query->execute();
		//get pagination response
		$response = $this->_getPaginationData($resultset, $data);

		//listener
		$this->onListResultset($resultset);

		$items = [];
		//check for phalcon resultset
		if($resultset instanceof Resultset) {
			foreach ($resultset as $obj)
				$items[] = $obj->toArray();
		}
		else if(is_array($resultset)) {
			$items = $resultset;
		}

		//set items data
		$response->data = $items;
		//output json response
		$this->outputJsonResponse($response);
    }

    /**
     * Ajax POST action for object creation
     */
    public function createAction()
    {
        $this->onlyAjax();

        //get data
        $data = $this->handleRequest([], "POST");

        //merge paylod if set
        $this->_mergePayload($data);

        try {
            //call listener
            $this->onBeforeSave($data, "create");

			//new object
	        $object_class = AppModule::getClass($this->crud_conf["entity"]);
	        $object 	  = new $object_class();

	        //save object
	        if(!$object->save($data))
	            throw new \Exception($object->allMessages(true));

			//move uploaded files? (UploaderController)
			if(isset($this->crud_conf["uploader"]))
				$this->moveUploadedFiles($object->id);

	        //call listener
	        $this->onAfterSave($object, "create");

			//send response
	        $this->jsonResponse(200);
        }
        catch (\Exception $e) {
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
			"payload" => ""
		], "POST");

		//merge paylod if set
        $this->_mergePayload($data);

        try {
            //call listener
            $this->onBeforeSave($data, "update");

			//get object
	        $object_class = AppModule::getClass($this->crud_conf["entity"]);
	        $object 	  = $object_class::getById($data["id"]);

	        //check object exists
	        if(!$object)
	            throw new \Exception("Objeto no encontrado");

			//consider only attributes from object (phalcon metadata)
			$meta_data  = $object->getModelsMetaData();
			$attributes = $meta_data->getAttributes($object);
			//unset id
			unset($attributes["id"]);

			//filter data
			$new_data = array_filter($data,
							function ($key) use ($attributes) { return in_array($key, $attributes); },
							ARRAY_FILTER_USE_KEY);

			//update values
			$object->update($new_data);

			//move uploaded files? (UploaderController)
			if(isset($this->crud_conf["uploader"]))
				$this->moveUploadedFiles($object->id);

	        //call listener
	        $this->onAfterSave($object, "update");

			//send response
	        $this->jsonResponse(200);
        }
        catch (\Exception $e) {
            $this->jsonResponse(200, $e->getMessage(), "alert");
        }
    }

    /**
     * Ajax POST Action for delete an Object.
     */
    public function deleteAction()
    {
		$this->onlyAjax();

		$data = $this->handleRequest([
			"id" => "int"
		], "POST");

		//find object
		$object_class = $this->crud_conf["entity"];
		$object 	  = $object_class::getById($data["id"]);

		if($object)
			$object->delete();

		//delete upload files?
		if(isset($this->crud_conf["uploader"]))
			$this->cleanUploadFolder($this->_getDestinationFolder($object->id));

		//send response
        $this->jsonResponse(200);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Handles builder syntax (active record)
	 * @param object $query  - The query builder object
	 * @param string $syntax - Any query syntax.
	 */
	private function _handleBuilderSyntax(&$query, $syntax = "")
	{
		$class_name = AppModule::getClass($this->crud_conf["entity"], false);
		$prefix     = $class_name.".";
		$entities   = explode(".", $syntax);

		if(count($entities) < 2)
			return $prefix.$syntax;

		//idenitfy neede joins
		$join_class = \Phalcon\Text::camelize($entities[0]);

		if(!in_array($join_class, $this->crud_conf["joins"]))
			$this->crud_conf["joins"][] = $join_class;

		//caso especial para inner joins
		$syntax = \Phalcon\Text::camelize($syntax);

		return $syntax;
	}

	/**
	 * Get Pagination Data
	 * @param  object $resultset - The resultset
	 * @param  array $data - The input data
	 * @return stdClass object
	 */
	private function _getPaginationData($resultset, $data = [])
	{
		$per_page     = (int)$data["per_page"];
		$current_page = (int)$data["page"];
		$entity		  = $this->crud_conf["entity"];

		// Passing a resultset as data
		$paginator = new PaginatorModel([
		    "data"  => $resultset, //data setter
		    "limit" => $per_page,
		    "page"  => $current_page
		]);
		//page object
		$page = $paginator->getPaginate();

		//baseUrl
		$url = $this->baseUrl("$entity/list?page=");
		//limits
		$total = $resultset->count();
		$from  = $current_page == 1 ? 1 : ($per_page*$current_page - $per_page + 1);
		$to    = $from + $per_page - 1;

		if($to > $total) $to = $total;

		//create response object
		return (object)[
			"total" 	    => $total,
			"per_page" 		=> $per_page,
			"current_page"  => $page->current,
			"last_page" 	=> $page->last,
			//just for indexing in view
			"from" 			=> $from,
			"to" 			=> $to,
			//urls
			"next_page_url" => $url.$page->next,
			"prev_page_url" => $url.$page->before
		];
	}

	/**
	 * Merges payload key with data array
	 * @param  array $data - The input data
	 */
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
