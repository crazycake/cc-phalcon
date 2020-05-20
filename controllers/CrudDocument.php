<?php
/**
 * BaseCrud Trait for apps (MongoDB).
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

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
			"database_host"      => getenv("MONGO_HOST") ?: "mongodb://mongo",
			"database_name"      => getenv("MONGO_DB") ?: "app",
			"collection"         => null,
			"predictive_search"  => false,
			"fetch_limit"        => 1000,
			"fetch_default_sort" => null
		];

		// merge confs
		$conf = array_merge($defaults, $conf);

		if (empty($conf["collection"])) throw new \Exception("Crud requires a collection argument.");

		// set conf
		$this->CRUD_CONF = $conf;

		// set db URI
		$this->CRUD_CONF["database_host"] = $this->CRUD_CONF["database_host"];

		$this->database = (new \MongoDB\Client($this->CRUD_CONF["database_host"]))->{$this->CRUD_CONF["database_name"]};

		$this->databaseManager = new \MongoDB\Driver\Manager($this->CRUD_CONF["database_host"]);
	}

	/**
	 * Ajax - List Action
	 */
	public function listAction()
	{
		$this->onlyAjax();

		// parse query string & assign to data
		parse_str($this->request->get("query"), $data);

		if (empty($data)) $this->jsonResponse(404);

		// set limit, skips
		$limit = $data["limit"] ?? $this->CRUD_CONF["fetch_limit"];

		if ($limit > $this->CRUD_CONF["fetch_limit"])
			$limit = $this->CRUD_CONF["fetch_limit"];

		// defaults
		$query = [];
		$opts  = ["limit" => intval($limit), "skip" => intval($data["skip"] ?? 0)];

		$data["search"] = trim($data["search"] ?? "");

		// sort by score relevance (full text search)
		if (!empty($data["search"])) {

			$query = ['$or' => [['$text' => ['$search' => "\"".$data["search"]."\""] ]]];

			// is an ObjectId ?
			if (preg_match('/^[a-f\d]{24}$/i', $data["search"]))
				$query['$or'][] = ["_id" => new \MongoDB\BSON\ObjectId($data["search"])];

			$opts["projection"] = ["score" => ['$meta' => "textScore"]];
			$opts["sort"]       = ["score" => ['$meta' => "textScore"]];

			if (!empty($data["sort"]) && !empty($data["order"]))
				$opts["sort"][$data["sort"]] = intval($data["order"]);

			// search prediction props
			if (!empty($this->CRUD_CONF["predictive_search"])) {

				foreach ($this->CRUD_CONF["predictive_search"] as $prop)
					$query['$or'][] = [$prop => ['$regex' => $data["search"], '$options' => 'i']];
			}
		}
		else {

			// sort default
			$opts["sort"] = $this->CRUD_CONF["fetch_default_sort"] ?? ["_id" => -1];

			if (!empty($data["sort"]) && !empty($data["order"]))
				$opts["sort"] = [$data["sort"] => intval($data["order"])];
		}

		// event
		if (method_exists($this, "onBeforeQuery"))
			$this->onBeforeQuery($query, $opts, $data);

		$this->logger->debug("CrudDocument::list -> query: ".json_encode($query)." => ".json_encode($opts));

		// collection
		$collection = $this->database->getDatabaseName().".".$this->CRUD_CONF["collection"];

		// query
		$items = $this->databaseManager->executeQuery($collection, new \MongoDB\Driver\Query($query, $opts))->toArray();

		// get unfiltered items
		unset($opts["limit"], $opts["skip"], $opts["sort"]);

		$totalItems = $this->database->{$this->CRUD_CONF["collection"]}->count($query, $opts);

		// event
		if (method_exists($this, "onAfterQuery"))
			$this->onAfterQuery($items, $data);

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
		$data = $this->handleRequest(["payload" => "striptags"], "POST");

		try { $payload = \MongoDB\BSON\toPHP(\MongoDB\BSON\fromJSON($data["payload"])); }

		catch (\Exception $e) {

			$this->logger->error("CrudDocument::saveAction -> parse exception: ".$e->getMessage(). " -> ".$data["payload"]);
			$this->jsonResponse(500);
		}

		// format payload
		static::formatPayload($payload);

		// event
		if (method_exists($this, "onBeforeSave"))
			$this->onBeforeSave($payload);

		// set object id
		$id     = $payload->_id ?? null;
		$exists = $id ? $this->database->{$this->CRUD_CONF["collection"]}->count(["_id" => $id]) > 0 : false;

		// insert
		if (!$exists) {

			// createdAt timestamp
			$payload->createdAt = new \MongoDB\BSON\UTCDateTime((new \DateTime())->getTimestamp() * 1000);

			try { $object = $this->database->{$this->CRUD_CONF["collection"]}->insertOne($payload); }

			catch (\Exception $e) {

				$this->logger->error("CrudDocument::saveAction -> insert exception: ".$e->getMessage());

				if (method_exists($this, "onSaveException"))
					$this->onSaveException($e, $payload);

				$this->jsonResponse(500);
			}

			$id = $object->getInsertedId();
		}
		// update
		else {

			try { $this->database->{$this->CRUD_CONF["collection"]}->updateOne(["_id" => $id], ['$set' => $payload]); }

			catch (\Exception $e) {

				$this->logger->error("CrudDocument::saveAction -> update exception: ".$e->getMessage());

				if (method_exists($this, "onSaveException"))
					$this->onSaveException($e, $payload);
			}
		}

		// get saved object
		try                   { $object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $id]); }
		catch (\Exception $e) { $object = null; }

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

		try                   { $id = new \MongoDB\BSON\ObjectID($id); }
		catch (\Exception $e) { $id = (string)$id; }

		try                   { $object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $id]); }
		catch (\Exception $e) { $object = null; }

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

		try                   { $id = new \MongoDB\BSON\ObjectID($data["id"]); }
		catch (\Exception $e) { $id = (string)$data["id"]; }

		// event
		if (method_exists($this, "onBeforeDelete"))
			$this->onBeforeDelete($id);

		$this->database->{$this->CRUD_CONF["collection"]}->deleteOne(["_id" => $id]);

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

		try                   { $id = new \MongoDB\BSON\ObjectID($data["id"]); }
		catch (\Exception $e) { $id = (string)$data["id"]; }

		$prop = $data["prop"];

		$object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $id]);

		if (empty($object)) $this->jsonResponse(400);

		// check if is array
		$is_array = $object->{$prop} instanceof \MongoDB\Model\BSONArray;

		// array special case?
		$cmd = $is_array ? ['$pull' => ["$prop" => $data["value"]]] : ['$set' => ["$prop" => null]];

		$this->database->{$this->CRUD_CONF["collection"]}->updateOne(["_id" => $id], $cmd);

		// get updated value
		$object = $this->database->{$this->CRUD_CONF["collection"]}->findOne(["_id" => $id]);

		// event
		if (method_exists($this, "onAfterPullValue"))
			$this->onAfterPullValue($object, $data);

		$this->jsonResponse(200, ["prop" => "$prop", "value" => $object->{$prop}]);
	}

	/**
	 * Format payload properties and set correct data types
	 * @param Object $payload - The payload properties
	 */
	public static function formatPayload(&$payload)
	{
		foreach ($payload as $key => &$value) {

			if (is_numeric($value)) {

				if (strpos($value, ".") > 0 && $value < PHP_FLOAT_MAX)
					$value = floatval($value);

				else if ($value < PHP_INT_MAX)
					$value = intval($value);
			}

			else if (empty($value))
				$value = null;
		}
		//~ss($payload);
	}
}
