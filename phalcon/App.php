<?php
/**
 * Phalcon APP loader. Contains Loader classes & services wrap logic
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

use Phalcon\Exception;

//required Files
require "AppModule.php";
require "AppServices.php";

/**
 * Phalcon APP Loader [main file]
 */
abstract class App extends AppModule implements AppLoader
{
    /** const **/
    const APP_CORE_NAMESPACE = "CrazyCake\\";
    const APP_CORE_PROJECT   = "cc-phalcon";

    /**
     * App Core default libs
     * @var array
     */
    protected static $CORE_DEFAULT_LIBS = ["services", "controllers", "core", "helpers", "models", "account"];

    /**
     * The App Dependency injector
     * @var object
     */
    private $di;

    /**
     * Class loader
     */
    public function loadClasses()
    {
        //load webapp directories
        $this->_directoriesSetup();
        //load clases
        $this->_autoloadClasses();
    }

    /**
     * DI loader
     */
    public function setDI()
    {
        //set DI services (requires composer)
        $this->_setServices();
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

            case "cli":
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
                break;

            case "api":
                //new micro app
                $app = new \Phalcon\Mvc\Micro($this->di);
                //apply a routes function if param given (must be done before object instance)
                if (is_callable($routes_fn))
                    $routes_fn($app);

                //Handle the request
                echo $app->handle();
                break;

            default:
                //apply a routes function if param given (must be done after object instance)
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

                //new mvc app
                $app = new \Phalcon\Mvc\Application($this->di);
                //set output
                $output = $app->handle()->getContent();

                //return output if argv is true
                if ($argv)
                    return $output;

                //Handle the request
                if (APP_ENV !== "local")
                    ob_start([$this,"_minifyOutput"]); //call function

                echo $output;
                break;
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set App Dependency Injector
     * @access private
     */
    private function _setServices()
    {
        //get DI preset services for module
        $services = new AppServices($this);
        $this->di = $services->getDI();
    }

    /**
     * Set Directories configurations
     * @access private
     */
    private function _directoriesSetup()
    {
        $app_dirs = [
            "controllers" => APP_PATH."controllers/"
        ];

        $folders = self::getProperty("loader");

        if ($folders) {

            foreach ($folders as $dir) {

                $paths = explode("/", $dir, 2);
                //set directory path
                $app_dirs[$dir] = count($paths) > 1 ? PROJECT_PATH.$paths[0]."/app/".$paths[1]."/" : APP_PATH.$dir."/";
            }
        }
        //print_r($app_dirs); exit;

        //inverted sort
        arsort($app_dirs);
        $this->app_conf["directories"] = $app_dirs;
    }

    /**
     * Phalcon Auto Load Classes, Composer and Static Libs
     * @access private
     */
    private function _autoloadClasses()
    {
        //1.- Load app directories (components)
        $loader = new \Phalcon\Loader();
        $loader->registerDirs($this->app_conf["directories"]);

        $core_libs = self::getProperty("core");

        //2.- Register core static modules
        $this->_loadCoreLibraries($loader, $core_libs);

        //3.- Composer libs auto loader
        if (!is_file(COMPOSER_PATH."autoload.php"))
            throw new Exception("App::_autoloadClasses -> autoload composer file not found: ".COMPOSER_PATH."autoload.php");

        //autoload composer file
        require COMPOSER_PATH."autoload.php";

        //4.- Register phalcon loader
        $loader->register();
        //var_dump(get_included_files());exit;
    }

    /**
     * Loads static libraries.
     * Use Phar::running() to get path of current phar running
     * Use get_included_files() to see all loaded classes
     * @param object $loader - Phalcon loader object
     * @param array $libraries - Libraries required
     */
    private function _loadCoreLibraries($loader = null, $libraries = [])
    {
        if (is_null($loader))
            return;

        if (!is_array($libraries))
            $libraries = [];

        //merge libraries with defaults
        $libraries = array_merge(self::$CORE_DEFAULT_LIBS, $libraries);

        //check if library was loaded from dev environment
        $class_path = is_link(CORE_PATH.self::APP_CORE_PROJECT) ? CORE_PATH.self::APP_CORE_PROJECT : false;

        //load classes directly form phar file
        if (!$class_path)
            $class_path = \Phar::running();

        //set library path => namespaces
        $namespaces = [];
        foreach ($libraries as $lib) {
            $namespaces[self::APP_CORE_NAMESPACE.ucfirst($lib)] = "$class_path/$lib/";
        }
        //var_dump($class_path, $namespaces);exit;

        //register namespaces
        $loader->registerNamespaces($namespaces);
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
