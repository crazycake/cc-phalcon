<?php
/**
 * User Agent parser helper, see links for required libraries
 * Requires composer donatj/phpuseragentparser, mobiledetect/mobiledetectlib
 * @link https://github.com/donatj/PhpUserAgent
 * @link https://github.com/serbanghita/Mobile-Detect/
 * @link https://github.com/JayBizzle/Crawler-Detect
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Helpers;

use Mobile_Detect;

/**
 * User Agent Helper
 */
class UserAgent
{
	/**
	 * User Agent
	 * @var String
	 */
	private $user_agent;

	/**
	 * Constructor
	 * @param String $agent - The client user agent
	 */
	public function __construct($agent = null)
	{
		$this->user_agent = $agent;
	}

	/**
	 * Parses a user agent string into its important parts
	 * @return Array an array with browser, version and platform keys
	 */
	public function parseUserAgent()
	{
		// parse user agent (loaded library method from composer)
		$data = \donatj\UserAgent\parse_user_agent($this->user_agent);

		// get the short version
		$short_version = $data["version"] ? current(explode(".", $data["version"])) : false;

		$data["short_version"] = $short_version;
		$data["is_mobile"]     = (new Mobile_Detect())->isMobile();
		$data["is_crawler"]    = (new \Jaybizzle\CrawlerDetect\CrawlerDetect())->isCrawler();
		$data["is_legacy"]     = $this->_isUserAgentLegacy($data);

		// special cases
		$data["is_analyser"] = !empty(preg_match('/prerendercrawler|chrome-lighthouse|gtmetrix|ptst/i', $this->user_agent));

		return $data;
	}

	/**
	 * Check if user agent is legacy
	 * @param Array $data - The UA data
	 * @return Boolean
	 */
	private function _isUserAgentLegacy($data = [])
	{
		return (
			($data["browser"] == "MSIE"    && $data["short_version"] < 11) ||
			($data["browser"] == "Chrome"  && $data["short_version"] < 20) ||
			($data["browser"] == "Firefox" && $data["short_version"] < 20) ||
			($data["browser"] == "Safari"  && $data["short_version"] < 6)  ||
			($data["browser"] == "Opera"   && $data["short_version"] < 6)
		);
	}
}
