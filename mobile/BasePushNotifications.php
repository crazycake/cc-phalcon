<?php
/**
 * Base Push Notifications Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Mobile;

//imports
use Phalcon\Mvc\Model\Validator\InclusionIn;
use Phalcon\Exception;

/**
 * Base Push Notification Model
 */
class BasePushNotifications extends \CrazyCake\Models\Base
{

    /* properties */

    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string
     */
    public $platform;

    /**
     * @var string
     */
    public $token;

    /**
     * @var int
     * Badge counter for iOS
     */
    public $badge_counter;

    /**
     * @var string
     */
    public $payload;

    /**
     * @var string
     */
    public $created_at;

    /* inclusion vars */

    /**
     * @var array
     */
    static $PLATFORMS = ['ios', 'android'];

    /**
     * Validation Event
     */
    public function validation()
    {
        $this->validate(new InclusionIn([
            "field"   => "platform",
            "domain"  => self::$PLATFORMS,
            "message" => 'Invalid platform. Platforms supported: '.implode(", ", self::$PLATFORMS)
        ]));

        //check validations
        if ($this->validationHasFailed() == true)
            return false;
    }

    /**
     * Before Validation Event [onCreate]
     */
    public function beforeValidationOnCreate()
    {
        //set default values
        $this->badge_counter = 0;
        $this->payload       = null;
        $this->created_at    = date("Y-m-d H:i:s");
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Gets a subscribed user
     * @static
     * @param string $platform - The platform [ios, android]
     * @param string $uuid - The device UUID
     * @return object
     */
    public static function getSubscriber($platform, $uuid)
    {
        $conditions = "platform = ?1 AND uuid = ?2";
        $parameters = [1 => $platform, 2 => $uuid];

        return self::findFirst([$conditions, "bind" => $parameters]);
    }

    /**
     * Gets all subscribed users
     * @static
     * @param string $platform - The platform [ios, android]
     * @return resultset
     */
    public static function getSubscribers($platform)
    {
        $conditions = "platform = ?1";
        $parameters = [1 => $platform];

        return self::find([$conditions, "bind" => $parameters]);
    }

    /**
     * Concatenate payload
     * @param string $payload - The object current payload
     * @param string $item - A string item
     * @param string $delimeter - The explode delimeter (optional)
     * @return string
     */
	public static function concatPayload($payload = "", $item = "", $delimeter = ",")
	{
	    //parse payload
		$payload_items = explode($delimeter, $payload);

        //validate items
        if(empty($payload_items) || in_array($item, $payload_items))
			return $payload;

        //append item
		array_push($payload_items, $item);
		$payload_items = array_unique($payload_items);        //remove duplicated entries
		$payload 	   = implode($delimeter, $payload_items); //string concatenated by delimiter
		//s($payload);exit;

		return $payload;
	}
}
