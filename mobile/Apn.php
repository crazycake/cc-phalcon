<?php
/**
 * Apple Push Notification Client
 * @copyright (c) Benjamin Ortuzar Seconde <bortuzar@gmail.com>
 * @license GNU/GPL 2
 * @link https://github.com/antongorodezkiy/codeigniter-apn/
 * @author Phalcon integration - Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Mobile;

//imports
use Phalcon\DI;

/**
 * Apple Push Notification Client
 */
class APN
{
	/** const **/
	const CONECTION_TIMEOUT   = 60;
	const NOTIFICATION_EXPIRY = 86400;

	const PUSH_GATEWAY_SANDBOX = 'ssl://gateway.sandbox.push.apple.com:2195';
	const PUSH_GATEWAY 		   = 'ssl://gateway.push.apple.com:2195';

	const FEEDBACK_GATEWAY_SANDBOX = 'ssl://feedback.sandbox.push.apple.com:2196';
	const FEEDBACK_GATEWAY 		   = 'ssl://feedback.push.apple.com:2196';

	protected $server;
	protected $keyCertFilePath;
	protected $passphrase;
	protected $caCertFilePath;
	protected $pushStream;
	protected $feedbackStream;
	protected $timeout;
	protected $idCounter = 0;
	protected $expiry;
	protected $allowReconnect = true;
	protected $additionalData = array();

	protected $apnResonses = [
		0   => 'No errors encountered',
		1   => 'Processing error',
		2   => 'Missing device token',
		3   => 'Missing topic',
		4   => 'Missing payload',
		5   => 'Invalid token size',
		6   => 'Invalid topic size',
		7   => 'Invalid payload size',
		8   => 'Invalid token',
		255 => 'None (unknown)',
	];

	private $connection_start;

	public $error;
	public $payloadMethod = 'simple';

	/**
	 * Connects to the APNS server with a certificate and a passphrase
	 * @param array $config - The configuration data
	 */
	protected function __construct($config = array())
	{
		//check if file exists
		if(!file_exists($config['prodPemFile']))
			$this->_log("APN Lib -> Failed to connect: APN production PEM file not found");

		//set configs
		$this->pushServer 	  = $config['sandbox'] ? self::PUSH_GATEWAY_SANDBOX : self::PUSH_GATEWAY;
		$this->feedbackServer = $config['sandbox'] ? self::FEEDBACK_GATEWAY_SANDBOX : self::FEEDBACK_GATEWAY;

        $this->keyCertFilePath = $config['sandbox'] ? $config['devPemFile'] : $config['prodPemFile'];
        $this->passphrase      = $config['passphrase'];
        $this->caCertFilePath  = $config['entrustCaCertFile'];

		$this->timeout = self::CONECTION_TIMEOUT;
		$this->expiry  = self::NOTIFICATION_EXPIRY;
	}

	/**
	* Closes the stream
	*/
	protected function __destruct()
	{
		$this->disconnectPush();
		$this->disconnectFeedback();
	}

	/**
	* Connects to the server with the certificate and passphrase
	*
	* @return <void>
	*/
	protected function connect($server)
	{
		//set context
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $this->keyCertFilePath);
		stream_context_set_option($ctx, 'ssl', 'passphrase', $this->passphrase);
		stream_context_set_option($ctx, 'ssl', 'cafile', $this->caCertFilePath);

		$stream = stream_socket_client($server, $err, $errstr, $this->timeout, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
		$this->_log("APN: Maybe some errors: $err: $errstr");


		if (!$stream) {

			if ($err) {

				$this->_log("APNLib -> APN Failed to connect: $err $errstr");
				$this->_log("APN Failed to connect: $err $errstr");
			}
			else {

				$this->_log("APNLib -> APN Failed to connect: Something wrong with context");
				$this->_log("APN Failed to connect: Something wrong with context");
			}

			return false;
		}
		else {

			stream_set_timeout($stream,20);
			$this->_log("APN: Opening connection to: {$server}");
			return $stream;
		}
	}

