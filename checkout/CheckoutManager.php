<?php
/**
 * Checkout Trait
 * This class has common actions for checkout controllers
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\App;

/**
 * Checkout Manager
 */
trait CheckoutManager
{
	/**
	 * Listener - Before Inster a new Buy Order record
	 * @param Object $checkout - The checkout object
	 */
	abstract public function onBeforeBuyOrderCreation(&$checkout);

	/**
	 * Listener - Success checkout Task completed
	 * @param Object $checkout - The checkout object
	 */
	abstract public function onSuccessCheckout(&$checkout);

	/**
	 * Form data prefix
	 * @var String
	 */
	public static $FORM_DATA_PREFIX = "Checkout_";

		/**
	 * trait config
	 * @var Array
	 */
	public $checkout_manager_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait.
	 * @param Array $conf - The config array
	 */
	public function initCheckoutManager($conf = [])
	{
		$defaults = [
			"checkout_entity"  => "user_checkout",
			"default_currency" => "CLP"
		];

		$conf = array_merge($defaults, $conf);

		$conf["checkout_entity"] = App::getClass($conf["checkout_entity"]);

		$this->checkout_manager_conf = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Ajax Action - Before user goes to payment gateway, a buyorder is generated.
	 */
	public function buyOrderAction()
	{
		//make sure is ajax request
		$this->onlyAjax();

		//get class
		$entity = $this->checkout_manager_conf["checkout_entity"];

		try {

			//get checkout object with parsed data
			$checkout = $this->newCheckoutObject();

			//call listeners
			$this->onBeforeBuyOrderCreation($checkout);

			$checkout_orm = $entity::newBuyOrder($checkout);

			//check if an error occurred
			if (!$checkout_orm) {

				$this->logger->error("CheckoutManager::buyOrder -> failed saving checkout: ".json_encode($checkout, JSON_UNESCAPED_SLASHES));
				$this->jsonResponse(500);
			}

			//set buy order
			$checkout->buy_order = $checkout_orm->buy_order;

			if(method_exists($this, "onAfterBuyOrderCreation"))
				$this->onAfterBuyOrderCreation($checkout);

			//send JSON response
			return $this->jsonResponse(200, $checkout);
		}
		catch (\Exception | Exception $e) { $exception = $e->getMessage(); }

		//sends an error message
		$this->jsonResponse(400, $exception);
	}

	/**
	 * Succesful checkout, call when checkout is completed succesfuly
	 * @param String $buy_order - The buy order
	 * @return Object Checkout
	 */
	public function successCheckout($buy_order = "")
	{
		$this->logger->debug("CheckoutManager::successCheckout -> processing buy order: ". $buy_order);

		try {
			//set classes
			$entity = $this->checkout_manager_conf["checkout_entity"];

			//get checkout & user
			$checkout = $entity::getByBuyOrder($buy_order);

			if (!$checkout)
				throw new Exception("checkout not found! buy order: ".$buy_order);

			//skip already process for dev
			if($checkout->state == "success")
				throw new Exception("Checkout already processed, buy order: ".$buy_order);

			//1) update status of checkout
			$checkout->update(["state" => "success"]);

			//reduce object
			$checkout = $checkout->reduce();
			//set objects
			$checkout->objects = $entity::getObjects($buy_order);

			//2) Call listener
			$this->onSuccessCheckout($checkout);
		}
		catch (\Exception | Exception $e) {

			$this->logger->debug("CheckoutManager::successCheckout -> Exception: ".$e->getMessage());

			//get mailer controller
			$mailer = App::getClass("mailer_controller");

			//send alert system mail message
			(new $mailer())->adminException($e, ["edata" => "buy_order: ".$buy_order ?? "n/a"]);
		}
	}

	/**
	 * Method: Parses objects checkout & set new props by reference (validator & parser)
	 * @param Object $checkout - The checkout object
	 * @param Array $data - The received form data
	 */
	public function parseCheckoutObjects(&$checkout = null, $data = [])
	{

		$checkout->objects = [];
		$checkout->amount  = 0;

		$classes = [];
		$total_q = 0;

		//loop throught checkout items
		foreach ($data as $key => $q) {

			//parse properties
			$props = explode("_", $key);

			//validates checkout data has defined prefix
			if (strpos($key, static::$FORM_DATA_PREFIX) === false || count($props) != 3 || empty($q))
				continue;

			//get object props
			$object_class = $props[1];
			$object_id    = $props[2];

			//create object if class dont exists
			$object = class_exists($object_class) ? $object_class::getById($object_id) : null;

			//append object class
			if (!in_array($object_class, $classes))
				$classes[] = $object_class;

			//update total Q
			$total_q += $q;

			//update amount
			if(!empty($object->price))
				$checkout->amount += $q * $object->price;

			//create new checkout object without ORM props
			$checkout_object = (object)[
				"object_class" => $object_class,
				"object_id"    => $object_id,
				"quantity"     => $q,
			];

			//set item in array as string or plain object
			$checkout->objects[] = $checkout_object;
		}

		//set objects class name
		$checkout->objects_classes = $classes;
		//update total Q
		$checkout->total_q = $total_q;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * New checkout object. CSRF validation skipped.
	 * @return Object
	 */
	private function newCheckoutObject()
	{
		//get form data
		$data = $this->handleRequest([
			"gateway" => "string"
		], "POST", false);

		// take only some props of object client
		$client = json_decode(json_encode($this->client), true);
		
		unset($client["shortVersion"], $client["isLegacy"], $client["requestedUri"], $client["token"], $client["tokenKey"]);

		//create checkout object
		$checkout = (object)[
			"user_id"  => $this->user_session["id"] ?? null,
			"gateway"  => $data["gateway"],
			"currency" => $data["currency"] ?? $this->checkout_manager_conf["default_currency"],
			"payload"  => $data["payload"] ?? null,
			"client"   => json_encode($client, JSON_UNESCAPED_SLASHES)
		];

		//parse checkout objects
		$this->parseCheckoutObjects($checkout, $data);

		return $checkout;
	}
}
