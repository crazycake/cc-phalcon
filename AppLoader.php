<?php
/**
 * Phalcon Project Environment configuration file.
 * Requires PhalconPHP installed
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

//imports
require "phalcon/AppServices.php";

abstract class AppLoader
{
    /** const **/
    const CCLIBS_PACKAGE      = "CrazyCake\\";
    const CCLIBS_NAMESPACE    = "cc-phalcon";

    //for CLI environment setup
    const EC2_HOSTNAME_PREFIX = "ip-";
    const DEPLOY_FILENAME     = ".deploy";

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
     * The database app configuration
     * @var string
     */
    protected $app_db;

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
    protected $modules_langs;

    /**
     * App properties for configuration array, access by config->app->property
     * @var array
     */
    protected $app_props = array();

    /**
     * The App configuration array
     * @var array
     */
    private $app_config = array();

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
        $this->_environmentSetUp(); //set APP_ENVIRONMENT & APP_BASE_URL
        $this->_databaseSetup();
        $this->_directoriesSetup();
        $this->_autoloadClasses();
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
        $services = new AppServices(MODULE_NAME, $this->app_config);
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
                    case 1: $arguments['task']   = $arg; break;
                    case 2: $arguments['action'] = $arg; break;
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
            define('CLI_TASK', isset($argv[1]) ? $argv[1] : null);
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
            if(APP_ENVIRONMENT !== 'development')
                ob_start(array($this,"_minifyHTML")); //call function

            echo $output;
        }
    }

    /**
     * Get a module URL from current environment
     * @static
     * @param  string $module The module name
     * @return string
     */
    public static function getModuleEnviromentURL($module = "")
    {
        if(APP_ENVIRONMENT === 'development')
            return str_replace(array('/api/','/frontend/','/backend/'), "/$module/", APP_BASE_URL);
        else
            return str_replace(array('.api.','.frontend.','.backend.'), ".$module.", APP_BASE_URL);
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
        $phar_assets = __DIR__."/".$assets_uri;
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

        //set local shared resourced path, setting is an array where index 0 => module_name & 1 => the uri
        if(isset($this->app_props['sharedResourcesUri'])) {
            $uris = explode("/", $this->app_props['sharedResourcesUri'], 2);
            $this->app_props['sharedResourcesUri'] = $this->getModuleEnviromentURL($uris[0]).$uris[1];
        }

        //set environment dynamic props
        if(isset($this->app_props['awsS3Bucket'])) {
            $this->app_props['awsS3Bucket'] .= (APP_ENVIRONMENT == 'production') ? '-prod' : '-dev';
        }

        //finally, set app properties
        $this->app_config["app"] = $this->app_props;
    }

    /**
     * Set Database configurations
     * @access private
     */
    private function _databaseSetup()
    {
        if(is_null($this->app_db))
            throw new Exception("AppLoader::_databaseSetup -> var 'app_db' is not set.");

        $app_database = $this->app_db[APP_ENVIRONMENT];
        //set database config
        $this->app_config["database"] = array(
            'host'      => $app_database[0],
            'username'  => $app_database[1],
            'password'  => $app_database[2],
            'dbname'    => $app_database[3]
        );
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
        $app_dirs['logs']        = APP_PATH.'logs/';

        if(isset($this->modules_components[MODULE_NAME])) {
            foreach ($this->modules_components[MODULE_NAME] as $dir) {
                $public = false;
                //check if directory is public
                if (substr($dir, 0, 1) === "@") {
                    $public = true;
                    $dir    = substr($dir, 1);
                }
                //set directory path
                $app_dirs[$dir] = $public ? PUBLIC_PATH.$dir."/" : APP_PATH.$dir."/";
            }
        }

        //set directories
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

        //3.- Composer libs, use composer auto load for production
        if (APP_ENVIRONMENT !== 'development') {

            //autoload classes (se debe pre-generar autoload_classmap)
            $classmap = COMPOSER_PATH.'vendor/composer/autoload_classmap.php';

            if(!is_file($classmap))
                throw new Exception("AppLoader::_autoloadClasses -> Composer libraries are missing, please run environment bash file (-composer option).");

            $loader->registerClasses(require $classmap);
            //check if autoload files must be loaded
            if (file_exists(COMPOSER_PATH.'vendor/composer/autoload_files.php')) {

                $autoload_files = require COMPOSER_PATH.'vendor/composer/autoload_files.php';
                //include classes?
                foreach ($autoload_files as $file)
                    include_once $file;
            }
        }
        else {

            //autoload composer file
            if (!is_file(COMPOSER_PATH.'vendor/autoload.php'))
                throw new Exception("AppLoader::_autoloadClasses -> Composer libraries are missing, please run environment bash file (-composer option).");

            //autoload composer file
            require COMPOSER_PATH.'vendor/autoload.php';
        }

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
        if(is_null($loader) || empty($packages))
            return;

        //check if library was loaded from dev environment
        $class_path = is_link(PACKAGES_PATH.self::CCLIBS_NAMESPACE) ? PACKAGES_PATH.self::CCLIBS_NAMESPACE : false;

        //load classes directly form phar
        if(!$class_path) {
            //get class map array
            $class_map = include "phalcon/AppClassMap.php";

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
        //set default environment
        $app_base_url = "./";

        //make sure script execution is not comming from command line (CLI)
        if (php_sapi_name() !== 'cli') {
            //set base URL
            $app_base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME'])) . '/';

            //SET APP_ENVIRONMENT
            if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || strpos($_SERVER['SERVER_NAME'], '192.168.') !== false) {
                ini_set('display_errors', 1);
                error_reporting(E_ALL);
                $app_environment = "development";
            }
            elseif (strpos($_SERVER['SERVER_NAME'], '.testing.') !== false || strpos($_SERVER['SERVER_NAME'], '.ngrok.io' ) !== false) {
                ini_set('display_errors', 1);
                error_reporting(E_ALL);
                $app_environment = "testing";
            }
            else {
                ini_set('display_errors', 0);
                error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
                $app_environment = "production";
            }
        }
        //CLI config, set ENV, checks that host machine is a AWS EC2 machine
        else {
            $hostname         = gethostname();
            $testing_hostname = $this->_readFromDeployFile("TEST_HOSTNAME");

            //dev
            if(strpos($hostname, self::EC2_HOSTNAME_PREFIX) === false) {
                $app_environment = "development";
            }
            else if($hostname == $testing_hostname) {
                $app_environment = "testing";
            }
            else {
                $app_environment = "production";
            }
        }
        //print_r($app_environment);exit;
        //set environment consts & self vars
        define("APP_ENVIRONMENT", $app_environment); //@hardcode: production
        define("APP_BASE_URL", $app_base_url);
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

     /**
      * Reads the deploy file and extracts an attribute
      * @param string $attr The attribute Key
      * @return string
      */
     private function _readFromDeployFile($attr)
     {
         $file = file(PROJECT_PATH.self::DEPLOY_FILENAME);

         if(!$file)
            throw new Exception("AppLoader::_readDeployFile -> No deploy file found, filename: $filename!");

        $hostname = null;

        foreach($file as $line) {

            //split file contents
            list($key, $value) = explode(" = ", $line);

            //check for key
            if(trim($key) != $attr)
                continue;

            //check for key
            $hostname = $value;
            break;
        }

        return trim($hostname);
     }
}
