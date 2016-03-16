<?php
/**
 * Phalcon APP loader
 * Requires PhalconPHP extension
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Phalcon;

use Phalcon\Exception;
//required Files
require "AppServices.php";

/**
 * Phalcon APP Loader [main file]
 */
abstract class AppLoader
{
    /** const **/
    const APP_CORE_PACKAGE   = "CrazyCake\\";
    const APP_CORE_NAMESPACE = "cc-phalcon";

    /**
     * Set App config (required)
     * @return array
     */
    abstract protected function config();

    /**
     * App Core default packages
     * @var array
     */
    protected static $CORE_DEFAULT_PACKAGES = ['services', 'core', 'utils', 'models', 'account'];

    /**
     * App Core default modules
     * @var array
     */
    protected static $CORE_DEFAULT_MODULES = ['cli', 'api', 'backend', 'frontend'];

    /**
     * Modules Config
     * @var array
     */
    public static $modules_conf;

    /**
     * The App configuration array
     * @var array
     */
    public $app_conf;

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
        //modules config
        self::$modules_conf = $config["modules"];

        //validations
        if(empty($mod_name) || empty($config))
            throw new Exception("AppLoader::__construct -> invalid input module, check setup.");

        //define APP contants
        define("PROJECT_PATH", $config["projectPath"]);
        define("PACKAGES_PATH", PROJECT_PATH."packages/");
        define("COMPOSER_PATH", PACKAGES_PATH."composer/");
        define("MODULE_NAME", $mod_name);
        define("MODULE_PATH", PROJECT_PATH.MODULE_NAME."/");
        define("APP_PATH", MODULE_PATH."app/" );
        define("PUBLIC_PATH", MODULE_PATH."public/");
        define("EXEC_START", microtime(true));  //for debugging render time

        //start webapp loader flux
        $this->_directoriesSetup(); //required for autoload classes
        $this->_autoloadClasses();
        //set environment setup
        $this->_environmentSetUp(); //set APP_ENVIRONMENT & APP_BASE_URL (requires composer)
        //set phalcon DI services
        $this->_setAppDependencyInjector($config["app"]);
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

