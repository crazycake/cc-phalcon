<?php
/**
 * Checkout Trait
 * This class has common actions for checkout controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
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
	 * @param object $checkout - The checkout object
	 */
	abstract public function onBeforeBuyOrderCreation(&$checkout);

	/**
	 * Listener - Success checkout Task completed
	 * @param object $checkout - The checkout object
	 */
	abstract public function onSuccessCheckout(&$checkout);

	/**
	 * trait config
	 * @var array
	 */
	public $checkout_manager_conf;

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Initialize Trait.
	 * @param array $conf - The config array
	 */
	public function initCheckoutManager($conf = [])
	{
		$defaults = [
			"async"            => true,
			"default_currency" => "CLP"
		];

		$this->checkout_manager_conf = array_merge($defaults, $conf);
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Ajax Action - Before user goes to payment gateway (or not), buy order must be generated.
	 */
	public function buyOrderAction()
	{
		//make sure is ajax request
		$this->onlyAjax();

		//get class
		$user_checkout_class = App::getClass("user_checkout");

		try {

			//get checkout object with parsed data
			$checkout = $this->newCheckout();

			//call listeners
			$this->onBeforeBuyOrderCreation($checkout);

			$checkout_orm = $user_checkout_class::newBuyOrder($checkout);

			//check if an error occurred
			if (!$checkout_orm) {

				$this->logger->error("CheckoutManager::buyOrder -> failed saving checkout: ".json_encode($checkout, JSON_UNESCAPED_SLASHES));
				throw new Exception($this->checkout_manager_conf["trans"]["ERROR_UNEXPECTED"]);
			}

			//set buy order
			$checkout->buy_order = $checkout_orm->buy_order;

			if(method_exists($this, "onAfterBuyOrderCreation"))
				$this->onAfterBuyOrderCreation($checkout);

			//send JSON response
			return $this->jsonResponse(200, $checkout);
		}
		catch (Exception $e)  { $exception = $e->getMessage(); }
		catch (\Exception $e) { $exception = $e->getMessage(); }

		//sends an error message
		$this->jsonResponse(200, $exception, "alert");
	}

	/**
	 * Method: Succesful checkout, Called when checkout was made succesfuly
	 * @param string $buy_order - The buy order
	 * @param boolean $async - Socket async request method (don't wait response)
	 * @return object Checkout
	 */
	public function successCheckout($buy_order = "", $async = true)
	{
		$this->logger->debug("CheckoutManager::successCheckout -> $buy_order, async: ".(int)$async);
		//triggers async request
		$this->coreRequest([
			"uri" 	  => "checkout/successCheckoutTask/",
			"method"  => "GET",
			"socket"  => $async,
			"encrypt" => true,
			"payload" => ["buy_order" => $buy_order]
		]);
	}

	/**
	 * Action: GET Async checkout
	 * Data is encrypted for security
	 * Logic tasks:
	 * 1) Update status del checkout
	 * 2) Call listener
	 * @param string $encrypted_data - The encrypted hash string
	 */
	public function successCheckoutTaskAction($encrypted_data = "")
	{
		try {

			$this->logger->debug("CheckoutManager::successCheckoutTask -> GET Data ".json_encode($encrypted_data));

			//decrypt data
			$data = $this->cryptify->decryptData($encrypted_data, true);

			if (empty($data) || !isset($data->buy_order))
				throw new Exception("Invalid decrypted data: ".json_encode($data));

			$this->logger->debug("CheckoutManager::successCheckoutTask -> processing buy order: ".$data->buy_order);

			//set classes
			$user_checkout_class        = App::getClass("user_checkout");
			$user_checkout_object_class = App::getClass("user_checkout_object");

			//get checkout & user
			$checkout = $user_checkout_class::findFirstByBuyOrder($data->buy_order);

			if (!$checkout)
				throw new Exception("Invalid input checkout: ".json_encode($data));

			//already process
			if(APP_ENV != "local" && $checkout->state == "success")
				throw new Exception("Checkout already processed, buy order: ".$data->buy_order);

			//1) update status of checkout
			$checkout->update(["state" => "success"]);
			//$this->logger->debug("CheckoutManager::successCheckoutTask -> checkout update message ".$checkout->messages(true));

			//reduce object
			$checkout = $checkout->reduce();
			//set objects
			$checkout->objects = $user_checkout_object_class::getCollection($checkout->buy_order);

			//2) Call listener
			$this->onSuccessCheckout($checkout, $this->checkout_manager_conf["async"]);
		}
		catch (Exception $e) {

			$this->logger->debug("CheckoutManager::successCheckoutTask -> Exception: ".$e->getMessage());

			//get mailer controller
			$mailer = App::getClass("mailer_controller");

			//send alert system mail message
			(new $mailer())->adminException($e, [
				"edata" => json_encode($checkout, JSON_UNESCAPED_SLASHES)
			]);
		}
		finally {
			//send OK response
			$this->jsonResponse(200);
		}
	}

	/**
	 * Method: Parses objects checkout & set new props by reference
	 * @param object $checkout - The checkout object
	 * @param array $data - The received form data
	 */
	public function parseCheckoutObjects(&$checkout = null, $data = [])
	{
		if (empty($checkout) || empty($data))
			return;

		if (empty($checkout->objects));
			$checkout->objects = [];

		if (empty($checkout->amount))
			$checkout->amount = 0;

		//get module class name
		$user_checkout_object_class = App::getClass("user_checkout_object");

		//computed vars
		$classes = empty($checkout->objects_classes) ? [] : $checkout->objects_classes;
		$total_q = empty($checkout->total_q) ? 0 : $checkout->total_q;

		//loop throught checkout items
		foreach ($data as $key => $q) {

			//parse properties
			$props = explode("_", $key);

			//validates checkout data has defined prefix
			if (strpos($key, "Checkout_") === false || count($props) != 3 || empty($q))
				continue;

			//get object props
			$object_class = $props[1];
			$object_id    = $props[2];

			$prefixed_object_class = "\\$object_class";

			//create object if class dont exists
			if(!class_exists($prefixed_object_class))
				$object = new \stdClass();
			else
				$object = $prefixed_object_class::getById($object_id);
			//var_dump($object_class, $object_id, $object->toArray());exit;

			//check that object is in stock (also validates object exists)
			if (isset($object->quantity) && !is_null($object->quantity) &&
				!$user_checkout_object_class::validateStock($object_class, $object_id, $q)) {

				$this->logger->error("CheckoutManager::parseCheckoutObjects -> No stock for object $object_class, ID: $object_id, Q: $q.");
				throw new Exception(str_replace("{name}", $object->name, $this->checkout_manager_conf["trans"]["ERROR_NO_STOCK"]));
			}

			//append object class
			if (!in_array($object_class, $classes))
				array_push($classes, $object_class);

			//update total Q
			$total_q += $q;

			//update amount
			if(!empty($object->price))
				$checkout->amount += $q * $object->price;

			//create new checkout object without ORM props
			$checkout_object = (new $user_checkout_object_class())->reduce();
			//props
			$checkout_object->object_class = $object_class;
			$checkout_object->object_id    = $object_id;
			$checkout_object->quantity     = $q;

			//set item in array as string or plain object
			$checkout->objects[] = $checkout_object;
		}

		//set objectsClassName
		$checkout->objects_classes = $classes;
		//update total Q
		$checkout->total_q = $total_q;

		//payload?
		if(!empty($data["payload"]))
			$checkout->payload = $data["payload"];
	}
	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * New checkout object. CSRF validation skipped.
	 * @return object
	 */
	private function newCheckout()
	{
		//get form data
		$data = $this->handleRequest([
			"gateway"   => "string",
			"@currency" => "string"
		], "POST", false);

		if(empty($data["currency"]))
			$data["currency"] = $this->checkout_manager_conf["default_currency"];

		//check user_id
		$user_id = empty($this->user_session["id"]) ? null : $this->user_session["id"];

		//create checkout object
		$checkout = (object)[
			"user_id"  => $user_id,
			"client"   => json_encode($this->client, JSON_UNESCAPED_SLASHES),
			"gateway"  => $data["gateway"],
			"currency" => $data["currency"]
		];

		//parse checkout objects
		$this->parseCheckoutObjects($checkout, $data);
		//sd($checkout);

		return $checkout;
	}
}
