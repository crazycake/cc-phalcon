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
use CrazyCake\Phalcon\App;

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
    abstract protected function onAfterSave(&$object, $data, $action);

	/**
     * On Query
     * @param object $query - The Query object
     */
    abstract protected function onQuery(&$query);

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
			"pk"           => "id",         //primary key
            "entity"   	   => "",           // entity in uppercase
            "entity_lower" => "",           // entity in lowercase
            "entity_label" => "Colección",
            "new_label"    => "Nuevo",
            "dfields"  	   => [],
            "sfields"  	   => [],
			"cfields"  	   => [],
			"actions"	   => ["update", "delete"]
        ];

        //merge confs
        $conf = array_merge($defaults, $conf);

		//set default fields?
		if(empty($conf["entity"]) || empty($conf["dfields"]) || empty($conf["sfields"]))
			throw new \Exception("Crud requires entity, dfields & sfields options.");

	    //set entity in lower case
		$conf["entity_lower"] = \Phalcon\Text::uncamelize($conf["entity"]);

		//init uploader?
		if(isset($conf["uploader"]))
        	$this->initUploader($conf["uploader"]);

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

		//build query object
		$query = $this->modelsManager->createBuilder()->from($this->crud_conf["entity"]);

		//default order
		$query->orderBy($this->_fieldToPhql($this->crud_conf["pk"])." DESC");

		if(!empty($data["sort"])) {
			//parse sort data from js
			$sort  = explode("|", $data["sort"], 2);
			$order = $this->_fieldToPhql($sort[0])." ".strtoupper($sort[1]);
			//set order
			$query->orderBy($order);
			//sd($order);
		}

		//filter param for search
		if(!empty($data["filter"])) {

			//create filter syntax
			$search_fields = isset($this->crud_conf["sfields"]) ? $this->crud_conf["sfields"] : [$this->crud_conf["pk"]];

			//loop through search fields
			foreach ($search_fields as $index => $fname) {

				$condition = $this->_fieldToPhql($fname)." LIKE '%".$data["filter"]."%'";
				//s($condition);

				//1st condition
				if(empty($index)) {
					$query->where($condition);
					continue;
				}
				//append other condition
				$query->orWhere($condition);
			}
		}

		//group results
		$query->groupBy($this->_fieldToPhql($this->crud_conf["pk"]));
        //listener, on query
		$this->onQuery($query);
		//get pagination response
		$result = $this->_getPaginationData($query, $data);

		//parse data array
		if($result->data instanceof Resultset) {

	        $objects = [];
	        foreach ($result->data as $obj)
	            $objects[] = $obj->toArray();

	         $result->data = $objects;
		}

		//output json response
		$this->outputJsonResponse($result);
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
	        $object_class = $this->crud_conf["entity"];
	        $object 	  = new $object_class();

			//set empty strings as null data
			$data = array_map(function($prop) { return $prop == "" ? null : $prop; }, $data);

	        //save object
	        if(!$object->save($data))
	            throw new \Exception($object->messages(true));

			//move uploaded files? (UploaderController)
			$data["uploaded"] = $this->_moveUploadedFiles($object);

	        //call listener
	        $this->onAfterSave($object, $data, "create");

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

			//get object class
	        $object_class = $this->crud_conf["entity"];
            // get object by primary key
	        $object = $object_class::findFirst([$this->crud_conf["pk"]." = '".$data[$this->crud_conf["pk"]]."'"]);

	        //check object exists
	        if(!$object)
	            throw new \Exception("Objeto no encontrado");

			//consider only attributes from object (phalcon metadata)
			$meta_data  = $object->getModelsMetaData();
			$attributes = $meta_data->getAttributes($object);
			//unset id
			unset($attributes[$this->crud_conf["pk"]]);

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
			$new_data["uploaded"] = $this->_moveUploadedFiles($object);

	        //call listener
	        $this->onAfterSave($object, $new_data, "update");

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
			$this->crud_conf["pk"] => "string" //number or string
		], "POST");

		//get object class
        $object_class = $this->crud_conf["entity"];
        // get object by primary key
        $object = $object_class::findFirst([$this->crud_conf["pk"]." = '".$data[$this->crud_conf["pk"]]."'"]);

		//orm deletion
		if($object)
			$object->delete();

		//delete upload files?
		if(isset($this->crud_conf["uploader"])) {

			$path = Uploader::$ROOT_UPLOAD_PATH.$this->crud_conf["entity_lower"]."/".$data[$this->crud_conf["pk"]]."/";
			$this->cleanUploadFolder($path);
        }

		//send response
        $this->jsonResponse(200);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Handles builder syntax (active record)
	 * Also push joins relations in array if query will need them
	 * @param string $field - Any field name.
	 */
	private function _fieldToPhql($field = "")
	{
		$namespaces = explode(".", $field);

		// one level
		if(count($namespaces) <= 1)
			return $this->crud_conf["entity"].".".$field;

    	//two or more levels
        $levels   = count($namespaces);
        $entities = array_slice($namespaces, $levels - 2);

        //syntax is always table.field
        $field = \Phalcon\Text::camelize(current($entities)).".".end($entities);

        return $field;
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
		$entity_lower = $this->crud_conf["entity_lower"];
		$per_page 	  = (int)$data["per_page"];
		$current_page = (int)$data["page"];

		//get total records
		$total = $query->getQuery()->execute()->count();

		//baseUrl
		$url = $this->baseUrl("$entity_lower/list?page=");
		//limits
		$from   = ($current_page == 1) ? 1 : ($per_page*$current_page - $per_page + 1);
		$to     = $from + $per_page - 1;
		$last 	= ceil($total / $per_page);
		$before = ($current_page <= 1) ? 1 : $current_page - 1;
		$next   = ($current_page + 1) >= $last ? $last : $current_page + 1;

		if($to > $total) $to = $total;

		//filtered resultset
		$resultset = $query->limit($per_page, $from - 1)
                           ->getQuery()->execute();

		//create response object
		$response = (object)[
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
			"data"			=> $resultset->jsonSerialize()
		];

        if(APP_ENV == "local")
            $response->phql = $query->getPhql();

        return $response;
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

	/**
	 * Move Uploaded files with Uploaded
	 * @param object $object - The entity object
	 */
	private function _moveUploadedFiles($object)
	{
		//move uploaded files? (UploaderController)
		if(!isset($this->crud_conf["uploader"]))
			return;

		$files = $this->moveUploadedFiles($this->crud_conf["entity_lower"]."/".$object->id."/");

		if(!$files)
			return;

		//save assets path as {key}_url prop
		$data = [];
		foreach ($files as $key => $value)
			$data[strtolower($key)."_url"] = $this->baseUrl("uploads/".$value);

		//update object
         $object->update($data);

        return $files;
	}
}
