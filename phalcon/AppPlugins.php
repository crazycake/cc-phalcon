<?php
/**
 * Phalcon App plugins
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

use \Phalcon\Mvc\User\Plugin;
use \Phalcon\Events\Event;
use \Phalcon\Dispatcher;
use \Phalcon\Mvc\Dispatcher\Exception as DispatcherException;
use \Phalcon\Mvc\Dispatcher as MvcDispatcher;

/**
 * Class for MVC module that handles 404 (Not Found) app routes
 */
class Route404Plugin extends Plugin
{
	/**
	 * Constructor
	 */
	public function __construct()
    {
		if(!defined('APP_ENVIRONMENT'))
            throw new Exception("Route404Plugin::__construct -> APP_ENVIRONMENT is not defined.");
    }

	/**
	 * This action is executed before execute any action in the application
	 * @param Event $event
	 * @param Dispatcher $dispatcher
	 * @param Exception $exception
	 * @return boolean
	 */
	public function beforeException(Event $event, MvcDispatcher $dispatcher, Exception $exception)
	{
		//Handle 404 exceptions
		if ($exception instanceof DispatcherException) {
			
			switch ($exception->getCode()) {
				//dispatch to not found action
				case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
					$dispatcher->forward( array(
						'controller' => 'errors',
						'action' 	 => 'notFound'
					));
					return false;
			}
		}

		//log error
		$di = $dispatcher->getDI();
		//check logger service exists
		if(!is_null($di->get('logger')))
			$di->get('logger')->error("PhalconPHP Error -> Exception: ".$exception->getMessage());

		if(APP_ENVIRONMENT !== 'production')
			die("<h1>Oops Phalcon Error (dev mode)</h1><pre>".$exception->getMessage()."</pre>");

		//Handle exception and foward to internal error page
		$dispatcher->forward( array(
			'controller' => 'errors',
			'action'     => 'internal'
		));

		return false;
	}
}
