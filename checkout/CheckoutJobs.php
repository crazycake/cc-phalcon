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
	 * Initializer
	 */
	protected function initCheckoutJobs()
	{
		if (MODULE_NAME != "cli")
			return;

		$this->colorize("userCheckoutCleaner: Cleans expired user checkouts", "WARNING");
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
		if ($objs_deleted)
			$this->logger->info("CheckoutJobs::userCheckoutCleaner -> Expired Checkouts deleted: ".$objs_deleted);

		return $objs_deleted;
	}
}
