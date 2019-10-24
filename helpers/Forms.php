<?php
/**
 * Forms Helper
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Helpers;

use Phalcon\Exception;

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
	 * Validates name and filter weird chars from string.
	 * @return Mixed
	 */
	public static function validateName($name = "")
	{
		// validate names
		$nums = "0123456789";

		if (strcspn($name, $nums) != strlen($name))
			return false;

		// remove spaces & format to capitalized name
		return mb_convert_case(ltrim(rtrim($name)), MB_CASE_TITLE, "UTF-8");
	}

	/**
	 * Formats a rut with dots
	 * @param  string $rut - The input rut
	 * @return String
	 */
	public static function formatRut($rut)
	{
		$str = explode("-", $rut);

		return number_format($str[0], 0, "", ".").'-'.strotoupper($str[1]);
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
	 * @todo Complete other global currency formats
	 * @param numeric $price - The price numeric value
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
