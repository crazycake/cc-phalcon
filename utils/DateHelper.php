<?php
/**
 * Date Helper
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

class DateHelper
{
    /**
     * Returns days passed from Now and given date
     * @static
     * @param date $date with Format Y-m-d H:i:s or a DateTime object
     * @return int
     */
    public static function getDaysPassedFromDate($date = null)
    {
        //validation
        if (is_null($date))
            throw new Exception("DateHelper::getDaysPassedFromDate -> invalid date parameter, must be string or DateTime object.");

        //check if is a DateTime object
        $target_date = null;
        if ($date instanceof \DateTime)
            $target_date = $date;
        else
            $target_date = new \DateTime($date);

        //check days passed
        $today = new \DateTime("now");
        //calculate diff dates
        $interval = $today->diff($target_date);

        //return days property
        return $interval->days;
    } 

    /**
     * Get Translated Month Name, abbreviation support
     * @static
     * @param  mixed  $month      Month int or string, example: 01, 08, 11
     * @param  boolean $abbr      Option for month name abbreviated
     * @param  object $translate  The DI Gettext translation adapter reference
     * @return string             The translated month name
     */
    public static function getTranslatedMonthName($month = null, $abbr = false, $translate)
    {
        if(empty($month) || empty($translate))
            throw new Exception("DateHelper::getTranslatedMonthName -> 'month' and 'translate' params are required");

        $month = (int)$month;

        //get translated month name
        switch ($month) {
            case 1: $month  = ($abbr) ? $translate->_("Ene") : $translate->_("Enero"); break;
            case 2: $month  = ($abbr) ? $translate->_("Feb") : $translate->_("Febrero"); break;
            case 3: $month  = ($abbr) ? $translate->_("Mar") : $translate->_("Marzo"); break;
            case 4: $month  = ($abbr) ? $translate->_("Abr") : $translate->_("Abril"); break;
            case 5: $month  = ($abbr) ? $translate->_("May") : $translate->_("Mayo"); break;
            case 6: $month  = ($abbr) ? $translate->_("Jun") : $translate->_("Junio"); break;
            case 7: $month  = ($abbr) ? $translate->_("Jul") : $translate->_("Julio"); break;
            case 8: $month  = ($abbr) ? $translate->_("Ago") : $translate->_("Agosto"); break;
            case 9: $month  = ($abbr) ? $translate->_("Sep") : $translate->_("Septiembre"); break;
            case 10: $month = ($abbr) ? $translate->_("Oct") : $translate->_("Octubre"); break;
            case 11: $month = ($abbr) ? $translate->_("Nov") : $translate->_("Noviembre"); break;
            case 12: $month = ($abbr) ? $translate->_("Dic") : $translate->_("Diciembre"); break;
            default: break;
        }
        
        return $month;
    }

    /**
     * Format seconds to HH:MM style, example 23:45 or 23h 45m
     * @static
     * @param int $seconds
     * @param boolean $human For human style format
     * @return string
     */
    public static function formatSecondsToHHMM($seconds, $human = true)
    {
        $hours = floor($seconds / 3600);
        $mins  = floor(($seconds / 60) % 60);

        return $human ? $hours."h ".$mins."m" : $hours.":".$mins;       
    }
}
