<?php
/**
 * CrazyCake Webpay
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @contibutors Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

//load CCWebpay
require_once "CCWebpayClient.php"; 

class CCWebpay
{
	/* vars */
	public $client;

	/* Constructor */
    public function __construct($webpay_files = array()) {
   		//validation
    	if(empty($webpay_files))
    		throw new Exception('Webpay Lib -> Invalid Webpay files path for constructor. Array is required.');

    	if(!isset($webpay_files['key']) || !isset($webpay_files['cert']) || !isset($webpay_files['transbank_cert']))
    		throw new Exception('Webpay Lib -> Invalid webpay files param array.');

    	//consts
		define('WP_PRIVATE_KEY', $webpay_files['key']);
		define('WP_CERT_FILE',	 $webpay_files['cert']);
		define('WP_TRANSBANK_CERT', $webpay_files['transbank_cert']);

		//validate files
		if(!is_file(WP_PRIVATE_KEY) || !is_file(WP_CERT_FILE) || !is_file(WP_TRANSBANK_CERT))
			throw new Exception('Webpay Lib -> Invalid webpay files, files not found!');

		//new client instance
		$this->client = new CCWebpayClient();
    }
}
