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
     * @param boolean $as_string - If true, result will be uuids concatenated as string with ',' delimeter.
     * @return mixed resultset|string
     */
    public static function getSubscribers($service, $as_string = false)
    {
        $conditions = "service = ?1";
        $parameters = [1 => $service];

        $subscribers = self::find([$conditions, "bind" => $parameters]);

        if(!$as_string)
            return $subscribers;

        $uuids = \CrazyCake\Models\BaseResultset::getIdsArray($subscribers, "uuid");

        return $uuids ? implode(",", $uuids) : false;
    }

    /**
     * Updated payload in case a notification fails
     * @param mixed [string|array] $payload - The input payload
     * @return string
     */
	public function updatePayload($payload)
	{
        //set payload as array
        $payload = is_string($payload) ? json_decode($payload, true) : (array)$payload;
        //current payload
        $currentPayload = is_string($this->payload) ? json_decode($this->payload, true) : [];

        //validate inputs
        if(empty($payload))
			throw new Exception("Payload input is required");

        //update payload

        if(is_array($currentPayload)) {
            array_push($currentPayload, $payload);
        }
        else if(is_null($currentPayload)) {
            $currentPayload = [];
            array_push($currentPayload, $payload);
        }
        else {
            $obj = $currentPayload;
            //creates a new array
            $currentPayload = [];
            array_push($currentPayload, $obj);
            array_push($currentPayload, $payload);
        }

        $payload = json_encode($currentPayload, JSON_UNESCAPED_SLASHES);
        //badge counter for new notification payload
        $bagde_counter = ($this->payload == $payload) ? (int)$this->badge_counter : $this->badge_counter+1;
        //save it
        $this->save([
            "payload"       => $payload,
            "bagde_counter" => $bagde_counter
        ]);
        //update instanced object
        $this->payload = $payload;
	}
}
