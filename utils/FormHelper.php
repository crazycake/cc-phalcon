<?php
/**
 * Form Helper
 * Requires DateHelper for bday selector
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

class FormHelper
{
    /* consts */
    const RUT_EXP = "/^[0-9]+-[0-9kK]{1}/";

    /**
     * Validates chilean rut
     * @param string $rut The input form rut (without points)
     * @return boolean
     */
    public static function validateRut($input_rut = "")
    {
        if (!preg_match(self::RUT_EXP, $input_rut))
            return false;

        $rut = explode('-', $input_rut);

        //checks if rut is valid
        return strtolower($rut[1]) == self::validateRutVD($rut[0]);
    }

    /**
     * Formats price
     * @todo Complete other global coins formats
     * @static
     * @param numeric $price
     * @param string $coin
     * @return string
     */
    public static function formatPrice($price, $coin)
    {
        $formatted = $price;

        switch ($coin) {
            case 'CLP':
                $formatted = "$".str_replace(".00", "", number_format($formatted));
                $formatted = str_replace(",", ".", $formatted);
                break;
            case 'USD':
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

        if(!$di->has("translate"))
            throw new Exception("FormHelper -> no translate service adapter found.");

        $translate = $di->getShared("translate");

        //days
        $days_array = array();
        $days_array["0"] = $translate->_("Día");
        //loop
        for ($i = 1; $i <= 31; $i++) {
            $prefix = ($i <= 9) ? "_0$i" : "_$i";
            $days_array[$prefix] = $i;
        }

        //months
        $months_array = array();
        $months_array["0"] = $translate->_("Mes");
        //loop
        for ($i = 1; $i <= 12; $i++) {
            $prefix = ($i <= 9) ? "_0$i" : "_$i";
            $month = strftime('%m', mktime(0, 0, 0, $i, 1));

            //get abbr month
            if(class_exists("DateHelper")) {
                $month = DateHelper::getTranslatedMonthName($month, true);
            }

            //set month array
            $months_array[$prefix] = $month;
        }

        //years
        $years_array = array();
        $years_array["0"] = $translate->_("Año");
        //loop
        for ($i = (int) date('Y'); $i >= 1914; $i--)
            $years_array["_$i"] = $i;

        return array($years_array, $months_array, $days_array);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Validates Rut Verification Digit
     * @param  string $T [description]
     * @return mixed (int or string)
     */
    private static function validateRutVD($T)
    {
        $M = 0;
        $S = 1;

        for(; $T; $T = floor($T/10))
            $S = ($S + ($T % 10) * (9 - ($M++ % 6))) % 11;

        return $S ? $S - 1 : 'k';
    }
}
