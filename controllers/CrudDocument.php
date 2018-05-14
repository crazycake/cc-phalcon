<?php
/**
 * BaseCrud Trait for Backend apps (MongoDB).
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//phalcon
use Phalcon\Exception;
//imports
use CrazyCake\Phalcon\App;

/**
 * Base CRUD Controller
 */
trait CrudDocument
{
	//uploader trait
	use Uploader;

	/**
	 * trait config
	 * @var Array
	 */
	protected $crud_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	protected function initCrud($conf = [])
	{
		//default configurations
		$defaults = [
			"collection"  => "",
			"fetch_limit" => 300
		];

		//merge confs
		$conf = array_merge($defaults, $conf);

		//set default fields?
		if (empty($conf["collection"]))
			throw new Exception("Crud requires a collection argument.");

		//init uploader?
		if (isset($conf["uploader"]))
			$this->initUploader($conf["uploader"]);

		//finally set conf
		$this->crud_conf = $conf;
	}

	/**
	 * Ajax - List Action
	 */
	public function listAction()
	{
		$this->onlyAjax();

		//parse query string & assign to data
		parse_str($this->request->get("query"), $data);

		if (empty($data))
			$this->jsonResponse(404);

		//set limit, skips
		$limit = $data["limit"] ?? $this->crud_conf["fetch_limit"];

		if ($limit > $this->crud_conf["fetch_limit"]) 
			$limit = $this->crud_conf["fetch_limit"];

		// defaults
		$query = [];
		$opts  = [
			"limit" => intval($limit),
			"skip"  => intval($data["skip"] ?? 0),
		];

		$data["search"] = rtrim(ltrim($data["search"]));

		//sort by score relevance (full text search)
		if (!empty($data["search"])) {

			$query['$text']     = ['$search' => $data["search"]];
			$opts["projection"] = ["score" => ['$meta' => "textScore"]];
			$opts["sort"]       = ["score" => ['$meta' => "textScore"]];
		}
		else {
			
			//sort default
			$opts["sort"] = ["_id" => -1];

			if (!empty($data["sort"]) && !empty($data["order"]))
				$opts["sort"] = [$data["sort"] => intval($data["order"])];
		}

		$this->logger->debug("CrudDocument::list -> new request: ". json_encode($query)." => ".json_encode($opts));

		//optional listener
		if (method_exists($this, "onBeforeQuery"))
			$this->onBeforeQuery($query, $opts, $data);

		// collection
		$collection = $this->mongo->getDatabaseName().".".$this->crud_conf["collection"];
		//query
		$resultset = $this->mongoManager->executeQuery($collection, new \MongoDB\Driver\Query($query, $opts));
		$items     = $resultset ? $resultset->toArray() : [];

		//get unfiltered items
		unset($opts["limit"], $opts["skip"], $opts["sort"]);
		$resultset  = $this->mongoManager->executeQuery($collection, new \MongoDB\Driver\Query($query, $opts));
		$totalItems = count($resultset ? $resultset->toArray() : []);

		//optional listener
		if (method_exists($this, "onAfterQuery"))
			$this->onAfterQuery($items);

		$response = ["items" => $items, "totalItems" => $totalItems];

		// ok response
		$this->jsonResponse(200, $response);
	}

	/**
	 * Ajax - saves a document
	 */
	public function saveAction()
	{
		$this->onlyAjax();

		//set required props to be validated
		$data    = $this->handleRequest(["payload" => "array"], "POST");
		$payload = (object)$data["payload"];
		
		//set object id
		$object_id = empty($payload->_id) ? null : new \MongoDB\BSON\ObjectID(current($payload->_id));

		//format payload
		$this->formatPayload($payload);

		//optional listener
		if (method_exists($this, "onBeforeSave"))
			$this->onBeforeSave($payload);

		//insert
		if (is_null($object_id)) {

			try { $object = $this->mongo->{$this->crud_conf["collection"]}->insertOne($payload); }
			catch(\Exception | Exception $e) {

				$this->logger->error("CrudDocument::saveAction -> insert exception: ".$e->getMessage());

				if (method_exists($this, "onSaveException"))
					$this->onSaveException($e, $payload);

				$this->jsonResponse(500);
			}

			$object_id = $object->getInsertedId();
		}
		//update
		else {
		
			foreach ($payload as $key => $value) {

				//unset reserved props
				if (in_array($key, ["_id", "createdAt"])) {

					unset($payload->{$key});
					continue;
				}

				try { $this->mongo->{$this->crud_conf["collection"]}->updateOne(["_id" => $object_id], ['$set' => ["$key" => $value]]); }
				catch(\Exception | Exception $e) {

					$this->logger->error("CrudDocument::saveAction -> update exception: ".$e->getMessage());
					
					if (method_exists($this, "onSaveException"))
						$this->onSaveException($e, $payload);
				}
			}
		}

		//get saved object
		try { $object = $this->mongo->{$this->crud_conf["collection"]}->findOne(["_id" => $object_id]); }
		catch(\Exception | Exception $e) { $object = null; }

		//auto-move uploaded files? (UploaderController)
		if (!empty($this->crud_conf["uploader"])) {

			$uri = $this->crud_conf["collection"]."/".(string)$object->_id."/";

			$payload->uploaded = $this->saveUploadedFiles($uri);
		}

		//optional listener
		if (method_exists($this, "onAfterSave"))
			$this->onAfterSave($object, $payload);

		// ok response
		$this->jsonResponse(200, $object);
	}
	
