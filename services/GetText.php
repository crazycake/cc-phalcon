<?php
/**
 * GetText Adapter
 * Requires: getText, see installed locales command with locale -a
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
    public function __construct($options = [])
    {
        if (!is_array($options) || !isset($options["domain"]) || !isset($options["directory"]) || !is_array($options["supported"]))
            die("GetText Lib -> Invalid options: directory, domain & supported options are required.");

        //set class properties
        $this->default_locale  = php_uname("s") == "Darwin" ? "en_US.UTF-8" : "en_US.utf8"; //OSX or Ubuntu
        $this->supported_langs = $options["supported"];
        $this->current_lang    = substr($this->default_locale, 0, 2);

        //call parent constructor
        parent::__construct([
            "locale"        => $this->current_lang,
            "defaultDomain" => $options["domain"],
            "directory"     => $options["directory"],
            "category"      => LC_MESSAGES
        ]);
    }

    /**
     * Sets the current language & GetTex lang files domain
     * @param string $new_lang - The new language
     * @return void
     */
    public function setLanguage($lang = "")
    {
        //validate language
        $lang = substr(trim(strtolower($lang)), 0, 2);

        if (!in_array($lang, $this->supported_langs))
            $lang = substr($this->default_locale, 0, 2);

        //set new lang
        $this->current_lang = $lang;
        //sd($this->getDefaultDomain(), $this->getCategory(), $this->getDirectory(), $this->current_lang);

        //set environment vars
        $this->setLocale(LC_ALL, $this->current_lang);
    }

    /**
     * Get current language
     * @return string
     */
    public function getLanguage()
    {
        return $this->current_lang;
    }
}
