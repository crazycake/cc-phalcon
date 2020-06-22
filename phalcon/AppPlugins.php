<?php
/**
 * Phalcon App plugins
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Phalcon;

/**
 * Class ExceptionsPlugin.
 */
class ExceptionsPlugin extends \Phalcon\Di\Injectable
{
	/**
	 * This action is executed before a exception ocurrs.
	 * @param Event $event - The Phalcon event
	 * @param Dispatcher $dispatcher - The Phalcon dispatcher
	 * @param Exception $e - The exception
	 * @return Boolean
	 */
	public function beforeException(\Phalcon\Events\Event $event, \Phalcon\Mvc\Dispatcher $dispatcher, \Exception $e)
	{
		$forward = ["controller" => "error", "action" => "internal"];
		$report  = true;

		// Handle Phalcon Exception
		if ($e instanceof \Phalcon\Mvc\Dispatcher\Exception) {

			switch ($e->getCode()) {

				case \Phalcon\Dispatcher::EXCEPTION_NO_DI:
				case \Phalcon\Dispatcher::EXCEPTION_CYCLIC_ROUTING:

					break;

				case \Phalcon\Dispatcher::EXCEPTION_INVALID_PARAMS:
				case \Phalcon\Dispatcher::EXCEPTION_INVALID_HANDLER:

					$forward["action"] = "badRequest";
					break;

				case \Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case \Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:

					$forward["action"] = "notFound";
					$report = false;
					break;
			}
		}

		// report error?
		if ($report)
			\CrazyCake\Phalcon\App::handleException(new \Exception("Phalcon Exception: '".$e->getMessage()."' ".$e->getFile()." [".$e->getLine()."]", $e->getCode()));

		// handle exception and forward to internal error page
		$dispatcher->forward($forward);
		return false;
	}
}
