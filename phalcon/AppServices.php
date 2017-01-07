<?php
/**
 * Phalcon App Services files (Dependency Injector)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 * Phalcon Dependency Injection reference:
 * @link http://docs.phalconphp.com/en/latest/reference/di.html
 */

namespace CrazyCake\Phalcon;

//phalcon
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
     * Constructor
     * @param object $loaderObj - The app loader instance
     */
    public function __construct($config)
    {
        // set class vars
        $this->config = new \Phalcon\Config($config);
    }

    /**
     * Get the DI
     * @return object - The Dependency Injector
     */
    public function getDI()
    {
        if (MODULE_NAME == "cli")
            return $this->_getCliDI();

        return $this->_getDefaultDI();
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Set DI for CLI app
     * @access private
     */
    private function _getCliDI()
    {
        // get a new Micro DI
        $di = new \Phalcon\DI\FactoryDefault\CLI();
        $this->_setCommonServices($di);
        $this->_setDatabaseService($di);
        $this->_setTranslationService($di);
        return $di;
    }

    /**
     * Set DI for Mvc app
     * @access private
     */
    private function _getDefaultDI()
    {
        // get a new Micro DI
        $di = new \Phalcon\DI\FactoryDefault();
        $this->_setCommonServices($di);
        $this->_setDatabaseService($di);
        $this->_setTranslationService($di);

        if(MODULE_NAME != "api")
            $this->_setWebappServices($di);

        return $di;
    }

    /**
     * Set Common Services
     * @access private
     * @param object $di - The DI object
     */
    private function _setCommonServices(&$di)
    {
        // set the config
        $di->setShared("config", $this->config);

        // the URL component is used to generate all kind of urls in the application
        $di->setShared("url", function() {

            $url = new \Phalcon\Mvc\Url();
            // base URL
            $url->setBaseUri(APP_BASE_URL);

            // get static url
            $static_url = !empty($this->config->staticUrl) ? $this->config->staticUrl : false;

            // set static uri for assets, cdn only for production
            if (!$static_url || APP_ENV == "local")
                $static_url = APP_BASE_URL;

            $url->setStaticBaseUri($static_url);

            return $url;
        });

        // logger adapter
        $di->setShared("logger", function() {

            // date now
            $log_file = date("d-m-Y");

            // special case for cli (log is not saved as 'httpd user' as default)
            if(MODULE_NAME == "cli")
                $log_file = "cli_".$log_file;

            $logger = new \Phalcon\Logger\Adapter\File(STORAGE_PATH."logs/".$log_file.".log");
            return $logger;
        });

        // basic http security
        $di->setShared("security", function() {

            $security = new \Phalcon\Security();
            // set the password hashing factor to X rounds
            $security->setWorkFactor(12);
            return $security;
        });

        // phalcon Crypt service
        $di->setShared("crypt", function() {

            $crypt = new \Phalcon\Crypt();
            $crypt->setKey($this->config->cryptKey);
            return $crypt;
        });

        // extended encryption, Cryptify adapter (cryptography helper)
        if (class_exists("\CrazyCake\Helpers\Cryptify")) {

            $di->setShared("cryptify", function() {
                return new \CrazyCake\Helpers\Cryptify($this->config->cryptKey);
            });
        }

        // kint options
        if (class_exists("\Kint")) {
            \Kint::$theme = "solarized";
        }
    }

    /**
     * Set Database Service
     * @access private
     * @param object $di - The DI object
     * @param string $adapter - The DB adapter
     */
    private function _setDatabaseService(&$di, $adapter = "mysql")
    {
        if ($adapter != "mysql")
            throw new Exception("AppServices::setDatabaseService -> the adapter $adapter has not implemented yet.");

        // database connection is created based in the parameters defined in the configuration file
        $di->setShared("db", function() {

    		$db_conf = [
                "host"     => getenv("MYSQL_HOST") ?: "db",
                "port"     => 3306,
                "dbname"   => "app",
                "username" => getenv("MYSQL_USER") ?: "root",
                "password" => "mysql24681214?",
                "options"  => [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
            ];

            return new \Phalcon\Db\Adapter\Pdo\Mysql($db_conf);
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
        // check if langs are set
        if (empty($this->config->langs))
            return;

        $di->setShared("trans", function() {

            return new \CrazyCake\Helpers\GetText([
                "domain"    => "app",
                "supported" => (array)$this->config->langs,
                "directory" => APP_PATH."langs/"
            ]);
        });
    }

    /**
     * Set Web Services
     * @access private
     * @param object $di - The DI object
     */
    private function _setWebappServices(&$di)
    {
        // events manager
        $di->setShared("dispatcher", function() {

            $eventsManager = new \Phalcon\Events\Manager;
            //Handle exceptions and not-found exceptions using ExceptionsPlugin
            $eventsManager->attach("dispatch:beforeException", new ExceptionsPlugin);

            $dispatcher = new \Phalcon\Mvc\Dispatcher;
            $dispatcher->setEventsManager($eventsManager);
            return $dispatcher;
        });

        // session Adapter
        $di->setShared("session", function() {

            $expiration = 3600*4; //4 hours

            //default session
            if(empty($this->config->redisSession)) {

                $session = new \Phalcon\Session\Adapter\Files([
                    "uniqueId" => MODULE_NAME
                ]);
            }
            //redis session (requires extension)
            else {

                $session = new \Phalcon\Session\Adapter\Redis([
                    "uniqueId"   => MODULE_NAME,
                    "host"       => getenv("REDIS_HOST") ?: "redis",
                    "lifetime"   => $expiration,
                    "prefix"     => "_SID_"
                ]);
            }

            // set session name (cookie)
            $session->setName($this->config->namespace);
            // start session
            if (!$session->isStarted()) {

                //session TTL
                session_set_cookie_params($expiration);
                $session->start();
            }

            return $session;
        });

        // setting up the view component
        $di_view_engines = [
            ".volt" => function($view, $di_instance) {
                // instance a new volt engine
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di_instance);
                // set volt engine options
                $volt->setOptions([
                    "compiledPath"      => STORAGE_PATH."cache/",
                    "compiledSeparator" => "_",
                ]);
                // get compiler
                $compiler = $volt->getCompiler();

                //++ Binds some PHP functions to volt

                //++ str_replace
                $compiler->addFunction("replace", "str_replace");
				//++ substr
                $compiler->addFunction("substr", "substr");
                //++ strrpos
                $compiler->addFunction("strrpos", "strrpos");
				//++ strtotime
				$compiler->addFunction("strtotime", "strtotime");
                //++ in_array
                $compiler->addFunction("in_array", "in_array");
                //++ resizedImagePath
                $compiler->addFunction("resized_image_path", function($resolvedArgs, $exprArgs) {
                    return "CrazyCake\Helpers\Images::resizedImagePath(".$resolvedArgs.")";
                });

                return $volt;
            },
            ".phtml" => "Phalcon\Mvc\View\Engine\Php",
        ];

        // set view service
        $di->setShared("view", function() use (&$di_view_engines) {

            $view = new \Phalcon\Mvc\View();
            //set directory views
            $view->setViewsDir(PROJECT_PATH."ui/volt/");
            //register volt view engine
            $view->registerEngines($di_view_engines);

            return $view;
        });

        // simple view service
        $di->setShared("simpleView", function() use (&$di_view_engines) {

            //simpleView
            $view = new \Phalcon\Mvc\View\Simple();
            //set directory views
            $view->setViewsDir(PROJECT_PATH."ui/volt/");
            //register volt view engine
            $view->registerEngines($di_view_engines);

            return $view;
        });

        // cookies
        $di->setShared("cookies", function() {

            $cookies = new \Phalcon\Http\Response\Cookies();
            //no encryption
            $cookies->useEncryption(false);

            return $cookies;
        });

        // flash messages
        $di->setShared("flash", function() {

            $flash = new \Phalcon\Flash\Session([
                "success" => "success",
                "error"   => "alert",
                "notice"  => "notice",
                "warning" => "warning"
            ]);

            return $flash;
        });
    }
}
