<?php
/**
 * Checkout Trait
 * This class has common actions for checkout controllers
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Checkout;

use Phalcon\Exception;

use CrazyCake\Phalcon\App;

/**
 * Checkout Manager
 */
trait CheckoutManager
{
	/**
	 * Event - Before Inster a new Buy Order record
	 * @param Object $checkout - The checkout object
	 */
	abstract public function onBeforeBuyOrderCreation(&$checkout);

	/**
	 * Event - Success checkout Task completed
	 * @param Object $checkout - The checkout object
	 */
	abstract public function onSuccessCheckout($checkout);

	/**
	 * trait config
	 * @var Array
	 */
	public $CHECKOUT_MANAGER_CONF;

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

		$this->CHECKOUT_MANAGER_CONF = $conf;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Ajax Action - Before user goes to payment gateway, a buyorder is generated.
	 */
	public function buyOrderAction()
	{
		$this->onlyAjax();

		$data = $this->handleRequest(["gateway" => "string"], "POST");

		try {

			// new checkout object
			$checkout = (object)[
				"gateway"  => $data["gateway"],
				"currency" => $data["currency"] ?? $this->CHECKOUT_MANAGER_CONF["default_currency"],
				"payload"  => $data["payload"] ?? null,
				"client"   => ["platform" => $this->client->platform, "browser" => $this->client->browser, "version" => $this->client->version]
			];

			$entity = $this->CHECKOUT_MANAGER_CONF["checkout_entity"];

			// parse checkout objects
			if (method_exists($entity, "parseFormObjects"))
				$entity::parseFormObjects($checkout, $data);

			// event
			$this->onBeforeBuyOrderCreation($checkout);

			// check if an error occurred
			if (!$checkout = $entity::newBuyOrder($checkout)) {

				$this->logger->error("CheckoutManager::buyOrder -> failed saving checkout: ".json_encode($checkout, JSON_UNESCAPED_SLASHES));
				$this->jsonResponse(500);
			}

			// event
			if (method_exists($this, "onAfterBuyOrderCreation"))
				$this->onAfterBuyOrderCreation($checkout);

			// send response
			return $this->jsonResponse(200, $checkout);
		}
		catch (\Exception | Exception $e) { $this->jsonResponse(400, $e->getMessage()); }
	}

	/**
	 * Succesful checkout, call when checkout is completed succesfuly
	 * @param String $buy_order - The buy order
	 * @return Object Checkout
	 */
	public function successCheckout($buy_order = "")
	{
		$this->logger->debug("CheckoutManager::successCheckout -> processing buy order: $buy_order");

		try {
			// set classes
			$entity = $this->CHECKOUT_MANAGER_CONF["checkout_entity"];

			// get checkout & user
			$checkout = $entity::getByBuyOrder($buy_order);

			if (!$checkout)
				throw new Exception("checkout not found! buy order: ".$buy_order);

			// skip already process for dev
			if ($checkout->state == "success")
				throw new Exception("Checkout already processed, buy order: ".$buy_order);

			//1) update status of checkout
			$entity::updateState($buy_order, "success");

			//2) call event
			$this->onSuccessCheckout($checkout);
		}
		catch (\Exception | Exception $e) {

			$this->logger->debug("CheckoutManager::successCheckout -> Exception: ".$e->getMessage());

			$mailer = App::getClass("mailer_controller");

			// send alert system mail message
			(new $mailer())->adminException($e, ["trace" => "buy_order: ".$buy_order ?? "n/a"]);
		}
	}
}
