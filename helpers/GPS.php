<?php
/**
 * GPS helper : Helper for GPS operations.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\Exception;

/**
 * GPS Helper
 */
class GPS
{
    /* conts */
    const EARTH_RADIUS = 6371000;
    const DEFAULT_ZONE = "CL";

    /**
     * Calculates the great-circle distance between two points, with the Vincenty formula.
     * @access public
     * @static
     * @param float $latitudeFrom - Latitude of start point in [deg decimal]
     * @param float $longitudeFrom - Longitude of start point in [deg decimal]
     * @param float $latitudeTo - Latitude of target point in [deg decimal]
     * @param float $longitudeTo - Longitude of target point in [deg decimal]
     * @param float $earthRadius - Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    public static function vincentyGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = self::EARTH_RADIUS)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo   = deg2rad($latitudeTo);
        $lonTo   = deg2rad($longitudeTo);

        $delta = $lonTo - $lonFrom;
        $a     = pow(cos($latTo) * sin($delta), 2) + pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($delta), 2);
        $b     = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($delta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}
