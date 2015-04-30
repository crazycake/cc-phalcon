<?php
/**
 * Form Helper
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
     * @throws \Phalcon\Exception for invalid input
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
