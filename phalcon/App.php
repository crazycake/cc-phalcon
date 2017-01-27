<?php
/**
 * Phalcon APP loader. Contains Loader classes & services wrap logic
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

use Phalcon\Exception;

//required Files
require "AppLoader.php";
require "AppServices.php";

/**
 * Phalcon APP Loader [main file]
 */
abstract class App
{
    //trait
    use AppLoader;

    /**
     * The App Dependency injector
     * @var object
     */
    private $di;

    /**
     * Constructor
     * @access public
     * @param string $mod_name - The input module
     */
    public function __construct($mod_name = null)
    {
        //set app configurations
        $config = $this->config();

        //validations
        if (empty($mod_name) || empty($config))
            die("App::constructor -> a module name is required.");

        //define APP contants
        define("PROJECT_PATH", $config["path"]);
        define("MODULE_NAME", strtolower($mod_name));
        define("STORAGE_PATH", PROJECT_PATH."storage/");
        define("COMPOSER_PATH", PROJECT_PATH."vendor/");
        define("CORE_PATH", PROJECT_PATH."core/");
        define("PUBLIC_PATH", PROJECT_PATH."public/");
        define("APP_PATH", PROJECT_PATH."app/");
        define("APP_ST", microtime(true)); //for debugging render time

        //webapp directories (loader)
        $this->loadClasses($config);
        //environment (loader)
        $this->setEnvironment();
        //set DI (services)
        $this->di = (new AppServices($config))->getDI();
    }

    /**
     * Start app module execution
     * @access public
     * @param array $argv - Input arguments for CLI
     */
    public function start($argv = null)
    {
        //set routes function
        $routes_fn = is_file(APP_PATH."config/routes.php") ? include APP_PATH."config/routes.php" : null;

        switch (MODULE_NAME) {

            case "cli" : $this->_startCli($argv);      break;
            case "api" : $this->_startApi($routes_fn); break;
            default    : $this->_startMvc($routes_fn); break;
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Starts an CLI App
	 * @param array $argv - Input arguments for CLI
	 */
	private function _startCli($argv = null)
	{
		//new cli app
		$app = new \Phalcon\CLI\Console($this->di);
		//loop through args
		$arguments = [];

		if (is_null($argv))
			die("Phalcon Console -> no args supplied\n");

		//set args data
		foreach ($argv as $k => $arg) {
			switch ($k) {
				case 0: break;
				case 1: $arguments["task"]        = $arg; break;
				case 2: $arguments["action"]      = $arg; break;
				default: $arguments["params"][$k] = $arg; break;
			}
		}

		//checks that array param was set
		if (!isset($arguments["params"]))
			$arguments["params"] = [];

		//order params
		if (count($arguments["params"]) > 0) {
			$params = array_values($arguments["params"]);
			$arguments["params"] = $params;
		}

		//define global constants for the current task and action
		define("CLI_TASK",   isset($argv[1]) ? $argv[1] : null);
		define("CLI_ACTION", isset($argv[2]) ? $argv[2] : null);

		//handle incoming arguments
		$app->handle($arguments);
	}

    /**
     * Starts an API App
     * @param function $routes_fn - A routes function
     */
    private function _startApi($routes_fn = null)
    {
        //new micro app
        $app = new \Phalcon\Mvc\Micro($this->di);
        //apply a routes function if param given (must be done before object instance)
        if (is_callable($routes_fn))
            $routes_fn($app);

        //Handle the request
        echo $app->handle();
    }

    /**
     * Starts an MVC App
     * @param function $routes_fn - A routes function
     */
    private function _startMvc($routes_fn = null)
    {
        //call routes function?
        if (is_callable($routes_fn)) {
            //creates a router object (for use custom URL behavior use "false" param)
            $router = new \Phalcon\Mvc\Router();
            //Remove trailing slashes automatically
            $router->removeExtraSlashes(true);
            //apply a routes function
            $routes_fn($router);
            //Register the router in the DI
            $this->di->set("router", function() use (&$router) {
                return $router;
            });
        }

        $app = new \Phalcon\Mvc\Application($this->di);
        //set output
        $output = $app->handle()->getContent();

        //Handle the request
        if (APP_ENV != "local")
            ob_start([$this,"_minifyOutput"]); //call function

        echo $output;
    }

    /**
    * Minifies HTML output
    * @param string $buffer - The input buffer
    */
    private function _minifyOutput($buffer)
    {
        $search  = ["/\>[^\S ]+/s", "/[^\S ]+\</s", "/(\s)+/s"];
        $replace = [">","<","\\1"];

        if (preg_match("/\<html/i",$buffer) == 1 && preg_match("/\<\/html\>/i",$buffer) == 1)
        $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
    }
}
