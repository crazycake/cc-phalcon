<?php
/**
 * Push Notification WebServices
 * Requires Push Manager and & WsCore
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Mobile;

//imports
use Phalcon\Exception;

/**
 * Push Manager
 */
trait PushWebServices
{
    /* static vars */
    protected static $SERVICES = ["apn", "gcm"];

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Phalcon Constructor Event
     */
    protected function onConstruct()
    {
        //call parent construct 1st
        parent::onConstruct();

        //extended error codes
        $this->CODES["3600"] = "push notification error";
        $this->CODES["3601"] = "invalid service";
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * (POST) Subscribe action
     * @return json response
     */
    public function subscribeAction()
    {
        $data = $this->_handleRequestParams([
            "service" => "string",
            "uuid"    => "string",
            "token"   => "string"
        ], "POST");

        try {

            //service validation
            if(!in_array($data["service"], self::$SERVICES))
                $this->_sendJsonResponse(3601);

            //subscribe user
            $this->subscribe($data);
            //send response
            $this->_sendJsonResponse(200, $data);
        }
        catch (Exception $e) {

            $this->_sendJsonResponse(3600, $e->getMessage());
        }
    }

    /**
     * (POST) Send push notification
     * @return json response
     */
    public function sendAction()
    {
        $data = $this->_handleRequestParams([
            "service" => "string",
            "uuids"   => "string",
            "message" => "string",
            "payload" => ""
        ], "POST");

        try {

            //service validation
            if(!in_array($data["service"], self::$SERVICES))
                $this->_sendJsonResponse(3601);

            //send notification
            $response = $this->sendNotification($data);
            //send response
            $this->_sendJsonResponse(200, $response);
        }
        catch (Exception $e) {

            $this->_sendJsonResponse(3600, $e->getMessage());
        }
    }

    /**
     * (POST) Notification received action
     * @return json response
     */
    public function receivedAction()
    {
        $data = $this->_handleRequestParams([
            "service" => "string",
            "uuid"    => "string"
        ], "POST");

        try {

            //service validation
            if(!in_array($data["service"], self::$SERVICES))
                $this->_sendJsonResponse(3601);

            //Notification received
            $this->notificationReceived($data);
            //send response
            $this->_sendJsonResponse(200, $data);
        }
        catch (Exception $e) {

            $this->_sendJsonResponse(3600, $e->getMessage());
        }
    }
}
