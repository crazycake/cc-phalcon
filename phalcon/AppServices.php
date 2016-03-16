<?php
/**
 * Phalcon App Services files (Dependency Injector)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 * Phalcon Dependency Injection reference:
 * @link http://docs.phalconphp.com/en/latest/reference/di.html
 */

namespace CrazyCake\Phalcon;

use Phalcon\Exception;

//import plugins
require "AppPlugins.php";

/**
 * Phalcon Services Loader
 */
class AppServices
{
    /**
     * Phalcon config object
     * @var object
     */
    private $config;

    /**
     * Module properties
     * @var function
     */
    private $props;

    /**
     * Constructor
     * @param object $loaderObj - The app loader instance
     */
    public function __construct($loaderObj = null)
    {
        if(is_null($loaderObj))
            throw new Exception("AppServices::__construct -> 'loader' object is required.");

        //set class vars
        $this->config = new \Phalcon\Config($loaderObj->app_conf);
        $this->props  = $loaderObj::$modules_conf[MODULE_NAME];
    }

    /**
     * Get the DI
     * @return object - The Dependency Injector
     */
    public function getDI()
    {
        if(MODULE_NAME == 'api')
            return $this->_getMicroDI();
        else if(MODULE_NAME == 'cli')
            return $this->_getCliDI();

        //frontend or backend
        return $this->_getMvcDI();
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set DI for Micro app (API)
     * @access private
     */
    private function _getMicroDI()
    {
        //Get a new Micro DI
        $di = new \Phalcon\DI\FactoryDefault();
        $this->_setCommonServices($di);
        $this->_setDatabaseService($di);
        $this->_setTranslationService($di);
        return $di;
    }

    /**
     * Set DI for CLI app (Command Line)
     * @access private
     */
    private function _getCliDI()
    {
        //Get a new Micro DI
        $di = new \Phalcon\DI\FactoryDefault\CLI();
        $this->_setCommonServices($di);
        $this->_setDatabaseService($di);
        $this->_setTranslationService($di);
        return $di;
    }

    /**
     * Set DI for MVC app (frontend, backend)
     * @access private
     */
    private function _getMvcDI()
    {
        //Get a new Micro DI
        $di = new \Phalcon\DI\FactoryDefault();
        $this->_setCommonServices($di);
        $this->_setDatabaseService($di);
        $this->_setTranslationService($di);
        $this->_setMvcServices($di);
        return $di;
    }

    /**
     * Set Common Services
     * @access private
     * @param object $di - The DI object
     */
    private function _setCommonServices(&$di)
    {
        //Set the config
        $di->setShared('config', $this->config);

        //The URL component is used to generate all kind of urls in the application
        $di->setShared('url', function() {

            $url = new \Phalcon\Mvc\Url();
            //Base URL
            $url->setBaseUri(APP_BASE_URL);

            //get static url
            $staticUrl = isset($this->props["staticUrl"]) ? $this->props["staticUrl"] : false;

            //set static uri for assets, cdn only for production
            if(!$staticUrl || APP_ENVIRONMENT !== 'production')
                $staticUrl = APP_BASE_URL;

            $url->setStaticBaseUri($staticUrl);

            return $url;
        });

        //Logger adapter
        $di->setShared('logger', function() {

            $logger = new \Phalcon\Logger\Adapter\File(APP_PATH."logs/".date("d-m-Y").".log");
            return $logger;
        });

        //Basic http security
        $di->setShared('security', function() {

            $security = new \Phalcon\Security();
            //Set the password hashing factor to X rounds
            $security->setWorkFactor(12);
            return $security;
        });

        //Phalcon Crypt service
        $di->setShared('crypt', function() {

            $crypt = new \Phalcon\Crypt();
            $crypt->setKey($this->config->app->cryptKey);
            return $crypt;
        });

        //Extended encryption, Cryptify adapter (cryptography helper)
        if(class_exists('\CrazyCake\Utils\Cryptify')) {

            $di->setShared('cryptify', function() {
                return new \CrazyCake\Utils\Cryptify($this->config->app->cryptKey);
            });
        }
    }

    /**
     * Set Database Service
     * @access private
     * @param object $di - The DI object
     * @param string $adapter - The DB adapter
     */
    private function _setDatabaseService(&$di, $adapter = 'mysql')
    {
        if($adapter != 'mysql')
            throw new Exception("AppServices::setDatabaseService -> this adapter has not implemented yet :(");

        //Database connection is created based in the parameters defined in the configuration file
        $di->setShared('db', function() {

            return new \Phalcon\Db\Adapter\Pdo\Mysql([
                "host"     => $this->config->database["host"],
                "username" => $this->config->database["username"],
                "password" => $this->config->database["password"],
                "dbname"   => $this->config->database["dbname"],
                "options"  => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'] //force utf8-charset
            ]);
        });
    }

    /**
     * Set Translation Service
     * GetText adapter (multi-lang support)
     * @access private
     * @param object $di - The DI object
     */
    private function _setTranslationService(&$di)
    {
        //check if langs are set
        if(empty($this->config->app->langs))
            return;

        $di->setShared('trans', function() {

            return new \CrazyCake\Services\GetText([
                'domain'    => $this->config->app->name,
                'supported' => (array)$this->config->app->langs, //config obj to array
                'directory' => APP_PATH."langs/"
            ]);
        });
    }

    /**
     * Set MvC Services
     * @access private
     * @param object $di - The DI object
     */
    private function _setMvcServices(&$di)
    {
        //Events Manager
        $di->setShared('dispatcher', function() {

            $eventsManager = new \Phalcon\Events\Manager;
            //Handle exceptions and not-found exceptions using ExceptionsPlugin
            $eventsManager->attach('dispatch:beforeException', new ExceptionsPlugin);

            $dispatcher = new \Phalcon\Mvc\Dispatcher;
            $dispatcher->setEventsManager($eventsManager);
            return $dispatcher;
        });

        //Session Adapter
        $di->setShared('session', function() {
            
            $session = new \Phalcon\Session\Adapter\Files([
                'uniqueId' => MODULE_NAME
            ]);
            //set session name
            $session->setName($this->config->app->namespace);
            //start session
            if(!$session->isStarted())
                $session->start();

            return $session;
        });

        //Setting up the view component
        $di_view_engines = [
            '.volt' => function($view, $di_instance) {
                //instance a new volt engine
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di_instance);
                //set volt engine options
                $volt->setOptions([
                    'compiledPath'      => APP_PATH."cache/",
                    'compiledSeparator' => '_',
                ]);
                //get compiler
                $compiler = $volt->getCompiler();

                //++ Binds some PHP functions to volt

                //++ str replace
                $compiler->addFunction('replace', 'str_replace');

                return $volt;
            },
            '.phtml' => 'Phalcon\Mvc\View\Engine\Php',
        ];

        $di->setShared('view', function() use (&$di_view_engines) {

            $view = new \Phalcon\Mvc\View();
            //set directory views
            $view->setViewsDir(APP_PATH."views/");
            //register volt view engine
            $view->registerEngines($di_view_engines);

            return $view;
        });

        //Setting up the simpleView component, same as view
        $di->setShared('simpleView', function() use (&$di_view_engines) {

            //simpleView
            $view = new \Phalcon\Mvc\View\Simple();
            //set directory views
            $view->setViewsDir(APP_PATH."views/");
            //register volt view engine
            $view->registerEngines($di_view_engines);

            return $view;
        });

        //Cookies
        $di->setShared('cookies', function() {

            $cookies = new \Phalcon\Http\Response\Cookies();
            //no encryption
            $cookies->useEncryption(false);

            return $cookies;
        });

        //Flash messages
        $di->setShared('flash', function() {

            $flash = new \Phalcon\Flash\Session([
                'success' => 'success',
                'error'   => 'alert',
                'notice'  => 'notice',
                'warning' => 'warning'
            ]);

            return $flash;
        });
    }
}
