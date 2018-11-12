<?php
/**
 * Checkout Jobs
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Checkout;

use Phalcon\Exception;

use CrazyCake\Phalcon\App;

/**
 * Checkout Jobs for CLI
 */
trait CheckoutJobs
{

	/**
	 * Initialize Trait.
	 * @param Array $conf - The config array
	 */
	public function initCheckoutJobs($conf = [])
	{
		$defaults = [
			"checkout_entity" => "user_checkout"
		];

		$conf = array_merge($defaults, $conf);

		$conf["checkout_entity"] = App::getClass($conf["checkout_entity"]);

		$this->checkout_jobs_conf = $conf;
	}

	/**
	 * Users Checkouts cleaner (for expired checkout)
	 */
	public function cleanExpiredCheckouts()
	{
		$entity = $this->checkout_jobs_conf["checkout_entity"];

		// delete pending checkouts with default expiration time
		$objs_deleted = $entity::deleteExpired();

		if ($objs_deleted)
			$this->logger->debug("CheckoutJobs::cleanExpiredCheckouts -> Expired Checkouts deleted: ".$objs_deleted);

		return $objs_deleted;
	}
}
