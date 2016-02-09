<?php
/**
 * Date Helper
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

/**
 * Date Helper
 */
class DateHelper
{
    /**
     * Returns daytime passed from Now and given date
     * @static
     * @param date $date - Format Y-m-d H:i:s or a DateTime object
     * @param string $f - The interval unit datetime, default is days.
     * @return int
     */
    public static function getTimePassedFromDate($date = null, $f = "days")
    {
        //validation
        if (is_null($date))
            throw new Exception("DateHelper::getTimePassedFromDate -> invalid date parameter, must be string or DateTime object.");

        //check if is a DateTime object
        $target_date = $date instanceof \DateTime ? $date : new \DateTime($date);

        //check days passed
        $today = new \DateTime("now");
        //calculate diff dates
        $interval = $today->diff($target_date);

        //return days property
        return $interval->$f;
    }

    /**
     * Returns time difference from two input dates
     * @static
     * @param date $date1 - Format Y-m-d H:i:s or a DateTime object
     * @param date $date2 - Format Y-m-d H:i:s or a DateTime object
     * @param string $f - The interval unit datetime, default is hours.
     * @return int
     */
    public static function getTimeDiff($date1 = null, $date2 = null, $f = "h")
    {
        //validation
        if (is_null($date1) || is_null($date2) )
            throw new Exception("DateHelper::getTimeDiff -> both input dates must be string or DateTime objects.");

        //check if is a DateTime object
        $date1 = $date1 instanceof \DateTime ? $date1 : new \DateTime($date1);
        $date2 = $date2 instanceof \DateTime ? $date2 : new \DateTime($date2);

        //calculate diff dates
        $interval = $date1->diff($date2);

        //return days property
        return $interval->$f;
    }

    /**
     * Get Translated Month Name, abbreviation support
     * @static
     * @param mixed [int|string] $month - Month value, example: 01, 08, 11
     * @param boolean $abbr - Option for month name abbreviated
     * @return string - The translated month name
     */
    public static function getTranslatedMonthName($month = null, $abbr = false)
    {
        if(empty($month))
            throw new Exception("DateHelper::getTranslatedMonthName -> 'month' is required");

        //get DI instance (static)
        $di = \Phalcon\DI::getDefault();

        if(!$di->has("trans"))
            return $month;

        $trans = $di->getShared("trans");

        //set month
        $month = (int)$month;

        //get translated month name
        switch ($month) {
            case 1: $month  = ($abbr) ? $trans->_("Ene") : $trans->_("Enero"); break;
            case 2: $month  = ($abbr) ? $trans->_("Feb") : $trans->_("Febrero"); break;
            case 3: $month  = ($abbr) ? $trans->_("Mar") : $trans->_("Marzo"); break;
            case 4: $month  = ($abbr) ? $trans->_("Abr") : $trans->_("Abril"); break;
            case 5: $month  = ($abbr) ? $trans->_("May") : $trans->_("Mayo"); break;
            case 6: $month  = ($abbr) ? $trans->_("Jun") : $trans->_("Junio"); break;
            case 7: $month  = ($abbr) ? $trans->_("Jul") : $trans->_("Julio"); break;
            case 8: $month  = ($abbr) ? $trans->_("Ago") : $trans->_("Agosto"); break;
            case 9: $month  = ($abbr) ? $trans->_("Sep") : $trans->_("Septiembre"); break;
            case 10: $month = ($abbr) ? $trans->_("Oct") : $trans->_("Octubre"); break;
            case 11: $month = ($abbr) ? $trans->_("Nov") : $trans->_("Noviembre"); break;
            case 12: $month = ($abbr) ? $trans->_("Dic") : $trans->_("Diciembre"); break;
            default: break;
        }

        return $month;
    }

    /**
     * Get Translated Date, default translation (spanish)
     * @static
     * @param int $year - The year (optional)
     * @param mixed $month - Month int or string, example: 01, 11 (required)
     * @param mixed $day - Day int or string. (optional)
     * @param string $time - The time, no format is required. (optional)
     * @return string - The translated Date Time
     */
    public static function getTranslatedDateTime($year = "", $month = "", $day = "", $time = "")
    {
        if(empty($month))
            throw new Exception("DateHelper::getTranslatedDateTime -> month is required.");

        //get DI instance (static)
        $di = \Phalcon\DI::getDefault();

        if(!$di->has("trans"))
            return $day."-".$month."-".$year;

        $trans = $di->getShared("trans");

        //get translated Month
        $month = self::getTranslatedMonthName($month, false);

        //format with year & month
        if(empty($day)) {
            return $trans->_("%month% del %year%", [
                "month" => $month,
                "year"  => $year
            ]);
        }

        //format with day & month & time
        if(empty($year) && !empty($day) && !empty($time)) {
            return $trans->_("%day% de %month%, a las %time% hrs.", [
                "day"   => $day,
                "month" => $month,
                "time"  => $time
            ]);
        }

        //format with month & day
        if(empty($year)) {
            return $trans->_("%day% %month%", [
                "day"   => $day,
                "month" => $month
            ]);
        }

        //format with year, month & day
        if(empty($time)) {
            return $trans->_("%day% de %month% del %year%", [
                "day"   => $day,
                "month" => $month,
                "year"  => $year
            ]);
        }

        //format with year, month, day & time
        return $trans->_("%day% de %month% del %year%, a las %time hrs.", [
            "day"   => $day,
            "month" => $month,
            "year"  => $year,
            "time"  => $time
        ]);
    }

    /**
     * Get translated current date
     * @static
     * @return string - The translated date
     */
    public static function getTranslatedCurrentDate()
    {
        $date = date("Y-m-d");
        $date = explode("-", $date);

        return self::getTranslatedDateTime($date[0], $date[1], $date[2]);
    }

    /**
     * Format seconds to HH:MM style, example 23:45 or 23h 45m
     * @static
     * @param int $seconds
     * @param boolean $human - For human style format
     * @return string
     */
    public static function formatSecondsToHHMM($seconds, $human = true)
    {
        $hours = floor($seconds / 3600);
        $mins  = floor(($seconds / 60) % 60);

        return $human ? $hours."h ".$mins."m" : $hours.":".$mins;
    }
}
