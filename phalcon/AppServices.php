<?php
/**
 * Phalcon App Services files (Dependency Injector)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 * Phalcon Dependency Injection reference:
 * @link http://docs.phalconphp.com/en/latest/reference/di.html
 */

class AppServices
{
    /**
     * Module var
     * @var string
     */
    private $module;

    /**
     * Configuration var
     * @var array
     */
    private $config;

    /**
     * Constructor
     * @param string $mod The app module
     * @param array $conf The app config as array
     */
    public function __construct($mod = null, $conf = null)
    {
        if(is_null($mod) || is_null($conf))
            throw new Exception("AppServices::__construct -> 'module' and 'config' parameters are required.");

        //set class vars
        $this->module = $mod;
        $this->config = new \Phalcon\Config($conf);
    }

    /**
     * Get the DI
     * @return object The Dependency Injector
     */
    public function getDI()
    {
        if($this->module == 'api')
            return $this->_getMicroDI();
        else if($this->module == 'cli')
            return $this->_getCliDI();
        else
            return $this->_getMvcDI();
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set DI for Micro app
     * @access private
     */
    private function _getMicroDI()
    {
        //Get a new Micro DI
        $di = new \Phalcon\DI\FactoryDefault();
        $this->_setCommonServices($di);
        $this->_setDatabaseService($di);
        return $di;
    }

    /**
     * Set DI for CLI (Command Line) app
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
     * Set DI for MVC app
     * @access private
     */
    private function _getMvcDI()
    {
        //import plugins
        require "AppPlugins.php";

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
     * @param object $di
     */
    private function _setCommonServices(&$di)
    {
        //Set the config
        $di->setShared('config', $this->config);

        //The URL component is used to generate all kind of urls in the application
        $di->setShared('url', function() {

            $url = new \Phalcon\Mvc\Url();
            $url->setBaseUri(APP_BASE_URL);
            $url->setStaticBaseUri($this->config->app->staticUri);

            return $url;
        });

        //Logger adapter
        $di->setShared('logger', function() {
            $logger = new \Phalcon\Logger\Adapter\File($this->config->directories->logs.date("d_m_Y").".log");
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
     * @param object $di
     */
    private function _setDatabaseService(&$di, $adapter = 'mysql')
    {
        if($adapter != 'mysql')
            throw new Exception("AppServices::setDatabaseService -> this adapter has not implemented yet :(");

        //Database connection is created based in the parameters defined in the configuration file
        $di->setShared('db', function() {
            return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
                "host"     => $this->config->database["host"],
                "username" => $this->config->database["username"],
                "password" => $this->config->database["password"],
                "dbname"   => $this->config->database["dbname"],
                "options"  => array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8' ) //force utf8-charset
            ));
        });
    }

    /**
     * Set Translation Service
     * GetText adapter (multi-lang support)
     * @access private
     * @param object $di
     */
    private function _setTranslationService(&$di)
    {
        $di->setShared('translate', function() {
            return new \CrazyCake\Utils\GetText(array(
                'domain'    => $this->config->app->name,
                'supported' => (array)$this->config->app->langs,
                'directory' => $this->config->directories->langs
            ));
        });
    }

    /**
     * Set MvC Services
     * @access private
     * @param object $di
     */
    private function _setMvcServices(&$di)
    {
        //Events Manager
        $di->setShared('dispatcher', function() {

            $eventsManager = new \Phalcon\Events\Manager;
            //Handle exceptions and not-found exceptions using ExceptionsPlugin
            $eventsManager->attach('dispatch:beforeException', new \ExceptionsPlugin);

            $dispatcher = new \Phalcon\Mvc\Dispatcher;
            $dispatcher->setEventsManager($eventsManager);
            return $dispatcher;
        });

        //Session Adapter
        $di->setShared('session', function() {
            $session = new \Phalcon\Session\Adapter\Files(array(
                'uniqueId' => MODULE_NAME
            ));
            //set session name
            $session->setName($this->config->app->namespace);
            //start session
            if(!$session->isStarted())
                $session->start();

            return $session;
        });

        //Setting up the view component
        $di_view_engines = array(
            '.volt'  => function($view, $di_instance) {
                //instance a new volt engine
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di_instance);
                //set volt engine options
                $volt->setOptions(array(
                    'compiledPath'      => $this->config->directories->cache,
                    'compiledSeparator' => '_',
                ));
                //get compiler
                $compiler = $volt->getCompiler();

                //++ Binds some PHP functions to volt

                //++ str replace
                $compiler->addFunction('replace', 'str_replace');

                //++ get base URL (same as url method)
                $compiler->addFunction('base_url', function($resolvedArgs, $exprArgs) use ($compiler) {

                    // Resolve the first argument
                    $firstArgument = isset($exprArgs[0]['expr']) ? $compiler->expression($exprArgs[0]['expr']) : "";

                    return empty($firstArgument) ? "'".APP_BASE_URL."'" :  "'".APP_BASE_URL."'.$firstArgument";
                });

                return $volt;
            },
            '.phtml' => 'Phalcon\Mvc\View\Engine\Php',
        );
        $di->setShared('view', function() use (&$di_view_engines) {

            $view = new \Phalcon\Mvc\View();
            //set directory views
            $view->setViewsDir($this->config->directories->views);
            //register volt view engine
            $view->registerEngines($di_view_engines);

            return $view;
        });

        //Setting up the simpleView component, same as view
        $di->setShared('simpleView', function() use (&$di_view_engines) {
            //simpleView
            $view = new \Phalcon\Mvc\View\Simple();
            $view->setViewsDir($this->config->directories->views);
            $view->registerEngines($di_view_engines);
            return $view;
        });

        //Flash messages
        $di->setShared('flash', function() {
            $flash = new \Phalcon\Flash\Session(array(
                'success' => 'success',
                'error'   => 'alert',
                'notice'  => 'notice',
                'warning' => 'warning'
            ));
            return $flash;
        });
    }
}
