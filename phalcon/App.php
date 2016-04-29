<?php
/**
 * Phalcon APP loader
 * Requires PhalconPHP extension
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
    const APP_CORE_PACKAGE   = "CrazyCake\\";
    const APP_CORE_NAMESPACE = "cc-phalcon";

    /**
     * App Core default packages
     * @var array
     */
    protected static $CORE_DEFAULT_PACKAGES = ["services", "core", "helpers", "models", "account"];

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
        //set dabase configs
        $this->_databaseSetup();
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

                if(is_null($argv))
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
                if(!isset($arguments["params"]))
                    $arguments["params"] = [];

                //order params
                if(count($arguments["params"]) > 0) {
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
                if(is_callable($routes_fn))
                    $routes_fn($app);

                //Handle the request
                echo $app->handle();
                break;

            default:
                //apply a routes function if param given (must be done after object instance)
                if(is_callable($routes_fn)) {
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
                if($argv)
                    return $output;

                //Handle the request
                if(APP_ENVIRONMENT !== "local")
                    ob_start([$this,"_minifyOutput"]); //call function

                echo $output;
                break;
        }
    }

    /**
     * Extract assets inside the phar file
     * @static
     * @param string $assets_uri - The phar assets phar as URI, not absolute & must end with a slash
     * @param string $cache_path - The app cache path, must end with a slash
     * @param string $force_extract - Forces extraction not validating contents in given cache path
     * @return mixed [boolean|string] - The absolute include cache path
     */
    public static function extractAssetsFromPhar($assets_uri = null, $cache_path = null, $force_extract = false)
    {
        //check folders
        if(is_null($assets_uri) || is_null($cache_path))
            throw new Exception("App::extractAssetsFromPhar -> assets and cache path must be valid paths.");

        if(!is_dir($cache_path))
            throw new Exception("App::extractAssetsFromPhar -> cache path directory not found.");

        //check phar is running
        if(!\Phar::running())
            return false;

        //set phar assets path
        $phar_assets = dirname(__DIR__)."/".$assets_uri; //parent dir
        $output_path = $cache_path.$assets_uri;

        //check if files are already extracted
        if(!$force_extract && is_dir($output_path))
            return $output_path;

        //get files in directory & exclude ".", ".." directories
        $assets = array();
        $files  = scandir($phar_assets);
        unset($files["."], $files[".."]);

        //fill the asset array
        foreach ($files as $file)
            array_push($assets, $assets_uri.$file);

        //instance a phar file object
        $phar = new \Phar(\Phar::running());
        //extract all files in a given directory
        $phar->extractTo($cache_path, $assets, true);

        //return path
        return $output_path;
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
     * Set Database configurations
     * @access private
     */
    private function _databaseSetup()
    {
        if(empty(getenv("DB_HOST")))
            throw new Exception("App::_databaseSetup -> DB environment is not set.");

        //set database config
        $this->app_conf["database"] = [
            "host"      => getenv("DB_HOST"),
            "username"  => getenv("DB_USER"),
            "password"  => getenv("DB_PASS"),
            "dbname"    => getenv("DB_NAME")
        ];
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

        if($folders) {

            foreach ($folders as $dir) {

                $paths = explode("/", $dir, 2);

                //set directory path (if first index is a module)
                if(count($paths) > 1 && in_array($paths[0], self::$CORE_DEFAULT_MODULES))
                    $app_dirs[$dir] = PROJECT_PATH.$paths[0]."/app/".$paths[1]."/";
                else
                    $app_dirs[$dir] = APP_PATH.$dir."/";
            }
        }

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

        //2.- Register any static libs (like core)
        $this->_loadStaticLibs($loader, $core_libs);

        //3.- Composer libs auto loader
        if (!is_file(COMPOSER_PATH."vendor/autoload.php"))
            throw new Exception("App::_autoloadClasses -> Composer libraries are missing, please run environment script file.");

        //autoload composer file
        require COMPOSER_PATH."vendor/autoload.php";

        //4.- Register phalcon loader
        $loader->register();
        //var_dump(get_included_files());exit;
    }

    /**
     * Loads statics libs from sym-link or phar file.
     * Use Phar::running() to get path of current phar running
     * Use get_included_files() to see all files that has loaded
     * it seems that phalcon"s loader->registerNamespaces don"t consider phar inside paths
     * @param object $loader - Phalcon loader object
     * @param array $packages - Modules packages list
     */
    private function _loadStaticLibs($loader = null, $packages = array())
    {
        if(is_null($loader))
            return;

        if(!is_array($packages))
            $packages = [];

        //merge packages with defaults
        $packages = array_merge(self::$CORE_DEFAULT_PACKAGES, $packages);

        //check if library was loaded from dev environment
        $class_path = is_link(PACKAGES_PATH.self::APP_CORE_NAMESPACE) ? PACKAGES_PATH.self::APP_CORE_NAMESPACE : false;

        //load classes directly form phar
        if(!$class_path) {
            //get class map array
            $class_map = include "AppClassMap.php";

            foreach ($packages as $lib) {
                //loop through package files
                foreach ($class_map[$lib] as $class)
                    require \Phar::running()."/$lib/".$class;
            }
            return;
        }

        //load classes from symlink
        $namespaces = array();
        foreach ($packages as $lib) {
            $namespaces[self::APP_CORE_PACKAGE.ucfirst($lib)] = "$class_path/$lib/";
        }
        //var_dump($class_path, $namespaces);

        //register namespaces
        if(!empty($namespaces))
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
