<?php
/**
 * JSON Helper
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Helpers;

/**
 * JSON Helper
 */
class JSON
{
	/**
	 * Safe Encodes Json with NAN and INF support
	 * @param Mixed $data - Serializable data
	 * @return String
	 */
	public static function safeEncode($data = "")
	{
		$output = json_encode(unserialize(str_replace(["d:NAN;", "d:INF;"], "d:000;", serialize($data))), JSON_UNESCAPED_SLASHES);

		if (json_last_error()) throw new \Exception("JSON Encode error: ".json_last_error_msg());

		return $output;
	}
}
