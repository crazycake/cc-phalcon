<?php
/**
 * BaseCrud Trait for Backend apps (MongoDB).
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

use Phalcon\Exception;

use CrazyCake\Phalcon\App;

/**
 * Base CRUD Document
 */
trait CrudDocument
{
	/**
	 * trait config
	 * @var Array
	 */
	protected $crud_conf;

	/**
	 * Database connection object
	 * @var Object
	 */
	protected $database;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	protected function initCrud($conf = [])
	{
		$defaults = [
			"database_uri"  => null,
			"database_name" => null,
			"collection"    => null,
			"fetch_limit"   => 1000
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		if (empty($conf["collection"]))
			throw new Exception("Crud requires a collection argument.");

		// set conf
		$this->crud_conf = $conf;

		// set db
		$uri = getenv("MONGO_HOST") ? str_replace("~", "=", getenv("MONGO_HOST")) : "mongodb://mongo";

		if (!empty($this->crud_conf["database_uri"]))
			$uri = str_replace("~", "=", $this->crud_conf["database_uri"]);

		$database = $this->crud_conf["database_name"] ?: (getenv("MONGO_DB") ?: "app");

		$this->database = (new \MongoDB\Client($uri))->{$database};
	}

	/**
	 * Ajax - List Action
	 */
	public function listAction()
	{
		$this->onlyAjax();

		// parse query string & assign to data
		parse_str($this->request->get("query"), $data);

		if (empty($data))
			$this->jsonResponse(404);

		// set limit, skips
		$limit = $data["limit"] ?? $this->crud_conf["fetch_limit"];

		if ($limit > $this->crud_conf["fetch_limit"])
			$limit = $this->crud_conf["fetch_limit"];

		// defaults
		$query = [];
		$opts  = [
			"limit" => intval($limit),
			"skip"  => intval($data["skip"] ?? 0),
		];

		$data["search"] = rtrim(ltrim($data["search"] ?? ""));

		// sort by score relevance (full text search)
		if (!empty($data["search"])) {

			$query['$text']     = ['$search' => $data["search"]];
			$opts["projection"] = ["score" => ['$meta' => "textScore"]];
			$opts["sort"]       = ["score" => ['$meta' => "textScore"]];
		}
		else {

			// sort default
			$opts["sort"] = ["_id" => -1];

			if (!empty($data["sort"]) && !empty($data["order"]))
				$opts["sort"] = [$data["sort"] => intval($data["order"])];
		}

		$this->logger->debug("CrudDocument::list -> new request: ". json_encode($query)." => ".json_encode($opts));

		// event
		if (method_exists($this, "onBeforeQuery"))
			$this->onBeforeQuery($query, $opts, $data);

		// collection
		$collection = $this->database->getDatabaseName().".".$this->crud_conf["collection"];
		// query
		$resultset = $this->mongoManager->executeQuery($collection, new \MongoDB\Driver\Query($query, $opts));
		$items     = $resultset ? $resultset->toArray() : [];

		// get unfiltered items
		unset($opts["limit"], $opts["skip"], $opts["sort"]);
		$resultset  = $this->mongoManager->executeQuery($collection, new \MongoDB\Driver\Query($query, $opts));
		$totalItems = count($resultset ? $resultset->toArray() : []);

		// event
		if (method_exists($this, "onAfterQuery"))
			$this->onAfterQuery($items);

		$response = ["items" => $items, "totalItems" => $totalItems];

		// send response
		$this->jsonResponse(200, $response);
	}

	/**
	 * Ajax - saves a document
	 */
	public function saveAction()
	{
		$this->onlyAjax();

		// set required props to be validated
		$data    = $this->handleRequest(["payload" => "striptags"], "POST");
		$payload = json_decode($data["payload"]);

		// set object id
		$object_id = empty($payload->_id) ? null : new \MongoDB\BSON\ObjectID(current($payload->_id));

		// format payload
		$this->formatPayload($payload);

		// event
		if (method_exists($this, "onBeforeSave"))
			$this->onBeforeSave($payload);

		//insert
		if (is_null($object_id)) {

			try { $object = $this->database->{$this->crud_conf["collection"]}->insertOne($payload); }
			catch (\Exception | Exception $e) {

				$this->logger->error("CrudDocument::saveAction -> insert exception: ".$e->getMessage());

				if (method_exists($this, "onSaveException"))
					$this->onSaveException($e, $payload);

				$this->jsonResponse(500);
			}

			$object_id = $object->getInsertedId();
		}
		// update
		else {

			foreach ($payload as $key => $value) {

				// unset reserved props
				if (in_array($key, ["_id", "createdAt"])) {

					unset($payload->{$key});
					continue;
				}

				try { $this->database->{$this->crud_conf["collection"]}->updateOne(["_id" => $object_id], ['$set' => ["$key" => $value]]); }
				catch (\Exception | Exception $e) {

					$this->logger->error("CrudDocument::saveAction -> update exception: ".$e->getMessage());

					if (method_exists($this, "onSaveException"))
						$this->onSaveException($e, $payload);
				}
			}
		}

		// get saved object
		try { $object = $this->database->{$this->crud_conf["collection"]}->findOne(["_id" => $object_id]); }
		catch (\Exception | Exception $e) { $object = null; }

		// event
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

		$id = (new \Phalcon\Filter())->sanitize($id, "string");

		try { $object = $this->database->{$this->crud_conf["collection"]}->findOne(["_id" => (new \MongoDB\BSON\ObjectId($id))]); }
		catch (\Exception | Exception $e) { $object = null; }

		// event
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

		$object_id = new \MongoDB\BSON\ObjectID($data["id"]);

		// event
		if (method_exists($this, "onBeforeDelete"))
			$this->onBeforeDelete($data);

		$this->database->{$this->crud_conf["collection"]}->deleteOne(["_id" => $object_id]);

		// send response
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
			"url"  => "string"
		], "POST");

		// event
		if (method_exists($this, "onBeforeDeleteImage"))
			$this->onBeforeDeleteImage($data);

		$object_id = new \MongoDB\BSON\ObjectID($data["id"]);
		$prop      = $data["prop"];

		$object = $this->database->{$this->crud_conf["collection"]}->findOne(["_id" => $object_id]);

		if (empty($object))
			$this->jsonResponse(400);

		// check if is array
		$is_array = $object->{$prop} instanceof \MongoDB\Model\BSONArray;

		// array special case?
		$cmd = $is_array ? ['$pull' => ["$prop" => $data["url"]]] : ['$set' => ["$prop" => null]];

		$this->database->{$this->crud_conf["collection"]}->updateOne(["_id" => $object_id], $cmd);

		// get updated value
		$value = $this->database->{$this->crud_conf["collection"]}->findOne(["_id" => $object_id]);

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

		// always set a createdAt timestamp
		if (!isset($payload->createdAt))
			$payload->createdAt = new \MongoDB\BSON\UTCDateTime((new \DateTime())->getTimestamp() * 1000);
	}
}
