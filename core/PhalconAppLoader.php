<?php
/**
 * Phalcon Project Environment configuration file.
 * Requires PhalconPHP installed
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

//imports
require "PhalconAppServices.php";

abstract class PhalconAppLoader
{
    /** const **/
    const CCLIBS_NAMESPACE   = "CrazyCake\\";
    const CCLIBS_FOLDER_NAME = "cc-phalcon";

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
            throw new Exception("PhalconAppLoader::__construct -> app_path property is not set.");

        if(is_null($mod))
            throw new Exception("PhalconAppLoader::__construct -> invalid input module.");

        //set directory and create if not exists
        $this->module = $mod;
        //define APP contants
        define("PROJECT_PATH", $this->app_path);
        define("COMPOSER_PATH", PROJECT_PATH."composer/vendor/");
        define("MODULE_PATH", PROJECT_PATH.$this->module."/");
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
        $services = new PhalconAppServices($this->module, $this->app_config);
        $this->di = $services->getDI();
    }

    /**
     * Start app module execution
     * @access public
     * @param mixed
     */
    public function start($routes_fn = null, $argv = null)
    {
        if($this->module == "cli") {
            //new cli app
            $application = new \Phalcon\CLI\Console($this->di);
            //loop through args
            $arguments = array();

            if(is_null($argv))
                die("Phalcon Console -> no args supplied\n");

            foreach ($argv as $k => $arg) {
                switch ($k) {
                    case 1: $arguments['task']     = $arg; break;
                    case 2: $arguments['action']   = $arg; break;
                    case 3: $arguments['params'][] = $arg; break;
                    default: break;
                }
            }
            
            //define global constants for the current task and action
            define('CLI_TASK', isset($argv[1]) ? $argv[1] : null);
            define('CLI_ACTION', isset($argv[2]) ? $argv[2] : null);
            //handle incoming arguments
            $application->handle($arguments);
        }
        elseif($this->module == "api") {
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
            //Handle the request
            echo $application->handle()->getContent();
        }
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
        if(isset($this->modules_langs[$this->module])) {
            $this->app_props['langs'] = $this->modules_langs[$this->module];
        }

        //set local backend upload uri
        if(isset($this->app_props['localBackendUploadsUri']) && $this->module == "api") {
            $placeholders = array("api", "public/");
            $new_values   = array("backend", "");
            $uri = APP_BASE_URL . $this->app_props['localBackendUploadsUri'];
            $this->app_props['localBackendUploadsUri'] = str_replace($placeholders, $new_values, $uri);
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
            throw new Exception("PhalconAppLoader::_databaseSetup -> var 'app_db' is not set.");

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

        if(isset($this->modules_components[$this->module])) {
            foreach ($this->modules_components[$this->module] as $dir) {
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
     * Phalcon Auto Load Classes
     * @access private
     */
    private function _autoloadClasses()
    {
        //load app directories
        $loader = new \Phalcon\Loader();
        $loader->registerDirs($this->app_config["directories"]);

        //check for any 3rd party lib
        if(isset($this->modules_cclibs[$this->module])) {
            $namespaces = array();
            foreach ($this->modules_cclibs[$this->module] as $lib) {
                $namespaces[self::CCLIBS_NAMESPACE.ucfirst($lib)] = PROJECT_PATH.self::CCLIBS_FOLDER_NAME."/".$lib."/";
            }
            //register namespaces
            $loader->registerNamespaces($namespaces);
        }

        //Composer libs, use composer auto load for production
        if (APP_ENVIRONMENT === 'production') {
            //autoload classes (se debe pre-generar autoload_classmap)
            $loader->registerClasses(require COMPOSER_PATH.'composer/autoload_classmap.php');

            //check if autoload files must be loaded
            if (file_exists(COMPOSER_PATH.'composer/autoload_files.php')) {

                $autoload_files = require COMPOSER_PATH.'composer/autoload_files.php';
                //include classes?
                foreach ($autoload_files as $file)
                    include_once $file;
            }
        }
        else {
            //autoload composer file
            if (!is_file(COMPOSER_PATH.'autoload.php'))
                throw new Exception("PhalconAppLoader::_autoloadClasses -> Composer libraries are missing, please run environment bash file.");

            //autoload composer file
            require COMPOSER_PATH.'autoload.php';
        }

        //register phalcon loader
        $loader->register();
    }

    /**
     * Set Environment properties
     * @access private
     */
    private function _environmentSetUp()
    {
        //set default environment
        $app_environment = 'development';
        $app_base_url    = "./";

        //make sure script execution is not comming from command line (CLI)
        if (php_sapi_name() !== 'cli') {
            //set base URL
            $app_base_url = (isset($_SERVER["HTTPS"]) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME'])) . '/';

            //SET APP_ENVIRONMENT
            if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || strpos($_SERVER['SERVER_NAME'], '192.168.') !== false || strpos($_SERVER['SERVER_NAME'], 'ngrok') !== false) {
                ini_set('display_errors', 1);
                error_reporting(E_ALL);
            }
            elseif (strpos($_SERVER['SERVER_NAME'], '.testing.') !== false) {
                ini_set('display_errors', 1);
                error_reporting(E_ALL);
                $app_environment = 'testing';
            }
            else {
                ini_set('display_errors', 0);
                error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
                $app_environment = 'production';
            }
        }
        //$app_environment = 'production'; //DEBUG PRODUCTION

        //set environment consts
        define("APP_ENVIRONMENT", $app_environment);
        define("APP_BASE_URL", $app_base_url);
    }
}
