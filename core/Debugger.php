<?php
/**
 * Debugger Trait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Core;

/**
 * Handles public actions for error pages
 * Requires a Frontend or Backend Module
 */
trait Debugger
{
	/**
	 * Logs database query & statements with phalcon event manager
	 * @param String $file - The log file name
	 */
	protected function dblog($file = "db.log")
	{
		// listen all the database events
		$manager = new \Phalcon\Events\Manager();
		$logger  = new \Phalcon\Logger\Adapter\File(STORAGE_PATH."logs/".$file);

		$manager->attach('db', function ($event, $connection) use ($logger) {

			// log SQL
			if ($event->getType() == 'beforeQuery') {

				$sql = $connection->getSQLStatement();
				$logger->log($sql, \Phalcon\Logger::INFO);
			}
		});

		// assign the eventsManager to the db adapter instance
		$this->db->setEventsManager($manager);
	}
}
