<?php
/**
 * GCM (Google Cloud Messaging)
 * @copyright (c) 2012 AntonGorodezkiy
 * @license GNU/GPL 2
 * @link https://github.com/antongorodezkiy/codeigniter-gcm/
 * @author Phalcon integration - Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Mobile;

//imports
use Phalcon\DI;

/**
 * GCM client
 */
class GCM {

	//consts
	const API_SEND_ADDRESS = 'https://android.googleapis.com/gcm/send';

    protected $apiKey         = '';
    protected $payload        = array();
    protected $additionalData = array();
    protected $recepients     = array();
    protected $message        = '';

    public $status           = array();
    public $messagesStatuses = array();
    public $responseData     = null;
    public $responseInfo     = null;


	protected $errorStatuses = [
		'Unavailable' 		  => 'Maybe missed API key',
		'MismatchSenderId' 	  => 'Make sure you\'re using one of those when trying to send messages to the device. If you switch to a different sender, the existing registration IDs won\'t work.',
		'MissingRegistration' => 'Check that the request contains a registration ID',
		'InvalidRegistration' => 'Check the formatting of the registration ID that you pass to the server. Make sure it matches the registration ID the phone receives in the google',
		'NotRegistered' 	  => 'Not registered',
		'MessageTooBig' 	  => 'The total size of the payload data that is included in a message can\'t exceed 4096 bytes'
	];

	/**
	 * Constructor
	 */
	public function __construct() {

		$di = DI::getDefault();

		//set API key
		$this->apiKey = $di->getShared("config")->app->gcm["apiKey"];

		if (!$this->apiKey)
			die('GCM lib -> API Key Required in app config [pushservices->gcmApiKey]');
	}


	/**
	* Time to live
	*
	* @param <array> $data
	* @return <array>
	*/
	public function setTtl($ttl = '')
	{
		if (!$ttl)
			unset($this->payload['time_to_live']);
		else
			$this->payload['time_to_live'] = $ttl;
	}


	/**
	 * Setting GCM message
	 *
	 * @param string $message
	 */
	public function setMessage($message = '') {

		$this->message = $message;
		$this->payload['data']['message'] = $message;
	}


	/**
	 * Setting data to message
	 *
	 * @param string $data
	 */
	public function setData($data = array()) {

		$this->payload['data'] = $data;

		if ($this->message)
			$this->payload['data']['message'] = $this->message;
	}


	/**
	 * Setting group of messages
	 *
	 * @param string $group
	 */
	public function setGroup($group = '')
	{
		if (!$group)
			unset($this->payload['collapse_key']);
		else
			$this->payload['collapse_key'] = $group;
	}


	/**
	 * Adding one recepient
	 *
	 * @param string $group
	 */
	public function addRecepient($registrationId)
	{
		$this->payload['registration_ids'][] = $registrationId;
	}

	/**
	 * Setting all recepients
	 *
	 * @param string $group
	 */
	public function setRecepients($registrationIds)
	{
		$this->payload['registration_ids'] = $registrationIds;
	}

	/**
	 * Clearing group of messages
	 */
	public function clearRecepients()
	{
		$this->payload['registration_ids'] = array();
	}

	/**
	 * Senging messages to Google Cloud Messaging
	 *
	 * @param string $group
	 */
	public function send()
	{
		$this->payload['registration_ids'] = array_unique($this->payload['registration_ids']);

		if (isset($this->payload['time_to_live']) && !isset($this->payload['collapse_key']))
			$this->payload['collapse_key'] = 'Punchmo Notifications';

		$data = json_encode($this->payload);
		return $this->request($data);
	}

	/**
	 * Request
	 */
	protected function request($data)
	{
		$headers[] = 'Content-Type:application/json';
		$headers[] = 'Authorization:key='.$this->apiKey;

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, self::API_SEND_ADDRESS);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		$this->responseData = curl_exec($curl);

		$this->responseInfo = curl_getinfo($curl);

		curl_close($curl);

		return $this->parseResponse();
	}

	/**
	 * Parse response
	 */
	protected function parseResponse()
	{
		if ($this->responseInfo['http_code'] == 200) {

			$response = explode("\n",$this->responseData);
			$responseBody = json_decode($response[count($response)-1]);

			if ($responseBody->success && !$responseBody->failure) {

				$message = 'All messages were sent successfully';
				$error 	 = 0;
			}
			elseif ($responseBody->success && $responseBody->failure) {

				$message = $responseBody->success.' of '.($responseBody->success+$responseBody->failure).' messages were sent successfully';
				$error 	 = 1;
			}
			elseif (!$responseBody->success && $responseBody->failure) {

				$message = 'No messages cannot be sent. '.$responseBody->results[0]->error;
				$error 	 = 1;
			}

			$this->status = [
				'error'   => $error,
				'message' => $message
			];

			$this->messagesStatuses = array();
			foreach($responseBody->results as $key => $result) {

				if (isset($result->error) && $result->error) {

					$this->messagesStatuses[$key] = [
						'error' => 1,
						'regid' => $this->payload['registration_ids'][$key],
						'message' => $this->errorStatuses[$result->error],
						'message_id' => ''
					];
				}
				else {

					$this->messagesStatuses[$key] = [
						'error' => 0,
						'regid' => $this->payload['registration_ids'][$key],
						'message' => 'Message was sent successfully',
						'message_id' => $result->message_id
					];
				}
			}

			return !$error;
		}
		elseif ($this->responseInfo['http_code'] == 400) {

			$this->status = [
				'error'   => 1,
				'message' => 'Request could not be parsed as JSON'
			];
			return false;
		}
		elseif ($this->responseInfo['http_code'] == 401) {

			$this->status = [
				'error'   => 1,
				'message' => 'There was an error authenticating the sender account'
			];
			return false;
		}
		elseif ($this->responseInfo['http_code'] == 500) {

			$this->status = [
				'error'   => 1,
				'message' => 'There was an internal error in the GCM server while trying to process the request'
			];
			return false;
		}
		elseif ($this->responseInfo['http_code'] == 503) {

			$this->status = [
				'error'   => 1,
				'message' => 'Server is temporarily unavailable'
			];
			return false;
		}
		else {
			
			$this->status = [
				'error'   => 1,
				'message' => 'Status undefined'
			];
			return false;
		}
	}
}