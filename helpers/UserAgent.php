<?php
/**
 * User Agent parser helper, see links for required libraries
 * Requires composer donatj/phpuseragentparser, mobiledetect/mobiledetectlib
 * @link https://github.com/donatj/PhpUserAgent
 * @link https://github.com/serbanghita/Mobile-Detect/
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Mobile_Detect;

/**
 * User Agent Helper
 */
class UserAgent
{
	/**
	 * input User Agent
	 * @var string
	 */
	private $user_agent;

	/**
	 * Constructor
	 * @param string $u_agent - The client user agent
	 */
	public function __construct($u_agent = null)
	{
		$this->user_agent = $u_agent;
	}

	/**
	 * Parses a user agent string into its important parts
	 * @return array an array with browser, version and platform keys
	 */
	public function parseUserAgent()
	{
		//instance both libs
		$mobile_detect = new Mobile_Detect();

		//parse user agent (loaded library method from composer)
		$data = parse_user_agent($this->user_agent);
		//check if is mobile agent
		$data["is_mobile"] = $mobile_detect->isMobile();

		//get the short version
		$short_version = false;

		if ($data["version"]) {
			$array         = explode(".", $data["version"]);
			$short_version = current($array);
		}

		$data["short_version"] = $short_version;
		//check if user agent is legacy
		$data["is_legacy"] = $this->_isUserAgentLegacy($data);

		//var_dump($data, $this->user_agent);exit;
		return $data;
	}

	/* --------------------------------------------------- § -------------------------------------------------------- */

	/**
	 * Check if user agent is legacy
	 * @param array $data - The UA data
	 * @return boolean
	 */
	private function _isUserAgentLegacy($data = [])
	{
		//mark some browsers as legacy
		if (($data["browser"] == "MSIE"    && $data["short_version"] < 11) ||
			($data["browser"] == "Chrome"  && $data["short_version"] < 20) ||
			($data["browser"] == "Firefox" && $data["short_version"] < 20) ||
			($data["browser"] == "Safari"  && $data["short_version"] < 6)  ||
			($data["browser"] == "Opera"   && $data["short_version"] < 6)) {

			return true;
		}

		return false;
	}
}
