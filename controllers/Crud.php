<?php
/**
 * BaseCrud Trait for Backend apps (Relational).
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//phalcon
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
	 * @param array $data - The input data
	 * @param string $action - defines the action, insert or update.
	 */
	abstract protected function onBeforeSave(&$data, $action);

	/**
	 * Event on after save
	 * @param object $object - The orm object
	 * @param array $data - The input data
	 * @param string $action - defines the action, insert or update.
	 */
	abstract protected function onAfterSave(&$object, $data, $action);

	/**
	 * On Query
	 * @param object $query - The Query object
	 */
	abstract protected function onQuery(&$query);

	/**
	 * trait config
	 * @var array
	 */
	protected $crud_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param array $conf - The config array
	 */
	protected function initCrud($conf = [])
	{
		//default configurations
		$defaults = [
			"pk"               => "id",         // primary key
			"entity"           => "",           // entity in uppercase
			"entity_lower"     => "",           // entity in lowercase
			"entity_component" => "",           // entity HTML component name
			"entity_label"     => "Colección",
			"new_label"        => "Nuevo",
			"dfields"          => [],	//data fields, head row
			"sfields"          => [],	//search fields
			"cfields"          => [],	//custom fields
			"actions"          => ["update", "delete"]
		];

		//merge confs
		$conf = array_merge($defaults, $conf);

		//set default fields?
		if(empty($conf["entity"]) || empty($conf["dfields"]) || empty($conf["sfields"]))
			throw new \Exception("Crud requires entity, dfields & sfields options.");

		//set entity in lower case
		$conf["entity_lower"] = \Phalcon\Text::uncamelize($conf["entity"]);

		if(empty($conf["entity_component"]))
			$conf["entity_component"] = $conf["entity_lower"];

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
			"@sort"     => "string",
			"@filter"   => "string",
			"@page"     => "int",
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
			$search_fields = $this->crud_conf["sfields"] ?? [$this->crud_conf["pk"]];

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
		$r = $this->_getPaginationData($query, $data);

		//optional listener
		if(method_exists($this, "onResultset"))
			$r->output->data = $this->onResultset($r->resultset);

		//output json response
		$this->outputJsonResponse($r->output);
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
		//sd($data);

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
			if(!empty($this->crud_conf["uploader"])) {

				$uri = $this->crud_conf["entity_lower"]."/".$object->{$this->crud_conf["pk"]}."/";

				$data["uploaded"] = $this->saveUploadedFiles($uri);
			}

			//call listener
			$this->onAfterSave($object, $data, "create");

			//send response
			$this->jsonResponse(200);
		}
		catch (\Exception | Exception $e) {

			$this->jsonResponse(400, $e->getMessage());
		}
	}

	/**
	 * Ajax POST action for update an Object
	 */
	public function updateAction()
	{
		$this->onlyAjax();

		$data = $this->handleRequest([
			"@payload" => ""
		], "POST");

		//merge paylod if set
		$this->_mergePayload($data);

		try {
			//call listener
			$this->onBeforeSave($data, "update");

			//get object class & get object by primary key
			$object_class = $this->crud_conf["entity"];
			$object       = $object_class::findFirst([$this->crud_conf["pk"]." = '".$data[$this->crud_conf["pk"]]."'"]);

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
			if(!empty($this->crud_conf["uploader"])) {

				$uri = $this->crud_conf["entity_lower"]."/".$object->{$this->crud_conf["pk"]}."/";

				$new_data["uploaded"] = $this->saveUploadedFiles($uri);
			}

			//append non-model data
			foreach ($data as $key => $value) {

				if(isset($new_data[$key]))
					continue;

				$new_data[$key] = $value;
			}

			//call listener
			$this->onAfterSave($object, $new_data, "update");

			//send response
			$this->jsonResponse(200);
		}
		catch (\Exception | Exception $e) {

			$this->jsonResponse(400, $e->getMessage());
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
		if($object) {

			if(method_exists($this, "onBeforeDelete"))
				$this->onBeforeDelete($object);

			$object->delete();
		}

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
							->getQuery()
							->execute();

		//create response object
		$output = (object)[
			"total"         => $total,
			"per_page"      => $per_page,
			"current_page"  => $current_page,
			"last_page"     => $last,
			"from"          => $from,
			"to"            => $to,
			//urls
			"next_page_url" => $url.$next,
			"prev_page_url" => $url.$before,
			//resultset
			"data"          => $resultset->jsonSerialize()
		];

		if(APP_ENV != "production")
			$output->phql = $query->getPhql();

		return (object)["output" => $output, "resultset" => $resultset];
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
