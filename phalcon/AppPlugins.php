<?php
/**
 * Phalcon App plugins
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Phalcon;

/**
 * Class ExceptionsPlugin.
 */
class ExceptionsPlugin extends \Phalcon\Mvc\User\Plugin
{
	/**
	 * This action is executed before a exception ocurrs.
	 * @param Event $event - The Phalcon event
	 * @param Dispatcher $dispatcher - The Phalcon dispatcher
	 * @param Exception $exception - Any exception
	 * @return Boolean
	 */
	public function beforeException(\Phalcon\Events\Event $event, \Phalcon\Mvc\Dispatcher $dispatcher, \Exception $exception)
	{
		$di      = $dispatcher->getDI();
		$forward = ["controller" => "error", "action" => "internal"];
		$log     = true;

		// Handle Phalcon Exception
		if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {


			switch ($exception->getCode()) {

				case \Phalcon\Dispatcher::EXCEPTION_NO_DI:
				case \Phalcon\Dispatcher::EXCEPTION_CYCLIC_ROUTING:

					break;

				case \Phalcon\Dispatcher::EXCEPTION_INVALID_PARAMS:
				case \Phalcon\Dispatcher::EXCEPTION_INVALID_HANDLER:

					$forward["action"] = "badRequest"; break;

				case \Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case \Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:

					$forward["action"] = "notFound"; $log = false; break;
			}
		}

		// log error?
		if ($log) $di->getShared("logger")->error("App Exception: ".$exception->getMessage()." File: ".$exception->getFile().". Line: ".$exception->getLine());

		// handle exception and forward to internal error page
		$dispatcher->forward($forward);
		return false;
	}
}
