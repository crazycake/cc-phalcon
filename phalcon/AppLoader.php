<?php
/**
 * Phalcon Project Environment configuration file.
 * Requires PhalconPHP installed
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

//imports
require_once "AppServices.php";

abstract class AppLoader
{
    /** const **/
    const CCLIBS_PACKAGE          = "CrazyCake\\";
    const CCLIBS_NAMESPACE        = "cc-phalcon";
    const CCLIBS_DEFAULT_PACKAGES = ['services', 'core', 'utils', 'models'];

    /**
     * Child required methods
     */
    abstract protected function setAppEnvironment();

    /**
     * The root app path
     * @var string
     */
    protected $app_path;

    /**
     * The module name, values: frontend, backend, api.
     * @var string
     */
    protected $module;

    /**
     * The module components
     * @var string
     */
    protected $modules_components;

    /**
     * CrazyCake libraries needed for each module
     * @var string
     */
    protected $modules_cclibs;

    /**
     * Module supported langs
     * @var string
     */
    public $modules_langs;


    /**
     * Module production URIS
     * @var array
     */
    public static $modules_production_urls;

    /**
     * The App configuration array
     * @var array
     */
    public $app_config = array();

    /**
     * App properties for configuration array, access by config->app->property
     * @var array
     */
    protected $app_props = array();

    /**
     * The App Dependency injector
     * @var object
     */
    private $di;

    /**
     * Constructor
     * @access public
     * @param string $dir The upload directory
     */
    public function __construct($mod = null)
    {
        //set environment vars (child method)
        $this->setAppEnvironment();

        if(is_null($this->app_path))
            throw new Exception("AppLoader::__construct -> app_path property is not set.");

        if(is_null($mod))
            throw new Exception("AppLoader::__construct -> invalid input module.");

        //define APP contants
        define("PROJECT_PATH", $this->app_path);
        define("PACKAGES_PATH", PROJECT_PATH."packages/");
        define("COMPOSER_PATH", PACKAGES_PATH."composer/");
        define("MODULE_NAME", $mod);
        define("MODULE_PATH", PROJECT_PATH.MODULE_NAME."/");
        define("APP_PATH", MODULE_PATH."app/" );
        define("PUBLIC_PATH", MODULE_PATH."public/");
        define("EXEC_START", microtime(true));  //for debugging render time
        //start webapp loader flux
        $this->_directoriesSetup();
        $this->_autoloadClasses();
        //set env
        $this->_environmentSetUp(); //set APP_ENVIRONMENT & APP_BASE_URL
        $this->_databaseSetup();
    }

    /**
     * Set App Dependency Injector
     * @access public
     * @param array $module_configs extended module configs
     */
    public function setAppDependencyInjector($module_configs = array())
    {
        //set module extended configurations
        $this->_moduleConfigurationSetUp($module_configs);
        //get DI preset services for module
        $services = new AppServices(MODULE_NAME, $this);
        $this->di = $services->getDI();
    }

    /**
     * Start app module execution
     * @access public
     * @param mixed
     */
    public function start($routes_fn = null, $argv = null)
    {
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
            $output = $application->handle()->getContent();

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
     * @param  string $module The module name
     * @return string
     */
    public static function getModuleURL($module = "", $uri = "")
    {
        $url = "";

        if(APP_ENVIRONMENT === "production")
            $url = static::$modules_production_urls[$module];
        else if(APP_ENVIRONMENT === "local")
            $url = str_replace(['/api/', '/frontend/', '/backend/'], "/$module/", APP_BASE_URL);
        else
            $url = str_replace(['.api.', '.frontend.', '.backend.'], ".$module.", APP_BASE_URL);

        return $url.$uri;
    }

    /**
     * Extract assets inside the phar file
     * @static
     * @param  string $assets_uri The phar assets phar as URI, not absolute & must end with a slash
     * @param  string $cache_path The app cache path, must end with a slash
     * @param  string $force_extract Forces extraction not validating contents in given cache path
     * @return mixed[boolean|string] The absolute include cache path
     */
    public static function extractAssetsFromPhar($assets_uri = null, $cache_path = null, $force_extract = false)
    {
        //check folders
        if(is_null($assets_uri) || is_null($cache_path))
            throw new Exception("AppLoader::extractAssetsFromPhar -> assets and cache path must be valid paths.");

        if(!is_dir($cache_path))
            throw new Exception("AppLoader::extractAssetsFromPhar -> cache path directory not found.");

        //check phar is running
        if(!Phar::running())
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
        $phar = new Phar(Phar::running());
        //extract all files in a given directory
        $phar->extractTo($cache_path, $assets, true);

        //return path
        return $output_path;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set Module configurations
     * @access private
     */
    private function _moduleConfigurationSetUp($module_configs = array())
    {
        //merge with input module configs?
        if(!empty($module_configs))
            $this->app_props = array_merge($this->app_props, $module_configs);

        //check for langs supported
        if(isset($this->modules_langs[MODULE_NAME])) {
            $this->app_props['langs'] = $this->modules_langs[MODULE_NAME];
        }

        //set static uri for assets
        if(APP_ENVIRONMENT == 'local' || !isset($this->app_props['staticUri']) || empty($this->app_props['staticUri']))
            $this->app_props['staticUri'] = APP_BASE_URL;

        //set environment dynamic props
        if(isset($this->app_props['aws']['s3Bucket']))
            $this->app_props['aws']['s3Bucket'] .= (APP_ENVIRONMENT == 'production') ? '-prod' : "-dev";

        //finally, set app properties
        $this->app_config["app"] = $this->app_props;
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
        $this->app_config["database"] = [
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
        $app_dirs = array();
        //default directories
        $app_dirs['controllers'] = APP_PATH.'controllers/';

        if(isset($this->modules_components[MODULE_NAME])) {

            foreach ($this->modules_components[MODULE_NAME] as $dir) {

                $paths = explode("/", $dir, 2);

                //set directory path (if first index is a module)
                if(count($paths) > 1 && in_array($paths[0], array_keys($this->modules_components)))
                    $app_dirs[$dir] = PROJECT_PATH.$paths[0]."/app/".$paths[1]."/";
                else
                    $app_dirs[$dir] = APP_PATH.$dir."/";
            }
        }

        //inverted sort
        arsort($app_dirs);
        $this->app_config["directories"] = $app_dirs;
    }

    /**
     * Phalcon Auto Load Classes, Composer and Static Libs
     * @access private
     */
    private function _autoloadClasses()
    {
        //1.- Load app directories (components)
        $loader = new \Phalcon\Loader();
        $loader->registerDirs($this->app_config["directories"]);

        //2.- Register any static libs (like cclibs)
        if(isset($this->modules_cclibs[MODULE_NAME]))
            $this->_loadStaticLibs($loader, $this->modules_cclibs[MODULE_NAME]);

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
     * @param  object $loader   Phalcon loader object
     * @param  array  $packages Modules array
     * @return void
     */
    private function _loadStaticLibs($loader = null, $packages = array())
    {
        if(is_null($loader))
            return;

        //merge packages with defaults
        $packages = array_merge(self::CCLIBS_DEFAULT_PACKAGES, $packages);

        //check if library was loaded from dev environment
        $class_path = is_link(PACKAGES_PATH.self::CCLIBS_NAMESPACE) ? PACKAGES_PATH.self::CCLIBS_NAMESPACE : false;

        //load classes directly form phar
        if(!$class_path) {
            //get class map array
            $class_map = include "AppClassMap.php";

            foreach ($packages as $lib) {
                //loop through package files
                foreach ($class_map[$lib] as $class)
                    require_once Phar::running()."/$lib/".$class;
            }
            return;
        }

        //load classes from symlink
        $namespaces = array();
        foreach ($packages as $lib) {
            $namespaces[self::CCLIBS_PACKAGE.ucfirst($lib)] = "$class_path/$lib/";
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

        //Check for CLI execution
        if (php_sapi_name() !== 'cli') {

            if(!isset($_SERVER['HTTP_HOST']) || !isset($_REQUEST))
                throw new Exception("AppLoader::undefined SERVER or REQUEST data: ".json_encode($_SERVER)." ".json_encode($_REQUEST));

            //set base URL
            $app_base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME'])) . '/';
        }

        //set environment consts & self vars
        define("APP_ENVIRONMENT", $app_environment); //@hardcode option: production
        define("APP_BASE_URL", $app_base_url);
        //var_dump(APP_ENVIRONMENT, APP_BASE_URL);exit;
    }

    /**
     * Minifies HTML output
     * @param string $buffer The buffer
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
