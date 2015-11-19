<?php
/**
 * Phalcon App plugins
 * Handles Phalcon Exceptions & Custom Filters
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

use Phalcon\Exception;

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
		if(!defined('APP_ENVIRONMENT'))
            throw new Exception("ExceptionsPlugin::__construct -> APP_ENVIRONMENT is not defined.");
    }

	/**
	 * This action is executed before a exception ocurrs.
	 * @param Event $event
	 * @param Dispatcher $dispatcher
	 * @param Exception $exception
	 * @return boolean
	 */
	public function beforeException(\Phalcon\Events\Event $event, \Phalcon\Mvc\Dispatcher $dispatcher, Exception $exception)
	{
		//log error
		$di = $dispatcher->getDI();
		//var_dump($di->getShared("session")->getName(), $exception, $exception->getCode(), $di);exit;

		//Handle Phalcon exceptions
		if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {

			$logError = false;
			switch ($exception->getCode()) {
				//dispatch to not found action
				case \Phalcon\Dispatcher::EXCEPTION_NO_DI:
				case \Phalcon\Dispatcher::EXCEPTION_CYCLIC_ROUTING:
					$logError = true;
					$forwardTo = array('controller' => 'errors', 'action' => 'internal');
					break;
				case \Phalcon\Dispatcher::EXCEPTION_INVALID_PARAMS:
				case \Phalcon\Dispatcher::EXCEPTION_INVALID_HANDLER:
					$logError = true;
					$forwardTo = array('controller' => 'errors', 'action' => 'badRequest');
					break;
				case \Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case \Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
					$forwardTo = array('controller' => 'errors', 'action' => 'notFound');
					break;
			}

			//log error?
			if($logError)
				$di->getShared('logger')->error("PhalconPHP Error -> Exception: ".$exception->getMessage());

			//forward
			$dispatcher->forward($forwardTo);
			return false;
		}

		if(APP_ENVIRONMENT !== 'production')
			die("<h1>Oops Phalcon Error (dev mode)</h1><pre>".$exception->getMessage()."</pre>");

		//log error
		$di->getShared('logger')->error("PhalconPHP Error -> Exception: ".$exception->getMessage());

		//Handle exception and forward to internal error page
		$dispatcher->forward(array('controller' => 'errors', 'action' => 'internal'));
		return false;
	}
}

/**
 * Custom Assets filter for already minified files.
 */
class MinifiedFilter implements \Phalcon\Assets\FilterInterface
{
	/**
	 * Filter for minified files
	 * @param  string $contents
	 * @return string
	 */
    public function filter($contents)
    {
        //$contents = str_replace(array("\n", "\r", " "), '', $contents);
        return $contents;
    }
}
