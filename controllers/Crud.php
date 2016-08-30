<?php
/**
 * BaseCrud Trait for Backend apps.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//phalcon
use Phalcon\Paginator\Adapter\Model as Paginator;
use Phalcon\Mvc\Model\Resultset\Simple as Resultset;
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
     * Event on before render Index
     */
    abstract protected function onBeforeRenderIndex();

    /**
     * Event on before save
     */
    abstract protected function onBeforeSave(&$data, $action);

    /**
     * Event on after save
     */
    abstract protected function onAfterSave(&$data, $action);

	/**
     * On list resultset
     * @param object $resultset - Phalcon DB resultset
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
		$conf["entity"] = \Phalcon\Text::uncamelize($conf["entity"]);

		//prepare fields data for rendering
		$dfields = []; //datatable

		//categories
		if(!isset($conf["cfields"]))
		 	$conf["cfields"] = [];

		//create fields metadata
		foreach ($conf["dfields"] as $field) {

			$obj = (object)[
				"title" 	=> current($field),
				"name" 		=> key($field),
				"sortField" => key($field)
			];

			//no sorting field?
			if(isset($field["sort"]) && $field["sort"] === false)
				unset($obj->sortField);

			//format dates
			if(in_array($obj->name, ["created_at", "date"]))
				$obj->callback = "formatDate|DD/MM/YY";
			else if(in_array($obj->name, ["datetime"]))
				$obj->callback = "formatDate|DD/MM/YY HH:mm:ss";

			//save categories and set format callback
			if(!empty($field["format"])) {

				//set object callback
				$obj->callback = "formatCategory|".json_encode($field["format"], JSON_UNESCAPED_SLASHES);
				//append new category
				$conf["cfields"][$obj->name] = $field["format"];
			}

			$dfields[] = $obj;
		}

		//fields filter
		$conf["dfields"] = $dfields;

		//append actions
		if($conf["actions"])
			array_push($conf["dfields"], ["name" => "__actions", "dataClass" => "text-center"]);

		//append fetch url
		$conf["fetch_url"] = $this->baseUrl($conf["entity"]."/list");

		//init uploader?
		if(isset($conf["uploader"])) {

			$conf["uploader"]["entity"] = $conf["entity"];
        	$this->initUploader($conf["uploader"]);
		}

		//finally set conf
        $this->crud_conf = $conf;
    }

    /**
     * View - index
     */
    public function indexAction()
    {
		//set layout
		$this->view->setLayout("crud");
		$this->view->pick("crud/index");

		//listener
		$this->onBeforeRenderIndex();

		//set current_view
		$this->view->setVars($this->crud_conf);

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
		//build query object
		$query = $class_name::query();

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
		foreach ($this->crud_conf["joins"] as $join) {

			//check for an alias
			$namespace = explode("@", $join);
			$model 	   = $namespace[0];

			if(count($namespace) > 1) {

				$alias  	 = $namespace[1];
				$alias_camel = \Phalcon\Text::camelize($alias);
				//set join
				$query->join($model, "$alias_camel.id = $class_name.".$alias."_id", $alias_camel);
			}
			else {
				$query->join($model);
			}
		}

		//get pagination response
		$response = $this->_getPaginationData($query, $data);

		//listener
		$this->onListResultset($response->data);

		//parse data array
		if($response->data instanceof Resultset) {

	        $objects = [];
	        foreach ($response->data as $obj)
	            $objects[] = $obj->toArray();

	         $response->data = $objects;
		}

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
		//print_r($data);exit;

        try {
            //call listener
            $this->onBeforeSave($data, "create");

			//new object
	        $object_class = AppModule::getClass($this->crud_conf["entity"]);
	        $object 	  = new $object_class();

			//set empty strings as null data
			$data = array_map(function($prop) { return $prop == "" ? null : $prop; }, $data);

	        //save object
	        if(!$object->save($data))
	            throw new \Exception($object->messages(true));

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

			//set empty strings as null data
			$new_data = array_map(function($prop) { return $prop == "" ? null : $prop; }, $new_data);

			//update values
			if(!$object->update($new_data))
				throw new \Exception($object->messages(true));

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
	 * Also push joins relations in array if query will need them
	 * @param object $query  - The query builder object
	 * @param string $syntax - Any query syntax.
	 */
	private function _handleBuilderSyntax(&$query, $syntax = "")
	{
		$class_name = AppModule::getClass($this->crud_conf["entity"], false);
		$prefix     = $class_name.".";
		$entities   = explode(".", $syntax);

		//cross data entity?
		if(count($entities) < 2)
			return $prefix.$syntax;

		//prepare join data
		$model = \Phalcon\Text::camelize($entities[0]);

		//check for any alias
		$alias = explode("_", $entities[0]);
		//struct for alias
		if(count($alias) > 1)
			$model = \Phalcon\Text::camelize($alias[0])."@".$entities[0];

		if(!in_array($model, $this->crud_conf["joins"]))
			$this->crud_conf["joins"][] = $model;

		//caso especial para inner joins
		$syntax = \Phalcon\Text::camelize($syntax);

		return $syntax;
	}

	/**
	 * Get Pagination Data.
	 * NOTE: Se descarto el paginator de Phalcon (en favor de EagerLoding + afterFetch).
	 * @param  object $query - The query builder object
	 * @param  array $data - A data array
	 * @return stdClass object
	 */
	private function _getPaginationData($query, $data)
	{
		$entity		  = $this->crud_conf["entity"];
		$per_page 	  = (int)$data["per_page"];
		$current_page = (int)$data["page"];

		//get total records
		$total = $query->execute()->count();

		//baseUrl
		$url = $this->baseUrl("$entity/list?page=");
		//limits
		$from   = ($current_page == 1) ? 1 : ($per_page*$current_page - $per_page + 1);
		$to     = $from + $per_page - 1;
		$last 	= ceil($total / $per_page);
		$before = ($current_page <= 1) ? 1 : $current_page - 1;
		$next   = ($current_page + 1) >= $last ? $last : $current_page + 1;

		if($to > $total) $to = $total;

		//filtered resultset
		$resultset = $query->limit($per_page, $from - 1)->execute();

		//create response object
		return (object)[
			"total" 	    => $total,
			"per_page" 		=> $per_page,
			"current_page"  => $current_page,
			"last_page" 	=> $last,
			"from" 			=> $from,
			"to" 			=> $to,
			//urls
			"next_page_url" => $url.$next,
			"prev_page_url" => $url.$before,
			//resultset
			"data"			=> $resultset
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
