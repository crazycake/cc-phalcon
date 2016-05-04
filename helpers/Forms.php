<?php
/**
 * Forms Helper
 * Requires Dates for birthday selector
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
    /* consts */
    const RUT_EXP = "/^[0-9]+-[0-9kK]{1}/";

    /**
     * Validates chilean rut
     * @param string $rut - The input form rut (without points)
     * @return boolean
     */
    public static function validateRut($input_rut = "")
    {
        if (!preg_match(self::RUT_EXP, $input_rut))
            return false;

        $rut = explode("-", $input_rut);

        //checks if rut is valid
        return strtolower($rut[1]) == self::validateRutVD($rut[0]);
    }

    /**
     * Formats price
     * @todo Complete other global currencys formats
     * @static
     * @param numeric $price - The price numeric value
     * @param string $currency - The price currency
     * @return string
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
                break;
            default:
                break;
        }

        return $formatted;
    }

    /**
     * Get birthday options form HTML select element
     * @static
     * @return array
     */
    public static function getBirthdaySelectors()
    {
        //get DI instance (static)
        $di = \Phalcon\DI::getDefault();

        if (!$di->has("trans"))
            throw new Exception("Forms -> no translate service adapter found.");

        $trans = $di->getShared("trans");

        //days
        $days_array = [];
        $days_array[""] = $trans->_("Día");
        //loop
        for ($i = 1; $i <= 31; $i++) {
            $prefix = ($i <= 9) ? "0$i" : "$i";
            $days_array[$prefix] = $i;
        }

        //months
        $months_array = [];
        $months_array[""] = $trans->_("Mes");
        //loop
        for ($i = 1; $i <= 12; $i++) {
            $prefix = ($i <= 9) ? "0$i" : "$i";
            $month = strftime("%m", mktime(0, 0, 0, $i, 1));

            //get abbr month
            $month = Dates::getTranslatedMonthName($month, true);

            //set month array
            $months_array[$prefix] = $month;
        }

        //years
        $years_array = [];
        $years_array[""] = $trans->_("Año");
        //loop
        for ($i = (int) date("Y"); $i >= 1930; $i--)
            $years_array["$i"] = $i;

        return [$years_array, $months_array, $days_array];
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Validates Rut Verification Digit
     * @param  string $R - The input rut without VD
     * @return mixed [int|string]
     */
    private static function validateRutVD($R)
    {
        $M = 0;
        $S = 1;

        for (; $R; $R = floor($R/10))
            $S = ($S + ($R % 10) * (9 - ($M++ % 6))) % 11;

        return $S ? $S - 1 : "k";
    }
}
