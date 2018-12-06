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
	public static $DEFAULT_CURRENCY = "CLP";

	/**
	 * Buy Order code length
	 * @var Integer
	 */
	public static $CODE_LENGTH = 16;

	/**
	 * States possible values
	 * @var Array
	 */
	public static $STATES = ["pending", "failed", "overturn", "success"];

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
				"currency" => $data["currency"] ?? static::$DEFAULT_CURRENCY,
				"payload"  => $data["payload"] ?? null,
				"client"   => ["platform" => $this->client->platform, "browser" => $this->client->browser, "version" => $this->client->version]
			];

			// event
			$this->onBeforeBuyOrderCreation($checkout);

			// check if an error occurred
			if (!$checkout = UserCheckout::newBuyOrder($checkout)) {

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

			// get checkout & user
			$checkout = UserCheckout::getByProperties(["buyOrder" => $buyOrder]);

			if (!$checkout)
				throw new Exception("checkout not found! buy order: $buy_order");

			// skip already process for dev
			if ($checkout->state == "success")
				throw new Exception("Checkout already processed, buy order: $buy_order");

			//1) update status of checkout
			UserCheckout::updateProperties(["buyOrder" => $buyOrder], ["state" => "success"]);

			//2) call event
			$this->onSuccessCheckout($checkout);
		}
		catch (\Exception | Exception $e) {

			$this->logger->debug("CheckoutManager::successCheckout -> exception: ".$e->getMessage());

			// send alert system mail message
			(new \MailerController())->adminException($e, ["trace" => "buy_order: ".$buy_order ?? "n/a"]);
		}
	}

	/**
	 * Creates a new buy order
	 * @param Object $checkout -The checkout object
	 * @return Mixed - The checkout ORM object
	 */
	public static function newBuyOrder($checkout)
	{
		// generates buy order
		$checkout->buyOrder = self::newBuyOrderCode();
		$checkout->state    = "pending";

		// log statement
		$this->logger->debug("CheckoutManager::newBuyOrder -> saving buy order: $checkout->buyOrder");

		try {

			$checkout = UserCheckout::insert($checkout);

			$this->logger->debug("CheckoutManager::newBuyOrder -> saved checkout! buy order: $checkout->buyOrder");

			return $checkout;
		}
		catch (\Exception | Exception $e) {

			$this->logger->error("CheckoutManager::newBuyOrder -> exception: ".$e->getMessage());
			return false;
		}
	}

	/**
	 * Generates a random code for a buy order
	 * @param Int $length - The buy order string length
	 * @return String
	 */
	public static function newBuyOrderCode($length = '')
	{
		if (empty($length))
			$length = static::$CODE_LENGTH;

		$code = $this->cryptify->newAlphanumeric($length);

		$exists = UserCheckout::getByProperties(["code" => $code]);

		return $exists ? self::newBuyOrderCode($length) : $code;
	}
}
