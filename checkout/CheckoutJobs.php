<?php
/**
 * Checkout Jobs
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\App;

/**
 * Checkout Jobs for CLI
 */
trait CheckoutJobs
{
	/**
	 * Implementation required
	 */
	abstract protected function storeDollarChileanPesoValue();

	/**
	 * Initializer
	 */
	protected function initCheckoutJobs()
	{
		if (MODULE_NAME != "cli")
			return;

		$this->colorize("userCheckoutCleaner: Cleans expired user checkouts", "WARNING");
		$this->colorize("storeDollarChileanPesoValue: API request & stores dollar CLP conversion value in Redis.", "WARNING");
	}

	/**
	 * CLI - Users Checkouts cleaner (for expired checkout)
	 */
	public function userCheckoutCleanerAction()
	{
		$user_checkout_class = App::getClass("user_checkout");

		//delete pending checkouts with default expiration time
		$objs_deleted = $user_checkout_class::deleteExpired();

		//rows affected
		if ($objs_deleted) {

			$output = "[".date("d-m-Y H:i:s")."] userCheckoutCleaner -> Expired Checkouts deleted: ".$objs_deleted."\n";
			$this->output($output);
		}
	}

	/**
	 * CLI - Saves in Redis currency CLP - USD value conversion
	 */
	public function storeDollarChileanPesoValueAction()
	{
		try {
			//checkout currency
			$output = $this->storeDollarChileanPesoValue();
			//print output
			$this->output($output);
		}
		catch (Exception $e) {

			$this->logger->error("CheckoutJob::storeChileanPesoToDollarConversion -> failed retrieving API data. Err: ".$e->getMessage());
		}
	}
}
