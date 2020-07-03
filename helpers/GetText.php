<?php
/**
 * GetText Adapter
 * Requires getText, see installed locales command with locale -a (debian)
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Helpers;

use Phalcon\Translate\Adapter\Gettext as GetTextAdapter;

/**
 * GetText Adapter Handler
 */
class GetText extends GetTextAdapter
{
	/**
	 * available locales
	 * @var Array
	 */
	const LOCALES = [
		"en" => "en_US.utf8",
		"es" => "es_ES.utf8",
		"zh" => "zh_CN.utf8"
	];

	/**
	 * Default UNIX system locale (use locale -a)
	 * @var String
	 */
	protected $default_locale;

	/**
	 * Current set language
	 * @var String
	 */
	protected $current_lang;

	/**
	 * The supported langs
	 * @var Array
	 */
	protected $supported_langs;

	/**
	 * Class constructor.
	 * @param Array $options - Required options:
	 *     (string) directory
	 *     (string) domain
	 *     (array) supported
	 */
	public function __construct($options = [])
	{
		if (empty($options["domain"]) || empty($options["directory"]) || !is_array($options["supported"]))
			die("GetText Lib -> Invalid options: directory, domain & supported options are required.");

		// set class properties
		$this->default_locale  = self::LOCALES["en"];
		$this->current_lang    = substr($this->default_locale, 0, 2);
		$this->supported_langs = $options["supported"];
		//ss($options);

		$factory = new \Phalcon\Translate\InterpolatorFactory();

		// call parent constructor
		parent::__construct($factory, [
			"locale"        => $this->default_locale,
			"defaultDomain" => $options["domain"],
			"directory"     => $options["directory"],
			"category"      => LC_MESSAGES
		]);
	}

	/**
	 * Sets the current language & GetTex lang files domain
	 * @param String $lang - The new language
	 */
	public function setLanguage($lang = "")
	{
		// validate lang
		if (strlen($lang) > 2 || !in_array($lang, $this->supported_langs))
			$lang = substr($this->default_locale, 0, 2);

		// set new lang
		$this->current_lang = $lang;

		$locale = self::LOCALES[$lang];
		//ss($this->getDefaultDomain(), $this->getCategory(), $this->getDirectory(), $this->current_lang, $locale);

		// set environment vars
		$this->setLocale(LC_ALL, $locale);
	}

	/**
	 * Get current language
	 * @return String
	 */
	public function getLanguage()
	{
		return $this->current_lang;
	}
}
