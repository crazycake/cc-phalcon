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
		$di = $dispatcher->getDI();

		// handle Phalcon exceptions
		if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {

			$log_exception = false;

			switch ($exception->getCode()) {

				case \Phalcon\Dispatcher::EXCEPTION_NO_DI:
				case \Phalcon\Dispatcher::EXCEPTION_CYCLIC_ROUTING:

					$log_exception = true;
					$forward = ["controller" => "error", "action" => "internal"];
					break;

				case \Phalcon\Dispatcher::EXCEPTION_INVALID_PARAMS:
				case \Phalcon\Dispatcher::EXCEPTION_INVALID_HANDLER:

					$log_exception = true;
					$forward = ["controller" => "error", "action" => "badRequest"];
					break;

				case \Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case \Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:

					$forward = ["controller" => "error", "action" => "notFound"];
					break;
			}

			// log error?
			if ($log_exception)
				$di->getShared("logger")->error("App Exception: ".$exception->getMessage()." File: ".$exception->getFile().". Line: ".$exception->getLine());

			// forward
			$dispatcher->forward($forward);
			return false;
		}

		if (APP_ENV != "production")
			die("App Exception: ".$exception->getMessage()." File: ".$exception->getFile().". Line: ".$exception->getLine());

		$di->getShared("logger")->error("App Exception: ".$exception->getMessage());

		// handle exception and forward to internal error page
		$dispatcher->forward(["controller" => "error", "action" => "internal"]);
		return false;
	}
}
