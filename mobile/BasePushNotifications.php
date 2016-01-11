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
    public $service;

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
     * Push Services
     * @var array
     */
    static $SERVICES = ['gcm', 'apn'];

    /**
     * Validation Event
     */
    public function validation()
    {
        $this->validate(new InclusionIn([
            "field"   => "service",
            "domain"  => self::$SERVICES,
            "message" => 'Invalid service. Services supported: '.implode(", ", self::$SERVICES)
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
     * @param string $service - The service.
     * @param string $uuid - The device UUID
     * @return object
     */
    public static function getSubscriber($service, $uuid)
    {
        $conditions = "service = ?1 AND uuid = ?2";
        $parameters = [1 => $service, 2 => $uuid];

        return self::findFirst([$conditions, "bind" => $parameters]);
    }

    /**
     * Gets all subscribed users
     * @static
     * @param string $service - The service.
     * @return resultset
     */
    public static function getSubscribers($service)
    {
        $conditions = "service = ?1";
        $parameters = [1 => $service];

        return self::find([$conditions, "bind" => $parameters]);
    }

    /**
     * Updated payload in case a notification fails
     * @param string $key - The payload key
     * @param string $item - A string value
     * @return string
     */
	public static function updatePayload($payload = array())
	{
        //validate inputs
        if(empty($payload))
			throw new Exception("Payload input is required");

        //1st time
        if(empty($this->payload))
            $this->payload = [];

        //current payload
        $currentPayload = is_string($this->payload) ? json_decode($this->payload) : $this->payload;

        foreach ($payload as $key => $value) {

            if(empty($value))
                return;

            //check keys
            if(array_key_exists($key, $currentPayload)) {

                //set new value
                if(is_bool($value))
                    $currentPayload[$key] = $payload[$key];
                else if(is_numeric($value))
                    $currentPayload[$key] += $payload[$key];
                else if(is_string($value))
                    $currentPayload[$key] .= ",".$payload[$key];
                else if(is_array($value))
                    $currentPayload[$key] = array_merge($currentPayload[$key], $value);
            }
            //check keys exists and non-empty current value
            else {
                $currentPayload[$key] = $value;
            }
        }

        $payload = json_encode($currentPayload, JSON_UNESCAPED_SLASHES);
        //badge counter for new notification payload
        $bagde_counter = ($this->payload == $payload) ? $this->badge_counter : $this->badge_counter+1;
        //save it
        $this->save([
            "payload"       => $payload,
            "bagde_counter" => $bagde_counter
        ]);
        //return payload as array
        return $currentPayload;
	}
}
