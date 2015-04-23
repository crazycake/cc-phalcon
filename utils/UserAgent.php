<?php
/**
 * User Agent parser helper
 * @author Jesse G. Donat <donatj@gmail.com>
 * @contributor Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

class UserAgent
{
    /**
     * input User Agent
     * @var string
     */
    private $user_agent;

    /**
     * Constructor
     * @param string $u_agent The client user agent
     */
    public function __construct($u_agent = null)
    {
        $this->user_agent = $u_agent;
    }

    /**
     * Parses a user agent string into its important parts
     *
     * @author Jesse G. Donat
     * @link https://github.com/donatj/PhpUserAgent
     * @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
     * @throws InvalidArgumentException on not having a proper user agent to parse.
     * @return array an array with browser, version and platform keys
     */
    public function parseUserAgent()
    {
        $u_agent = $this->user_agent;

        if (is_null($u_agent)) {
            //check user agent
            if (isset($_SERVER['HTTP_USER_AGENT']))
                $u_agent = $_SERVER['HTTP_USER_AGENT'];
            else
                throw new Exception('UserAgent Lib -> parseUserAgent method requires a User Agent string');
        }

        $platform = null;
        $browser  = null;
        $version  = null;
        $mobile   = null;

        $empty = array('platform' => $platform, 'browser' => $browser, 'version' => $version, 'mobile' => $mobile);

        //validate user agent
        if (!$u_agent)
            return $empty;

        //check if is mobile agent
        $mobile = preg_match('!(tablet|pad|mobile|phone|symbian|android|ipod|ios|blackberry|webos)!i', $u_agent) ? true : false;

        if (preg_match('/\((.*?)\)/im', $u_agent, $parent_matches)) {

            preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|iPhone|iPad|Linux|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|(New\ )?Nintendo\ (WiiU?|3DS)|Xbox(\ One)?)
					(?:\ [^;]*)?
					(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);

            $priority           = array('Android', 'Xbox One', 'Xbox');
            $result['platform'] = array_unique($result['platform']);

            if (count($result['platform']) > 1) {

                if ($keys = array_intersect($priority, $result['platform']))
                    $platform = reset($keys);
                else
                    $platform = $result['platform'][0];

            }
            elseif (isset($result['platform'][0])) {
                $platform = $result['platform'][0];
            }
        }

        //special cases
        if ($platform == 'linux-gnu')
            $platform = 'Linux';
        elseif ($platform == 'CrOS')
            $platform = 'Chrome OS';

        //preg match
        preg_match_all('%(?P<browser>Camino|Kindle(\ Fire\ Build)?|Firefox|Iceweasel|Safari|MSIE|Trident/.*rv|AppleWebKit|
                Chrome|IEMobile|Opera|OPR|Silk|Midori|Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
                $u_agent, $result, PREG_PATTERN_ORDER);

        // If nothing matched, return null (to avoid undefined index errors)
        if (!isset($result['browser'][0]) || !isset($result['version'][0])) {

            if (!$platform && preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?([;| ]\ ?.*)?$%ix', $u_agent, $result))
                return array('platform' => null, 'browser' => $result['browser'], 'version' => isset($result['version']) ? $result['version'] ?: null:null, 'mobile' => false);

            return $empty;
        }

        //set data
        $browser = $result['browser'][0];
        $version = $result['version'][0];

        //set function
        $find = function ($search, &$key) use ($result) {

            $xkey = array_search(strtolower($search), array_map('strtolower', $result['browser']));

            if ($xkey !== false) {
                $key = $xkey;

                return true;
            }

            return false;
        };

        /* browser special cases */
        $key = 0;
        if ($browser == 'Iceweasel') {
            $browser = 'Firefox';
        }
        elseif ($find('Playstation Vita', $key)) {
            $platform = 'PlayStation Vita';
            $browser  = 'Browser';
        }
        elseif ($find('Kindle Fire Build', $key) || $find('Silk', $key)) {
            $browser  = $result['browser'][$key] == 'Silk' ? 'Silk' : 'Kindle';
            $platform = 'Kindle Fire';

            if (!($version = $result['version'][$key]) || !is_numeric($version[0]))
                $version = $result['version'][array_search('Version', $result['browser'])];

        }
        elseif ($find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS') {
            $browser = 'NintendoBrowser';
            $version = $result['version'][$key];
        }
        elseif ($find('Kindle', $key)) {
            $browser  = $result['browser'][$key];
            $platform = 'Kindle';
            $version  = $result['version'][$key];
        }
        elseif ($find('OPR', $key)) {
            $browser = 'Opera Next';
            $version = $result['version'][$key];
        }
        elseif ($find('Opera', $key)) {
            $browser = 'Opera';
            $find('Version', $key);
            $version = $result['version'][$key];
        }
        elseif ($find('Midori', $key)) {
            $browser = 'Midori';
            $version = $result['version'][$key];
        }
        elseif ($browser == 'MSIE' || strpos($browser, 'Trident') !== false) {

            if ($find('IEMobile', $key)) {
                $browser = 'IEMobile';
            }
            else {
                $browser = 'MSIE';
                $key     = 0;
            }
            $version = $result['version'][$key];

        }
        elseif ($find('Chrome', $key)) {
            $browser = 'Chrome';
            $version = $result['version'][$key];
        }
        elseif ($browser == 'AppleWebKit') {

            if (($platform == 'Android' && !($key = 0))) {
                $browser = 'Android Browser';
            }
            elseif (strpos($platform, 'BB') === 0) {
                $browser  = 'BlackBerry Browser';
                $platform = 'BlackBerry';
            }
            elseif ($platform == 'BlackBerry' || $platform == 'PlayBook') {
                $browser = 'BlackBerry Browser';
            }
            elseif ($find('Safari', $key)) {
                $browser = 'Safari';
                //casos especiales de Safari
                if(strpos($u_agent, 'CriOS') !== false)
                    $browser = 'Chrome';
            }

            $find('Version', $key);
            //set version
            $version = $result['version'][$key];
        }
        elseif ($key = preg_grep('/playstation \d/i', array_map('strtolower', $result['browser']))) {

            $key = reset($key);
            $platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $key);
            $browser  = 'NetFront';
        }

        //get the short version
        $short_version = false;
        if ($version) {
            $array         = explode(".", $version);
            $short_version = current($array);
            $array         = null;
        }

        return array(
            'platform'      => $platform ?: null,
            'browser'       => $browser ?: null,
            'version'       => $version ?: null,
            'short_version' => $short_version ?: null,
            'mobile'        => $mobile ?: false
        );
    }
}
