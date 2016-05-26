<?php
/**
 * GetText Adapter
 * Requires: getText, see installed locales command with locale -a
 * This library was tested in Ubuntu environment
 * @author Andres Gutierrez <andres@phalconphp.com>
 * @author Eduar Carvajal <eduar@phalconphp.com>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Services;

//imports
use Phalcon\Exception;
use Phalcon\Translate\Adapter\Gettext as GetTextAdapter;
/**
 * GetText Adapter Handler
 */
class GetText extends GetTextAdapter
{
    /**
     * Default UNIX system locale (use locale -a)
     * @var string
     */
    protected $default_locale;

    /**
     * @var string
     */
    protected $current_lang;

    /**
     * @var array
     */
    protected $supported_langs;


    /**
     * Class constructor.
     * @param array $options - Required options:
     *     (string) directory
     *     (string) domain
     *     (array) supported
     */
    public function __construct($options)
    {
        if (!is_array($options) || !isset($options["domain"]) || !isset($options["directory"]) || !is_array($options["supported"]))
            die("GetText Lib -> Invalid options: directory, domain & supported options are required.");

        //set class properties
        $this->default_locale  = (php_uname("s") == "Darwin") ? "en_US.UTF-8" : "en_US.utf8"; //OSX or Ubuntu
        $this->supported_langs = $options["supported"];

        //call parent constructor
        parent::__construct([
            'locale'        => $this->default_locale,
            'defaultDomain' => $options["domain"],
            'directory'     => $options["directory"],
           'category'       => LC_MESSAGES
        ]);

        //set language
        $this->setLanguage();
    }

    /**
     * Sets the current language & GetTex lang files domain
     * @param string $new_lang - The new language
     * @return void
     */
    public function setLanguage($new_lang = null)
    {
        //validate new language
        if (is_null($new_lang)) {
            $this->current_lang = substr($this->default_locale, 0, 2);
        }
        else {
            $new_lang = trim(strtolower($new_lang));
            $new_lang = substr($new_lang, 0, 2);

            if (!in_array($new_lang, $this->supported_langs))
                $new_lang = substr($this->default_locale, 0, 2);

            //set new lang
            $this->current_lang = $new_lang;
        }
        //print_r("CrazyCake GetText (setLanguage) -> ". $this->current_lang ."\n" );

        //set environment vars
        $this->setLocale(LC_ALL, $this->current_lang);
    }
}