	/**
	* Generates the payload
	*
	* @param <string> $message
	* @param <int> $badge
	* @param <string> $sound
	* @return <string>
	*/
	protected function generatePayload($message, $badge = NULL, $sound = NULL, $newstand = false)
	{
	   $body = array();

	    //additional data
		if (is_array($this->additionalData) && count($this->additionalData))
			$body = $this->additionalData;

		//message
		$body['aps'] = array('alert' => $message);

		//badge
		if ($badge)
			$body['aps']['badge'] = $badge;

		if ($badge == 'clear')
		$body['aps']['badge'] = 0;

		//sound
		if ($sound)
			$body['aps']['sound'] = $sound;

		//newstand content-available
		if($newstand)
			$body['aps']['content-available'] = 1;

	   $payload = json_encode($body);
	   $this->_log("APN: generatePayload '$payload'");
	   return $payload;
	}

	/**
	 * Writes the contents of payload to the file stream
	 *
	 * @param <string> $deviceToken
	 * @param <string> $payload
	 */
	protected function sendPayloadSimple($deviceToken, $payload)
	{
		$this->idCounter++;

		$this->_log("APN: sendPayloadSimple to '$deviceToken'");

		$msg = chr(0) 									// command
			 . pack('n',32)									// token length
			 . pack('H*', $deviceToken)						// device token
			 . pack('n',strlen($payload))					// payload length
			 . $payload;										// payload

		$this->_log("APN: payload: '$msg'");
		$this->_log("APN: payload length: '".strlen($msg)."'");
		$result = fwrite($this->pushStream, $msg, strlen($msg));

		return $result ? true : false;
	}

	/**
	 * Writes the contents of payload to the file stream with enhanced api (expiry, debug)
	 *
	 * @param <string> $deviceToken
	 * @param <string> $payload
	 */
	protected function sendPayloadEnhance($deviceToken, $payload, $expiry = 86400)
	{
		if (!is_resource($this->pushStream))
			$this->reconnectPush();

		$this->idCounter++;

		$this->_log("APN: sendPayloadEnhance to '$deviceToken'");

		$payload_length = strlen($payload);

		$request = chr(1) 										// command
					. pack("N", time())		 				// identifier
					. pack("N", time() + $expiry) // expiry
					. pack('n', 32)								// token length
					. pack('H*', $deviceToken) 		// device token
					. pack('n', $payload_length) 	// payload length
					. $payload;

		$request_unpacked = @unpack('Ccommand/Nidentifier/Nexpiry/ntoken_length/H64device_token/npayload_length/A*payload', $request); // payload

		$this->_log("APN: request: '$request'");
		$this->_log("APN: unpacked request: '" . print_r($request_unpacked, true) . "'");
		$this->_log("APN: payload length: '" . $payload_length . "'");
		$result = fwrite($this->pushStream, $request, strlen($request));

		return $result ? $this->getPayloadStatuses() : false;
	}

	/**
	 * timeout Soon
	 * @param  integer $left_seconds
	 */
	protected function timeoutSoon($left_seconds = 5)
	{
		$t = ( (round(microtime(true) - $this->connection_start) >= ($this->timeout - $left_seconds)));
		return (bool)$t;
	}

	/**
	 * Public connector to push service
	 */
	public function connectToPush()
	{
		if (!$this->pushStream or !is_resource($this->pushStream)) {

			$this->_log("APN: connectToPush");
			$this->//_lo" "APNLib -> connectToPush successfully!");

			$this->pushStream = $this->connect($this->pushServer);

			if ($this->pushStream) {

				$this->connection_start = microtime(true);
				//stream_set_blocking($this->pushStream,0);
			}
		}

		return $this->pushStream;
	}

	/**
	 * Public connector to feedback service
	 */
	public function connectToFeedback()
	{
		$this->_log("APN: connectToFeedback");
		return $this->feedbackStream = $this->connect($this->feedbackServer);
	}

	/**
	 * Public diconnector to push service
	 */
	function disconnectPush()
	{
		$this->_log("APN: disconnectPush");
		if ($this->pushStream && is_resource($this->pushStream)) {

			$this->connection_start = 0;
			return @fclose($this->pushStream);
		}
		else {
			return true;
		}
	}

	/**
	 * Public disconnector to feedback service
	 */
	function disconnectFeedback()
	{
		$this->_log("APN: disconnectFeedback");
		if ($this->feedbackStream && is_resource($this->feedbackStream))
			return @fclose($this->feedbackStream);
		else
			return true;
	}

	function reconnectPush()
	{
		$this->disconnectPush();

		if ($this->connectToPush()) {

			$this->_log("APN: reconnect");
			return true;
		}
		else {
			$this->_log("APN: cannot reconnect");
			return false;
		}
	}

