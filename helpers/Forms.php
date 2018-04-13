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

	/**
	 * Get birthday options form HTML select element
	 * @return Array
	 */
	public static function getBirthdaySelectors()
	{
		$trans = (\Phalcon\DI::getDefault())->getShared("trans");

		//days
		$days_array     = [];
		$days_array[""] = $trans->_("Día");
		
		for ($i = 1; $i <= 31; $i++) {
			$prefix = ($i <= 9) ? "0$i" : "$i";
			$days_array[$prefix] = $i;
		}

		//months
		$months_array     = [];
		$months_array[""] = $trans->_("Mes");
		
		for ($i = 1; $i <= 12; $i++) {

			$prefix = ($i <= 9) ? "0$i" : "$i";
			//set month array
			$months_array[$prefix] = ucfirst(strftime("%b", mktime(0, 0, 0, $i, 1)));
		}

		//years
		$years_array     = [];
		$years_array[""] = $trans->_("Año");
		
		for ($i = (int)date("Y") - 5; $i >= 1930; $i--)
			$years_array["$i"] = $i;

		return [$years_array, $months_array, $days_array];
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
