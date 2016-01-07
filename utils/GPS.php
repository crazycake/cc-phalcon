<?php
/**
 * GPS helper : Utils for GPS operations.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

/**
 * GPS Helper
 */
class GPS
{
    /* conts */
    const EARTH_RADIUS = 6371000;
    const DEFAULT_ZONE = 'CL';

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

    /**
     * Parse a GPS coordinate with format <degrees.decimal_minutes>, example: -7038.8735 => -70º 38.8735'
     * @access public
     * @static
     * @param float $latitude
     * @param float $longitude
     * @return array [latitude, longitude]
     */
    public static function parseDegreesMinutesCoordinate($latitude = null, $longitude = null, $zone = self::DEFAULT_ZONE)
    {
        if(empty($latitude) || empty($longitude))
            throw new Exception("GPS::parseDegreesMinutesCoordinate -> latitude and longitude params are required");

        //trim both values
        $latitude  = trim($latitude);
        $longitude = trim($longitude);

        //parse coord by zone
        switch ($zone) {
            //Chile, both negative values.
            case 'CL':
                $latitude  = str_replace("-", "", $latitude);
                $longitude = str_replace("-", "", $longitude);

                $lat = explode(".", substr_replace($latitude, ".", 2, 0));
                $lon = explode(".", substr_replace($longitude, ".", 2, 0));

                $lat_minutes = ($lat[1].".".$lat[2])/60;
                $lon_minutes = ($lon[1].".".$lon[2])/60;

                $latitude  = "-" . ($lat[0] + $lat_minutes);
                $longitude = "-" . ($lon[0] + $lon_minutes);

                break;
            default:
                break;
        }

        $coordinate = [(float)number_format($latitude, 6), (float)number_format($longitude, 6)];
        //var_dump($coordinate);exit;

        return $coordinate;
    }
}
