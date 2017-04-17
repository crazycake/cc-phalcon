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
     * Initialize Event
     */
    protected function initialize()
    {
        //extended error codes
        $this->RCODES["3600"] = "push notification error";
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * (POST) Subscribe action
     * @return json response
     */
    public function newSubscriber()
    {
        $data = $this->handleRequest([
            "service" => "string",
            "uuid"    => "string",
            "token"   => "string"
        ], "POST");

        try {

            //service validation
            if (!in_array($data["service"], self::$SERVICES))
                $this->jsonResponse(405);

            //subscribe user
            $this->subscribe($data);
            //send response
            $this->jsonResponse(200, $data);
        }
        catch (Exception $e) {

            $this->jsonResponse(3600, $e->getMessage());
        }
    }

    /**
     * (POST) Send push notification
     * @return json response
     */
    public function newPush()
    {
        $data = $this->handleRequest([
            "service" => "string",
            "uuids"   => "string",
            "message" => "string",
            "payload" => ""
        ], "POST");

        try {

            //service validation
            if (!in_array($data["service"], self::$SERVICES))
                $this->jsonResponse(405);

            //send notification
            $response = $this->sendNotification($data);
            //send response
            $this->jsonResponse(200, $response);
        }
        catch (Exception $e) {

            $this->jsonResponse(3600, $e->getMessage());
        }
    }

    /**
     * (POST) Notification received action
     * @return json response
     */
    public function received()
    {
        $data = $this->handleRequest([
            "service" => "string",
            "uuid"    => "string"
        ], "POST");

        try {

            //service validation
            if (!in_array($data["service"], self::$SERVICES))
                $this->jsonResponse(405);

            //Notification received
            $this->notificationReceived($data);
            //send response
            $this->jsonResponse(200, $data);
        }
        catch (Exception $e) {

            $this->jsonResponse(3600, $e->getMessage());
        }
    }
}
