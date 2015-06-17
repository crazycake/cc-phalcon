<?php
/**
 * Phalcon App plugins
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

/**
 * Class for MVC module that handles 404 (Not Found) app routes
 */
class Route404Plugin extends \Phalcon\Mvc\User\Plugin
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
	public function beforeException(\Phalcon\Events\Event $event, \Phalcon\Mvc\Dispatcher $dispatcher, Exception $exception)
	{
		//Handle 404 exceptions
		if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {
			
			switch ($exception->getCode()) {
				//dispatch to not found action
				case \Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case \Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
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

/**
 * Custom Assets filter for already minified files
 */
class minifiedFilter implements \Phalcon\Assets\FilterInterface
{
    public function filter($contents)
    {
        //$contents = str_replace(array("\n", "\r", " "), '', $contents);
        return $contents;
    }
}