        if(MODULE_NAME == "cli") {
            //new cli app
            $application = new \Phalcon\CLI\Console($this->di);
            //loop through args
            $arguments = array();

            if(is_null($argv))
                die("Phalcon Console -> no args supplied\n");

            //set args data
            foreach ($argv as $k => $arg) {
                switch ($k) {
                    case 0: break;
                    case 1: $arguments['task']        = $arg; break;
                    case 2: $arguments['action']      = $arg; break;
                    default: $arguments['params'][$k] = $arg; break;
                }
            }

            //checks that array param was set
            if(!isset($arguments['params']))
                $arguments['params'] = array();

            //order params
            if(count($arguments['params']) > 0) {
                $params = array_values($arguments['params']);
                $arguments['params'] = $params;
            }

            //define global constants for the current task and action
            define('CLI_TASK',   isset($argv[1]) ? $argv[1] : null);
            define('CLI_ACTION', isset($argv[2]) ? $argv[2] : null);

            //handle incoming arguments
            $application->handle($arguments);
        }
        else if(MODULE_NAME == "api") {

            //new micro app
            $application = new \Phalcon\Mvc\Micro($this->di);
            //apply a routes function if param given (must be done before object instance)
            if(is_callable($routes_fn))
                $routes_fn($application);

            //Handle the request
            echo $application->handle();
        }
        else {

            //apply a routes function if param given (must be done after object instance)
            if(is_callable($routes_fn)) {
                //creates a router object (for use custom URL behavior use 'false' param)
                $router = new \Phalcon\Mvc\Router();
                //Remove trailing slashes automatically
                $router->removeExtraSlashes(true);
                //apply a routes function
                $routes_fn($router);
                //Register the router in the DI
                $this->di->set('router', function() use (&$router) {
                    return $router;
                });
            }

            //new mvc app
            $application = new \Phalcon\Mvc\Application($this->di);
            $output      = $application->handle()->getContent();

            //return output if argv is true
            if($argv)
                return $output;

            //Handle the request
            if(APP_ENVIRONMENT !== 'local')
                ob_start(array($this,"_minifyHTML")); //call function

            echo $output;
        }
    }

    /**
     * Get a module URL from current environment
     * For production use defined URIS, for dev local folders path
     * and for staging or testing URI replacement
     * @static
     * @param  string $module - The module name
     * @param  string $uri - A uri to be appended
     * @param  string $type - The url path type: 'base' or 'static'
     * @return string
     */
    public static function getModuleUrl($module = "", $uri = "", $type = "base")
    {
        if(APP_ENVIRONMENT === "production") {

            //production
            $baseUrl   = self::getModuleConfigProp("baseUrl", $module);
            $staticUrl = self::getModuleConfigProp("staticUrl", $module);
            //set URL
            $url = ($type == "static" && $staticUrl) ? $staticUrl : $baseUrl;
        }
        else if(APP_ENVIRONMENT === "local") {

            $url = str_replace(['/api/', '/frontend/', '/backend/'], "/$module/", APP_BASE_URL);
        }
        else {

            $url = str_replace(['.api.', '.frontend.', '.backend.'], ".$module.", APP_BASE_URL);
        }

        return $url.$uri;
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
            throw new Exception("AppLoader::extractAssetsFromPhar -> assets and cache path must be valid paths.");

        if(!is_dir($cache_path))
            throw new Exception("AppLoader::extractAssetsFromPhar -> cache path directory not found.");

        //check phar is running
        if(!\Phar::running())
            return false;

        //set phar assets path
        $phar_assets = dirname(__DIR__)."/".$assets_uri; //parent dir
        $output_path = $cache_path.$assets_uri;

        //check if files are already extracted
        if(!$force_extract && is_dir($output_path))
            return $output_path;

        //get files in directory & exclude '.', '..' directories
        $assets = array();
        $files  = scandir($phar_assets);
        unset($files['.'], $files['..']);

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

    /**
     * Gets current Module property value
     * @static
     * @param  string $prop - A input property
     * @param  string $mod_name - The module name
     * @return mixed
     */
    public static function getModuleConfigProp($prop = "", $mod_name = "")
    {
        $module = empty($mod_name) ? MODULE_NAME : $mod_name;

        if(!isset(self::$modules_conf[$module]))
            return false;

        if(!isset(self::$modules_conf[$module][$prop]))
            return false;

        return self::$modules_conf[$module][$prop];
    }
    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set App Dependency Injector
     * @access private
     * @param array $app_di - The DI app properties
     */
    private function _setAppDependencyInjector($app_di = array())
    {
        //set dabase configs
        $this->_databaseSetup();

        //langs
        $app_di['langs'] = self::getModuleConfigProp("langs");

        //set app AWS S3 bucket
        if(isset($app_di['aws']['s3Bucket']))
            $app_di['aws']['s3Bucket'] .= (APP_ENVIRONMENT === 'production') ? '-prod' : "-dev";

        //finally, set app properties
        $this->app_conf["app"] = $app_di;

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
        if(empty(getenv('DB_HOST')))
            throw new Exception("AppLoader::_databaseSetup -> DB environment is not set.");

        //set database config
        $this->app_conf["database"] = [
            'host'      => getenv('DB_HOST'),
            'username'  => getenv('DB_USER'),
            'password'  => getenv('DB_PASS'),
            'dbname'    => getenv('DB_NAME')
        ];
    }

    /**
     * Set Directories configurations
     * @access private
     */
    private function _directoriesSetup()
    {
        $app_dirs = [
            "controllers" => APP_PATH.'controllers/'
        ];

        $folders = self::getModuleConfigProp("loader");

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

        $core_libs = self::getModuleConfigProp("core");

        //2.- Register any static libs (like core)
        if($core_libs)
            $this->_loadStaticLibs($loader, $core_libs);

        //3.- Composer libs auto loader
        if (!is_file(COMPOSER_PATH.'vendor/autoload.php'))
            throw new Exception("AppLoader::_autoloadClasses -> Composer libraries are missing, please run environment script file.");

        //autoload composer file
        require COMPOSER_PATH.'vendor/autoload.php';

        //4.- Register phalcon loader
        $loader->register();
        //var_dump(get_included_files());exit;
    }

    /**
     * Loads statics libs from sym-link or phar file.
     * Use Phar::running() to get path of current phar running
     * Use get_included_files() to see all files that has loaded
     * it seems that phalcon's loader->registerNamespaces don't consider phar inside paths
     * @param object $loader - Phalcon loader object
     * @param array $packages - Modules packages list
     */
    private function _loadStaticLibs($loader = null, $packages = array())
    {
        if(is_null($loader))
            return;

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
     * Set Environment properties
     * @access private
     */
    private function _environmentSetUp()
    {
        //load .env file configuration with Dotenv
        $envfile = PROJECT_PATH.".env";
        $dotenv  = new \Dotenv\Dotenv(PROJECT_PATH);

        if(is_file($envfile))
            $dotenv->load();

        //SET APP_ENVIRONMENT
        if(getenv('APP_DEBUG')) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }
        else {
            ini_set('display_errors', 0);
            error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
        }

        //set default environment
        $app_base_url    = PROJECT_PATH;
        $app_environment = getenv('APP_ENV');

        //Check for CLI execution & CGI execution
        if (php_sapi_name() !== 'cli') {

            if(!isset($_REQUEST))
                throw new Exception("AppLoader -> Missing REQUEST data: ".json_encode($_SERVER)." && ".json_encode($_REQUEST));

            //set localhost if host is not set
            if(!isset($_SERVER['HTTP_HOST']))
                $_SERVER['HTTP_HOST'] = "127.0.0.1";

            $app_base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://").
                              $_SERVER['HTTP_HOST'].preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME'])).'/';
        }

        //set environment consts & self vars
        define("APP_ENVIRONMENT", $app_environment);
        define("APP_BASE_URL", $app_base_url);
        //var_dump(APP_ENVIRONMENT, APP_BASE_URL);exit;
    }

    /**
     * Minifies HTML output
     * @param string $buffer - The input buffer
     */
     private function _minifyHTML($buffer)
     {
        $search  = array('/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s');
        $replace = array('>','<','\\1');

        if (preg_match("/\<html/i",$buffer) == 1 && preg_match("/\<\/html\>/i",$buffer) == 1)
            $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
     }
}