	/**
	 * Get single document
	 * @param String $id - The object ID
	 */
	public function getAction($id = "")
	{
		$this->onlyAjax();

		if (empty($id))
			$this->jsonResponse(400);

		//sanitize id
		$id = (new \Phalcon\Filter())->sanitize($id, "string");
		
		try { $object = $this->mongo->{$this->crud_conf["collection"]}->findOne(["_id" => (new \MongoDB\BSON\ObjectId($id))]); }
		catch(\Exception | Exception $e) { $object = null; }

		//optional listener
		if (method_exists($this, "onGet"))
			$this->onGet($object);

		$this->jsonResponse(200, $object);
	}

	/**
	 * Ajax - Delete doc
	 */
	public function deleteAction()
	{
		$this->onlyAjax();

		$data = $this->handleRequest(["id" => "string"], "POST");

		//set object id
		$object_id = new \MongoDB\BSON\ObjectID($data["id"]);

		//optional listener
		if (method_exists($this, "onBeforeDelete"))
			$this->onBeforeDelete($data);

		$this->mongo->{$this->crud_conf["collection"]}->deleteOne(["_id" => $object_id]);

		//delete upload files?
		if (!empty($this->crud_conf["uploader"])) {

			$path = Uploader::$ROOT_UPLOAD_PATH.$this->crud_conf["collection"]."/".$data["id"]."/";

			$this->cleanUploadFolder($path);
		}

		// ok response
		$this->jsonResponse(200);
	}

	/**
	 * Ajax - Delete Image
	 */
	public function deleteImageAction()
	{
		$this->onlyAjax();

		$data = $this->handleRequest([
			"id"   => "string",
			"prop" => "string",
			"url"  => "string",
		], "POST");

		//optional listener
		if (method_exists($this, "onBeforeDeleteImage"))
			$this->onBeforeDeleteImage($data);
		
		//set object id
		$object_id = new \MongoDB\BSON\ObjectID($data["id"]);
		$prop      = $data["prop"];

		$object = $this->mongo->{$this->crud_conf["collection"]}->findOne(["_id" => $object_id]);

		if (empty($object))
			$this->jsonResponse(400);

		//check if is array
		$is_array = $object->{$prop} instanceof \MongoDB\Model\BSONArray;

		//arrays
		$cmd = $is_array ? ['$pull' => ["$prop" => $data["url"]]] : ['$set' => ["$prop" => null]]; 

		$this->mongo->{$this->crud_conf["collection"]}->updateOne(["_id" => $object_id], $cmd);

		//get updated value
		$value = $this->mongo->{$this->crud_conf["collection"]}->findOne(["_id" => $object_id]);

		$this->jsonResponse(200, ["prop" => "$prop", "value" => $value->{$prop}]);
	}

	/**
	 * Format payload properties and set correct data types
	 * @param Object $payload - The payload properties
	 */
	protected function formatPayload(&$payload)
	{
		foreach ($payload as $key => &$value) {
			
			if (is_numeric($value))
				$value = strpos($value, ".") > 0 ? floatval($value) : intval($value);

			else if (empty($value))
				$value = null;
		}

		//always set a createdAt timestamp
		if (!isset($payload->createdAt)) 
			$payload->createdAt = new \MongoDB\BSON\UTCDateTime((new \DateTime())->getTimestamp() * 1000);
	}
}
