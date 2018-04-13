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
	 * Users Checkouts cleaner (for expired checkout)
	 */
	public function userCheckoutCleanExpired()
	{
		$entity = App::getClass("user_checkout");

		//delete pending checkouts with default expiration time
		$objs_deleted = $entity::deleteExpired();

		//rows affected
		if ($objs_deleted)
			$this->logger->debug("CheckoutJobs::userCheckoutCleaner -> Expired Checkouts deleted: ".$objs_deleted);

		return $objs_deleted;
	}
}
