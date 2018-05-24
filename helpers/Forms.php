<?php
/**
 * Forms Helper
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
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
	 * @param String $input_rut - The input form rut (requires '-' token)
	 * @return Boolean
	 */
	public static function validateRut($input_rut = "")
	{
		$input_rut = str_replace(".", "", $input_rut);

		if (!preg_match(self::RUT_REGEX, $input_rut))
			return false;

		$rut = explode("-", $input_rut);

		//checks if rut is valid
		return strtolower($rut[1]) == self::_validateRutVD($rut[0]);
	}

	/**
	 * Validates name and filter weird chars from string.
	 * @return Mixed
	 */
	public static function validateName($name = "")
	{
		//validate names
		$nums = "0123456789";

		if (strcspn($name, $nums) != strlen($name))
			return false;

		//format to capitalized name
		return mb_convert_case($name, MB_CASE_TITLE, "UTF-8");
	}

	/**
	 * Formats a rut
	 * @param  string $rut - The input rut
	 * @return String
	 */
	public static function formatRut($rut)
	{
		$str = explode("-", $rut);
		return number_format($str[0], 0, "", ".").'-'.$str[1];
	}

	/**
	 * Formats price.
	 * @todo Complete other global currency formats
	 * @param numeric $price - The price numeric value
	 * @param String $currency - The price currency
	 * @return String
	 */
	public static function formatPrice($price, $currency)
	{
		$formatted = $price;

		switch ($currency) {

			case "CLP":
				$formatted = "$".str_replace(".00", "", number_format($formatted));
				$formatted = str_replace(",", ".", $formatted);
				break;

			case "USD":
				$formatted = "$".$formatted;
				break;

			default:
				break;
		}

		return $formatted;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Validates Rut Verification Digit
	 * @param String $R - The input rut without VD
	 * @return Mixed [int|string]
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
