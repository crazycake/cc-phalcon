<?php
/**
 * GetText Adapter
 * Requires: getText, see installed locales command with locale -a
 * This library was tested in Ubuntu environment
 * @author Andres Gutierrez <andres@phalconphp.com>
 * @author Eduar Carvajal <eduar@phalconphp.com>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Translate\Adapter;
use Phalcon\Translate\AdapterInterface;
use Phalcon\Translate\Exception;

class GetText extends Adapter implements AdapterInterface
{
    /**
     * Default UNIX system locale (use locale -a)
     * @var string
     */
    protected $default_locale; 

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var array
     */
    protected $domain;

    /**
     * @var array
     */
    protected $supportedLangs;

    /**
     * @var string
     */
    protected $currentLang;

    /**
     * Class constructor.
     * @param array $options Required options:
     *                       (string) directory
     *                       (string) domain
     *                       (array) supported
     * @throws \Phalcon\Translate\Exception
     */
    public function __construct($options)
    {
        if ( !is_array($options) ) {
            throw new Exception('GetText Lib -> Invalid options: "directory, file, domain, supported" settings are required.');
            die();
        }

        if ( !isset($options['directory']) ) {
            throw new Exception('GetText Lib -> Option "directory" is required.');
            die();
        }

        if ( !isset($options['domain']) ) {
            throw new Exception('GetText Lib -> Option "domain" is required, fo example: web_app. (multiple domains are not supported)');
            die();
        }

        if ( !is_array($options['supported']) ) {
            throw new Exception('GetText Lib > Option "supported" is required and must be an array,  for example: array("en", "es").');
            die();
        }

        //set class properties
        $this->default_locale  = (php_uname('s') == "Darwin") ? "en_US.UTF-8" : "en_US.utf8"; //OSX or Ubuntu
        $this->directory       = $options['directory'];
        $this->domain          = $options['domain'];
        $this->supportedLangs  = $options['supported'];
        //print_r($this->supportedLangs);exit;
        //set language
        $this->setLanguage();
    }

    /**
     * Sets the current language & GetTex lang files domain
     *
     * @access public
     * @return void.
     */
    public function setLanguage($new_lang = null)
    {
        //validate new language
        if( is_null($new_lang) ) {
            $this->currentLang = substr($this->default_locale, 0, 2);
        }
        else {
            $new_lang = trim( strtolower($new_lang) );
            $new_lang = substr($new_lang, 0, 2);

            if( !in_array($new_lang, $this->supportedLangs) )
                $new_lang = substr($this->default_locale, 0, 2);

            //set new lang
            $this->currentLang = $new_lang;
        }
        //print_r("GetText (setLanguage) -> ". $this->currentLang ."\n" );

        //set environment vars
        putenv("LANG="     . $this->default_locale); //force always default locale in server environment
        putenv("LANGUAGE=" . $this->currentLang);    //short version
        setlocale(LC_ALL, "");

        //bind the domain
        bindtextdomain($this->domain, $this->directory);
        //bind_textdomain_codeset($this->domain, 'UTF-8');

        //set text domain
        textdomain($this->domain);
    }

    /**
     * Gets the current language
     *
     * @access public
     * @return the current language simplified (example: en, es).
     */
    public function getLanguage()
    {
        //returns a short version of locale (en, es, fr)
        return $this->currentLang;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $index
     * @return boolean
     */
    public function exists($index)
    {
        return gettext($index) !== '';
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $index
     * @param  array  $placeholders
     * @param  string $domain
     * @return string
     */
    public function query($index, $placeholders = null, $domain = null)
    {
        if (is_null($domain))
            $translation = gettext($index);
        else
            $translation = dgettext($domain, $index);

        if (is_array($placeholders)) {
            foreach ($placeholders as $key => $value) {
                $translation = str_replace('%' . $key . '%', $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string                    $msgid
     * @param  string                    $msgctxt      Optional. If ommitted or NULL, this method behaves as query().
     * @param  array                     $placeholders Optional.
     * @param  string                    $category     Optional. Specify the locale category. Defaults to LC_MESSAGES
     * @return string
     * @throws \InvalidArgumentException
     */
    public function cquery($msgid, $msgctxt = null, $placeholders = null, $category = LC_MESSAGES, $domain = null)
    {
        if (is_null($msgctxt))
            return $this->query($msgid, $placeholders, $domain);

        if (is_null($domain))
            $domain = textdomain(null);

        $contextString = "{$msgctxt}\004{$msgid}";
        $translation   = dcgettext($domain, $contextString, $category);

        if ($translation == $contextString)
            $translation = $msgid;

        if (is_array($placeholders)) {
            foreach ($placeholders as $key => $value) {
                $translation = str_replace('%' . $key . '%', $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * Returns the translation related to the given key and context (msgctxt).
     * This is an alias to cquery().
     *
     * @param  string  $msgid
     * @param  string  $msgctxt      Optional.
     * @param  array   $placeholders Optional.
     * @param  integer $category     Optional. Specify the locale category. Defaults to LC_MESSAGES
     * @return string
     */
    // @codingStandardsIgnoreStart
    public function __($msgid, $msgctxt = null, $placeholders = null, $category = LC_MESSAGES)
        // @codingStandardsIgnoreEnd
    {
        return $this->cquery($msgid, $msgctxt, $placeholders, $category);
    }

    /**
     * Returns the translation related to the given key and context (msgctxt) from a specific domain.
     *
     * @param  string  $domain
     * @param  string  $msgid
     * @param  string  $msgctxt      Optional.
     * @param  array   $placeholders Optional.
     * @param  integer $category     Optional. Specify the locale category. Defaults to LC_MESSAGES
     * @return string
     */
    public function dquery($domain, $msgid, $msgctxt = null, $placeholders = null, $category = LC_MESSAGES)
    {
        return $this->cquery($msgid, $msgctxt, $placeholders, $category, $domain);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $msgid1
     * @param  string  $msgid2
     * @param  integer $count
     * @param  array   $placeholders
     * @param  string  $domain
     * @return string
     */
    public function nquery($msgid1, $msgid2, $count, $placeholders = null, $domain = null)
    {
        if (!is_int($count) || $count < 0) {
            throw new \InvalidArgumentException("Count must be a nonnegative integer. $count given.");
        }

        if (is_null($domain))
            $translation = ngettext($msgid1, $msgid2, $count);
        else
            $translation = dngettext($domain, $msgid1, $msgid2, $count);

        if (is_array($placeholders)) {
            foreach ($placeholders as $key => $value) {
                $translation = str_replace('%' . $key . '%', $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string                    $msgid1
     * @param  string                    $msgid2
     * @param  integer                   $count
     * @param  string                    $msgctxt      Optional. If ommitted or NULL, this method behaves as nquery().
     * @param  array                     $placeholders Optional.
     * @param  string                    $category     Optional. Specify the locale category. Defaults to LC_MESSAGES
     * @return string
     * @throws \InvalidArgumentException
     */
    public function cnquery($msgid1, $msgid2, $count, $msgctxt = null, $placeholders = null, $category = LC_MESSAGES, $domain = null)
    {

        if (!is_int($count) || $count < 0)
            throw new \InvalidArgumentException("Count must be a nonnegative integer. $count given.");

        if (is_null($msgctxt))
            return $this->nquery($msgid1, $msgid2, $count, $placeholders, $domain);

        if (is_null($domain))
            $domain = textdomain(null);

        $contextString1 = "{$msgctxt}\004{$msgid1}";
        $contextString2 = "{$msgctxt}\004{$msgid2}";
        $translation    = dcngettext($domain, $contextString1, $contextString2, $count, $category);

        /*
        if ($translation == $contextString) {
            $translation = $msgid;
        }*/

        if (is_array($placeholders)) {
            foreach ($placeholders as $key => $value) {
                $translation = str_replace('%' . $key . '%', $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * Returns the translation related to the given key and context (msgctxt)
     * from a specific domain with plural form support.
     *
     * @param  string  $domain
     * @param  string  $msgid1
     * @param  string  $msgid2
     * @param  integer $count
     * @param  string  $msgctxt      Optional.
     * @param  array   $placeholders Optional.
     * @param  integer $category     Optional. Specify the locale category. Defaults to LC_MESSAGES
     * @return string
     */
    public function dnquery($domain, $msgid1, $msgid2, $count, $msgctxt = null, $placeholders = null, $category = LC_MESSAGES)
    {
        if (!is_int($count) || $count < 0)
            throw new \InvalidArgumentException("Count must be a nonnegative integer. $count given.");

        return $this->cnquery($msgid1, $msgid2, $count, $msgctxt, $placeholders, $category, $domain);
    }
}