	function tryReconnectPush()
	{
		if ($this->allowReconnect) {

			if($this->timeoutSoon())
				return $this->reconnectPush();
		}

		return false;
	}


	/**
	 * Sends a message to device
	 *
	 * @param <string> $deviceToken
	 * @param <string> $message
	 * @param <int> $badge
	 * @param <string> $sound
	 */
	public function sendMessage($deviceToken, $message, $badge = NULL, $sound = NULL, $expiry = '', $newstand = false)
	{
		$this->error = '';

		if (!ctype_xdigit($deviceToken)) {

			$this->_log("APN: Error - '$deviceToken' token is invalid. Provided device token contains not hexadecimal chars");
			$this->error = 'Invalid device token. Provided device token contains not hexadecimal chars';
			return false;
		}

		// restart the connection
		$this->tryReconnectPush();

		$this->_log("APN: sendMessage '$message' to $deviceToken");

		//generate the payload
		$payload = $this->generatePayload($message, $badge, $sound, $newstand);

		$deviceToken = str_replace(' ', '', $deviceToken);

		//send payload to the device.
		if ($this->payloadMethod == 'simple') {

			$this->sendPayloadSimple($deviceToken, $payload);
		}
		else {
			if (!$expiry)
				$expiry = $this->expiry;

			return $this->sendPayloadEnhance($deviceToken, $payload, $expiry);
		}
	}


	/**
	 * Writes the contents of payload to the file stream
	 *
	 * @param <string> $deviceToken
	 * @param <string> $payload
	 * @return <bool>
	 */
	function getPayloadStatuses()
	{

		$read = array($this->pushStream);
		$null = null;
		$changedStreams = stream_select($read, $null, $null, 0, 2000000);

		if ($changedStreams === false) {

			$this->_log("APN Error: Unabled to wait for a stream availability");
		}
		else if ($changedStreams > 0) {

			$responseBinary = fread($this->pushStream, 6);

			if ($responseBinary !== false || strlen($responseBinary) == 6) {

				if (!$responseBinary)
					return true;

				$response = @unpack('Ccommand/Cstatus_code/Nidentifier', $responseBinary);

				$this->_log("APN: debugPayload response - ".print_r($response,true));

				if ($response && $response['status_code'] > 0) {

					$this->_log("APN: debugPayload response - status_code:".$response['status_code'].' => '.$this->apnResonses[$response['status_code']]);
					$this->error = $this->apnResonses[$response['status_code']];
					return false;
				}
				else {

					if (isset($response['status_code']))
						$this->_log("APN: debugPayload response - ".print_r($response['status_code'],true));
				}
			}
			else {

				$this->_log("APN: responseBinary = $responseBinary");
				return false;
			}
		}
		else
			$this->_log("APN: No streams to change, $changedStreams");

		return true;
	}



	/**
	* Gets an array of feedback tokens
	*
	* @return <array>
	*/
	public function getFeedbackTokens() {

		$this->_log("APN: getFeedbackTokens {$this->feedbackStream}");
		$this->connectToFeedback();

	    $feedback_tokens = array();
	    //and read the data on the connection:
	    while(!feof($this->feedbackStream)) {

	        $data = fread($this->feedbackStream, 38);

	        if(strlen($data)) {
	        	//echo $data;
	            $feedback_tokens[] = unpack("N1timestamp/n1length/H*devtoken", $data);
	        }
	    }

		$this->disconnectFeedback();

	    return $feedback_tokens;
	}


	/**
	* Sets additional data which will be send with main apn message
	*
	* @param <array> $data
	* @return <array>
	*/
	public function setData($data)
	{
		if (!is_array($data)) {

			$this->_log("APN: cannot add additional data - not an array");
			return false;
		}

		if (isset($data['apn'])) {

			$this->_log("APN: cannot add additional data - key 'apn' is reserved");
			return false;
		}

		return $this->additionalData = $data;
	}

	/**
	 * Log wrapper
	 * @param  string $text - The input text
	 * @param  string $type - Log type
	 */
	private function _log($text = "", $type = "error") {

		//get logger service
		$di = DI::getDefault();
		$log = $di->getShared("logger");

		if($log)
			$log->{$type}("Apn Lib -> ".$text);
	}
}
