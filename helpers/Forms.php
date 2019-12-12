<?php
/**
 * Forms Helper
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Helpers;

/**
 * Form Helper
 */
class Forms
{
	/**
	 * Regex for Rut
	 * @var String
	 */
	const RUT_REGEX = "/^[0-9]+-[0-9kK]{1}/";

	/**
	 * Validates chilean rut
	 * @param String $rut - The input form rut (requires '-' token)
	 * @return Boolean
	 */
	public static function validateRut($rut = "")
	{
		$rut = str_replace(".", "", $rut);

		if (!preg_match(self::RUT_REGEX, $rut))
			return false;

		$rut = explode("-", $rut);

		// checks if rut is valid
		return strtolower($rut[1]) == self::_validateRutVD($rut[0]);
	}

	/**
	 * Formats chilean RUT, with dots too
	 * @param  string $rut - The input rut
	 * @return String
	 */
	public static function formatRut($rut, $dots = false)
	{
		$str = explode("-", strotoupper(trim($rut)));

		return $dots ? number_format($str[0], 0, "", ".")."-".$str[1] : $str[0]."-".$str[1];
	}

	/**
	 * Formats name and filter weird chars from string.
	 * @return Mixed
	 */
	public static function formatName($name = "")
	{
		// validate names
		$nums = "0123456789";

		if (strcspn($name, $nums) != strlen($name))
			return null;

		// remove spaces & format to capitalized name
		return mb_convert_case(ltrim(rtrim($name)), MB_CASE_TITLE, "UTF-8");
	}

	/**
	 * Formats email
	 * @param  string $email - The input email
	 * @return String
	 */
	public static function formatEmail($email)
	{
		$str = explode("@", strtolower(trim($email)));

		return \CrazyCake\Helpers\Slug::translit($str[0] ?? "")."@".\CrazyCake\Helpers\Slug::translit($str[1] ?? "");
	}

	/**
	 * Formats price.
	 * @param Numeric $price - The price numeric value
	 * @param String $currency - The price currency
	 * @return String
	 */
	public static function formatPrice($price, $currency = "CLP")
	{
		$formatted = $price;

		switch ($currency) {

			case "CLP":

				$formatted = str_replace(",", ".", "$".str_replace(".00", "", number_format($formatted))); break;

			case "PEN":

				$formatted = str_replace(",", ".", "S/ ".str_replace(".00", "", number_format($formatted))); break;

			case "USD":

				$formatted = "$".$formatted; break;

			default: break;
		}

		return $formatted;
	}

	/**
	 * Strips URLs from a given string
	 * @param String $str - The input string
	 * @param String $replace - The replace string
	 * @return String
	 */
	public static function stripUrls($str = "", $replace = "")
	{
		return preg_replace('/\b((https?|ftp|file):\/\/|www\.)[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', $replace, $str);
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Validates Rut Verification Digit
	 * @param String $R - The input rut without VD
	 * @return Mixed
	 */
	private static function _validateRutVD($R)
	{
		$M = 0;
		$S = 1;

		for (; $R; $R = floor($R/10))
			$S = ($S + ($R % 10) * (9 - ($M++ % 6))) % 11;

		return $S ? $S - 1 : "k";
	}
}
