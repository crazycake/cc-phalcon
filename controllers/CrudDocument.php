<?php
/**
 * BaseCrud Trait for apps (MongoDB).
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
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
	protected $CRUD_CONF;

	/**
	 * Database connection object
	 * @var Object
	 */
	protected $database;

	/**
	 * Database manager object
	 * @var Object
	 */
	protected $databaseManager;

	/**
	 * Initialize Trait
	 * @param Array $conf - The config array
	 */
	protected function initCrud($conf = [])
	{
		$defaults = [
			"database_uri"      => getenv("MONGO_HOST") ?: "mongodb://mongo",
			"database_name"     => getenv("MONGO_DB") ?: "app",
			"collection"        => null,
			"predictive_search" => false,
			"fetch_limit"       => 1000
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		if (empty($conf["collection"])) throw new Exception("Crud requires a collection argument.");

		// set conf
		$this->CRUD_CONF = $conf;

		// set db URI
		$this->CRUD_CONF["database_uri"] = $this->CRUD_CONF["database_uri"];

		$this->database = (new \MongoDB\Client($this->CRUD_CONF["database_uri"]))->{$this->CRUD_CONF["database_name"]};

		$this->databaseManager = new \MongoDB\Driver\Manager($this->CRUD_CONF["database_uri"]);
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
		$limit = $data["limit"] ?? $this->CRUD_CONF["fetch_limit"];

		if ($limit > $this->CRUD_CONF["fetch_limit"])
			$limit = $this->CRUD_CONF["fetch_limit"];

		// defaults
		$query = [];
		$opts  = ["limit" => intval($limit), "skip" => intval($data["skip"] ?? 0)];

		$data["search"] = rtrim(ltrim($data["search"] ?? ""));

		// sort by score relevance (full text search)
		if (!empty($data["search"])) {

			$query['$text']     = ['$search' => $data["search"]];
			$opts["projection"] = ["score" => ['$meta' => "textScore"]];
			$opts["sort"]       = ["score" => ['$meta' => "textScore"]];

			if (!empty($data["sort"]) && !empty($data["order"]))
				$opts["sort"][$data["sort"]] = intval($data["order"]);

			// search prediction props
			if (!empty($this->CRUD_CONF["predictive_search"])) {

				$query = ['$or' => [['$text' => $query['$text']]]];

				foreach ($this->CRUD_CONF["predictive_search"] as $prop)
					$query['$or'][] = [$prop => ['$regex' => $data["search"], '$options' => 'i']];
			}
		}
		else {

			// sort default
			$opts["sort"] = ["_id" => -1];

			if (!empty($data["sort"]) && !empty($data["order"]))
				$opts["sort"] = [$data["sort"] => intval($data["order"])];
		}

		// event
		if (method_exists($this, "onBeforeQuery"))
			$this->onBeforeQuery($query, $opts, $data);

		$this->logger->debug("CrudDocument::list -> query: ". json_encode($query)." => ".json_encode($opts));

		// collection
		$collection = $this->database->getDatabaseName().".".$this->CRUD_CONF["collection"];

		// query
		$items = $this->databaseManager->executeQuery($collection, new \MongoDB\Driver\Query($query, $opts))->toArray();

		// get unfiltered items
		unset($opts["limit"], $opts["skip"], $opts["sort"]);

		$totalItems = $this->database->{$this->CRUD_CONF["collection"]}->count($query, $opts);

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

		// insert
		if (is_null($object_id)) {

			try { $object = $this->database->{$this->CRUD_CONF["collection"]}->insertOne($payload); }
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

			// unset const props
			unset($payload->_id, $payload->createdAt);

			try { $this->database->{$this->CRUD_CONF["collection"]}->updateOne(["_id" => $object_id], ['$set' => $payload]); }
			catch (\Exception | Exception $e) {

				$this->logger->error("CrudDocument::saveAction -> update exception: ".$e->getMessage());

				if (method_exists($this, "onSaveException"))
					$this->onSaveException($e, $payload);
			}
		}

		// get saved object
		try { $object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $object_id]); }
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

		try { $object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => (new \MongoDB\BSON\ObjectId($id))]); }
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

		$this->database->{$this->CRUD_CONF["collection"]}->deleteOne(["_id" => $object_id]);

		// send response
		$this->jsonResponse(200);
	}

	/**
	 * Ajax - Nullify prop or pull value in array-prop
	 */
	public function pullValueAction()
	{
		$this->onlyAjax();

		$data = $this->handleRequest([
			"id"     => "string",
			"prop"   => "string",
			"@value" => "string" // optional
		], "POST");

		// event
		if (method_exists($this, "onBeforeNullifyValue"))
			$this->onBeforeNullifyValue($data);

		$object_id = new \MongoDB\BSON\ObjectID($data["id"]);
		$prop      = $data["prop"];

		$object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $object_id]);

		if (empty($object))
			$this->jsonResponse(400);

		// check if is array
		$is_array = $object->{$prop} instanceof \MongoDB\Model\BSONArray;

		// array special case?
		$cmd = $is_array ? ['$pull' => ["$prop" => $data["value"]]] : ['$set' => ["$prop" => null]];

		$this->database->{$this->CRUD_CONF["collection"]}->updateOne(["_id" => $object_id], $cmd);

		// get updated value
		$object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $object_id]);

		$this->jsonResponse(200, ["prop" => "$prop", "value" => $object->{$prop}]);
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
