<?php
/**
 * Push Services Manager
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Mobile;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;

/**
 * Push Manager
 */
trait PushManager
{
    /**
     * The APN service
     * @var object
     */
    public $apn;

    /**
     * The GCM service
     * @var object
     */
    public $gcm;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Subscribes a device to push notifications
     * @param array $data - The input data array[service, uuid, token]
     * @return object
     */
    protected function subscribe($data = [])
    {
        if (!isset($data["service"]) || !isset($data["uuid"]) || !isset($data["token"]))
            throw new Exception("missing input params");

        //get model class
        $push_class = AppModule::getClass("push_notification");

        //check if subscriber exits
        $subscriber = $push_class::getSubscriber($data["service"], $data["uuid"]);

        //if exists update data
        if ($subscriber) {

            //update object
            $subscriber->update($data);

            //check for pending push notification
            if (!empty($subscriber->payload)) {

                $this->sendNotification([
                    "uuids"   => $data["uuid"],
                    "service" => $data["service"],
                    "message" => $this->config->app->name." pending notification.",
                    "payload" => $subscriber->payload
                ]);
            }
        }
        else {
            $subscriber = (new $push_class())->save($data);
        }

        return $subscriber;
    }

    /**
     * Sends a push notification
     * @param mixed $data["subscribers"] - A input array with UUIDs
     * @return response
     */
    protected function sendNotification($data)
    {
        if (empty($data["uuids"]) || empty($data["service"]) || empty($data["message"]) || empty($data["payload"]) )
            throw new Exception("missing input params: uuid, service, message, payload.");

        //set uuids
        if (is_string($data["uuids"]))
            $data["uuids"] = explode(",", trim($data["uuids"]));

        //set method
        $push = "_sendNotification".$data["service"];
		//get response [reflection]
		return $this->$push($data);
    }

    /**
     * The target device received successfully the notification
     * @param array $data - The input data array[service, uuid]
     * @return object
     */
    protected function notificationReceived($data = [])
	{
        if (!isset($data["service"]) || !isset($data["uuid"]))
            throw new Exception("missing input params");

        //get model class
        $push_class = AppModule::getClass("push_notification");

        //check if subscriber exits
        $subscriber = $push_class::getSubscriber($data["service"], $data["uuid"]);

		if (!$subscriber)
            throw new Exception("subscriber not found");

		//update ORM
        $subscriber->update([
            "badge_counter" => 0,
            "payload"       => null
        ]);

        return $subscriber;
	}

    /**
     * Get APN devices on which app is not installed anymore
     * Response struct: timestamp => 1340270617, length => 32, devtoken => 002bdf9985984f0b774e78f256eb6e6c6e5c576d3a0c8f1fd8ef9eb2c4499cb4
     * @return array
     */
    protected function getApnFeedbackTokens() {

        return $this->apn->getFeedbackTokens();
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Set Push Notification client
     * @param  string $service - The service: gcm or apn
     */
    private function _setClient($service = "")
    {
        //set clients
        switch ($service) {

            case "apn":
                //set sandbox mode
                $this->config->app->apn->sandbox = APP_ENV != "production" ? true : false;
                //set apn client
                $this->apn = new APN((array)$this->config->app->apn);
                break;

            case "gcm":
                //set gcm client
                $this->gcm = new GCM((array)$this->config->app->gcm);
                break;

            default:
                break;
        }
    }

    /**
     * Sends a push notification through APN Service
     * @param  array $data - The payload data array
     */
    private function _sendNotificationAPN($data = [])
    {
        //set client
        $this->_setClient("apn");

        //get model class
        $push_class = AppModule::getClass("push_notification");
        //set response data
        $successful_delivers = 0;
		$failed_delivers 	 = 0;

        //APN library config
    	$this->apn->payloadMethod = "enhance";
    	//open connection
    	$this->apn->connectToPush();

        foreach ($data["uuids"] as $uuid) {

            //get subscriber
            $subscriber = $push_class::getSubscriber("apn", $uuid);

            if (!$subscriber)
                throw new Exception("APN subscriber $uuid not found");

            //badge & payload logic
            $subscriber->updatePayload($data["payload"]);

            //set payload for notification
            $this->apn->setData(["payload" => $subscriber->payload]);
			//send message
			$response = $this->apn->sendMessage($subscriber->token,
												$data["message"],
												$subscriber->badge_counter,  //icon badge number
												"default"				     //default sound
												);

            //log action
            if (APP_ENV != "production")
                $this->logger->debug("PushManager::_sendNotificationAPN -> new push to $uuid: ".$subscriber->payload);

            //handle response
            if ($response) {
                $successful_delivers++;
			}
			else {

				$this->logger->error("PushManager -> APN onNotificationSent error: ".$this->apn->error.", token: ".$subscriber->token.", uuid: ".$uuid);
				$failed_delivers++;
			}
        }

        //send response
        return [
            "success" => $successful_delivers,
            "failed"  => $failed_delivers
        ];
    }

    /**
     * Sends a push notification through APN Service
     * @param  array $data - The payload data array
     */
    private function _sendNotificationGCM($data = [])
    {
        //set client
        $this->_setClient("gcm");

        //get model class
        $push_class = AppModule::getClass("push_notification");
        //set response data
        $successful_delivers = 0;
		$failed_delivers 	 = 0;

        //GCM library config
		$this->gcm->setMessage($data["message"]);
		//set time to live
		$this->gcm->setTtl(1500);
    	//and unset in further
        $this->gcm->setTtl(false);
        //set group to default
        $this->gcm->setGroup(false);

        foreach ($data["uuids"] as $uuid) {

            //get subscriber
            $subscriber = $push_class::getSubscriber("gcm", $uuid);

            if (!$subscriber)
                throw new Exception("GCM subscriber $uuid not found");

            //badge & payload logic
            $subscriber->updatePayload($data["payload"]);

            //clean & add recepients
			$this->gcm->clearRecepients();
        	$this->gcm->addRecepient($subscriber->token);
        	//set payload for notification
			$this->gcm->setData(["payload" => $subscriber->payload]);
			//send message
			$response = $this->gcm->send();

            //log action
            if (APP_ENV != "production")
                $this->logger->debug("PushManager::_sendNotificationGCM -> new push to $uuid: ".$subscriber->payload);

            //handle response
            if ($response) {
                $successful_delivers++;
				continue;
			}

            //output data to string
			$gcm_send_status = $this->gcm->status;
			//$gcm_msg_status  = $this->gcm->messagesStatuses;
			//set data to print as as string
			if (is_array($gcm_send_status))
				$gcm_send_status = implode(";", $gcm_send_status);

			$failed_delivers++;

			$this->logger->error("PushManager -> GCM onNotificationSent error: $gcm_send_status, token: ".$subscriber->token.", uuid: ".$uuid);
        }

        //send response
        return [
            "success" => $successful_delivers,
            "failed"  => $failed_delivers
        ];
    }
}
