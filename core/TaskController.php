<?php
/**
 * CLI Task Controller: provides common functions for CLI tasks.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\CLI\Task;

abstract class TaskController extends Task
{
    /**
     * child required methods
     */
    abstract protected function mainAction();

    /* consts */
    const MODULE_JS_PATH      = "/public/js/";
    const MODULE_VIEWS_PATH   = "/app/views/";
    const MODULE_LANGS_FOLDER = "/app/langs/";

    const JS_LANGS_FILENAME    = "app_langs.%code%.js";
    const PHTML_LANGS_FILENAME = "javascript/langs";

    /**
     * Print Output with Colors
     * @access protected
     * @param  string $text
     * @param  string $status Can be OK, ERROR, WARNING OR NOTE
     * @return string
     */
    protected function _colorize($text = "", $status = "OK", $die = false)
    {
        $open  = "";
        $close = "\033[0m";
        
        switch ($status) {
            case "OK":
                $open = "\033[92m";     //Green color
                break;
            case "ERROR":
                $open = "\033[91m";     //Red color
                break;
            case "WARNING":
                $open = "\033[93m";     //Yellow color
                break;
            case "NOTE":
                $open = "\033[94m";     //Blue color
                break;
            default:
                throw new \Exception("CoreTask:_colorize -> invalid status: " . $status);
        }
        //return outout, chr(27 ) -> escape key
        $output = chr(27) . $open . $text . chr(27) . $close . "\n";

        if($die)
            die($output);

        return $output;
    }

    /**
     * Validates module folder argument
     * @param array $params, The args array
     * @param int $index, The arg index to validate
     * @param boolean $check_folder, Checks if module folder exists
     * @return mixed
     */
    protected function _validatesModuleArg($params = array(), $index = 0, $check_folder = true)
    {
        if(empty($params) || !isset($params[$index])) {
            echo $this->_colorize("An argument [module] is missing", "ERROR") . "\n";
            exit;
        }

        $module = PROJECT_PATH.$params[$index];

        //check for folder
        if($check_folder && !is_dir($module)) {
            echo $this->_colorize("The input module folder ($module) was not found", "ERROR") . "\n";
            exit;
        }

        return $module;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Create JS Files for app supported languages
     * @param array $params The input args
     * @return void
     */
    public function jstransAction($params = array())
    {
        //get module
        $module = $this->_validatesModuleArg($params, 0);

        if(!is_dir($module.self::MODULE_LANGS_FOLDER)) {
            echo $this->_colorize("No langs directories found", "ERROR") . "\n";
            return;
        }

        //scan lang files
        $files = scandir($module.self::MODULE_LANGS_FOLDER);

        //get supported langs
        $supportedLangs = array();
        foreach ($files as $f) {
            //check for only lang_codes folder
            if(strlen($f) == 2 && ctype_alnum($f) && is_dir($module.self::MODULE_LANGS_FOLDER.$f)) {
                array_push($supportedLangs, $f);
            }
        }

        if(empty($supportedLangs)) {
            echo $this->_colorize("No lang_codes folders found", "ERROR") . "\n";
            return;
        }

        //update self-config
        $this->config->directories->langs = $module.self::MODULE_LANGS_FOLDER;
        $this->config->app->langs = $supportedLangs;

        //instance simple view
        $view = new \Phalcon\Mvc\View\Simple();
        $view->setViewsDir($module.self::MODULE_VIEWS_PATH);

        foreach ($supportedLangs as $code) {
            //set language
            $this->translate->setLanguage($code);
            //get output
            $output = $view->render(self::PHTML_LANGS_FILENAME);
            //create JS file
            $js_filename = str_replace("%code%", $this->translate->getLanguage(), self::JS_LANGS_FILENAME);

            echo $this->_colorize("Creating file " . $js_filename . " ...", "WARNING");
            //creates file
            file_put_contents($module.self::MODULE_JS_PATH. $js_filename, $output);
        }
        //response
        echo $this->_colorize("JS Files successfully created at path: ".$module.self::MODULE_JS_PATH, "OK") . "\n";
    }
}
