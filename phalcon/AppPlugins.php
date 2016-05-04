<?php
/**
 * Phalcon App plugins
 * Handles Phalcon Exceptions & Custom Filters
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

/**
 * Class for MVC module that hanldes Phalcon Exceptions.
 */
class ExceptionsPlugin extends \Phalcon\Mvc\User\Plugin
{
	/**
	 * Constructor
	 */
	public function __construct()
    {
		if (!defined("APP_ENVIRONMENT"))
            throw new Exception("ExceptionsPlugin::__construct -> APP_ENVIRONMENT is not defined.");
    }

	/**
	 * This action is executed before a exception ocurrs.
	 * @param Event $event - The Phalcon event
	 * @param Dispatcher $dispatcher - The Phalcon dispatcher
	 * @param Exception $exception - Any exception
	 * @return boolean
	 */
	public function beforeException(\Phalcon\Events\Event $event, \Phalcon\Mvc\Dispatcher $dispatcher, \Exception $exception)
	{
		//log error
		$di = $dispatcher->getDI();
		//var_dump($di->getShared("session")->getName(), $exception, $exception->getCode(), $di);exit;

		//Handle Phalcon exceptions
		if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {

			$log_exception = false;

			switch ($exception->getCode()) {
				//dispatch to not found action
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

			//log error?
			if ($log_exception)
				$di->getShared("logger")->error("App Exception: ".$exception->getMessage()." File: ".$exception->getFile().". Line: ".$exception->getLine()."</h1>");

			//forward
			$dispatcher->forward($forward);
			return false;
		}

		if (APP_ENVIRONMENT !== "production")
			die("App Exception: ".$exception->getMessage()." File: ".$exception->getFile().". Line: ".$exception->getLine());

		//log error
		$di->getShared("logger")->error("App Exception: ".$exception->getMessage());

		//Handle exception and forward to internal error page
		$dispatcher->forward(["controller" => "error", "action" => "internal"]);
		return false;
	}
}
