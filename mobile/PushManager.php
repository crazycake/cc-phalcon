<?php
/**
 * Push Services Manager
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Mobile;

//imports
use Phalcon\Exception;

/**
 * Push Manager
 */
trait PushManager
{
	/**
     * Set trait configurations
     */
    abstract public function setConfigurations();

    /**
     * Config var
     * @var array
     */
    public $pushConfig;

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

    /**
     * Initializer
     */
    protected function initialize()
    {
        parent::initialize();

        //set libs
        if(isset($this->pushConfig["apn_enabled"]) && $this->pushConfig["apn_enabled"]) {

            //set sandbox mode
            $this->config->app->apn->sandbox = APP_ENVIRONMENT != 'production' ? true : false;
            //set apn client
            $this->apn = new APN((array)$this->config->app->apn);
        }

        if(isset($this->pushConfig["gcm_enabled"]) && $this->pushConfig["gcm_enabled"]) {

            //set gcm client
            $this->gcm = new GCM((array)$this->config->app->gcm);
        }
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Subscribes a device to push notifications
     * @param array $data - The input data array[platform, uuid, token]
     * @return object
     */
    protected function _subscribe($data = array())
    {
        if(!isset($data["platform"]) || !isset($data["uuid"]) || !isset($data["token"]))
            throw new Exception("missing input params");

        //get model class
        $push_class = $this->_getModuleClass('push_notifications');

        //check if subscriber exits
        $subscriber = $push_class::getSubscriber($data["platform"], $data["uuid"]);

        //if exists update data
        if($subscriber)
            $subscriber->update($data);
        else
            $subscriber = (new $push_class())->save($data);

        return $subscriber;
    }

    /**
     * The target device received successfully the notification
     * @param array $data - The input data array[platform, uuid]
     * @return object
     */
    protected function _notificationReceived($data = array())
	{
        if(!isset($data["platform"]) || !isset($data["uuid"]))
            throw new Exception("missing input params");

        //get model class
        $push_class = $this->_getModuleClass('push_notifications');

        //check if subscriber exits
        $subscriber = $push_class::getSubscriber($data["platform"], $data["uuid"]);

		if(!$subscriber)
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
    protected function _getApnFeedbackTokens() {

        return $this->apn->getFeedbackTokens();
    }
}
