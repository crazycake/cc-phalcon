<?php
/**
 * Slug Helper for namespaces
 * @link http://cubiq.org/the-perfect-php-clean-url-generator
 */

namespace CrazyCake\Helpers;

/**
 * Slug Helper
 */
class Slug
{
	/**
	 * Creates a slug to be used for pretty URLs
	 * If iconv extension is enabled, replace utf8 chars to ascii.
	 * @param String $str - The input string
	 * @param Array $replace - Placeholders to be replaced
	 * @param String $delimiter - A delimiter
	 * @return Mixed
	 */
	public static function generate($str = "", $replace = [], $delimiter = "-")
	{
		if (empty($str))
			return "";

		$clean = $str;

		if (extension_loaded("iconv"))
			$clean = iconv("UTF-8", "ASCII//TRANSLIT", $str);

		if (!empty($replace))
			$clean = str_replace((array)$replace, " ", $clean);

		$clean = strtolower(preg_replace("/[^a-zA-Z0-9\/_|+ -]/", "", $clean));
		$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
		$clean = trim($clean, $delimiter);

		return $clean;
	}
}
