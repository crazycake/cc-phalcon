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

	/** 
	 * Constructor
	 * @param array $webpay_files The cert & key files path (shopKey, shopCert, transbankCert)
	 */
    public function __construct($webpay_files = array())
    {
   		//validation
    	if(empty($webpay_files))
    		throw new Exception('Webpay Lib -> Invalid Webpay files path for constructor. Array is required.');

    	if(!isset($webpay_files['shopKey']) || !isset($webpay_files['shopCert']) || !isset($webpay_files['transbankCert']))
    		throw new Exception('Webpay Lib -> Invalid webpay files param array.');

    	//consts
		define('WP_PRIVATE_KEY', $webpay_files['shopKey']);
		define('WP_CERT_FILE',	 $webpay_files['shopCert']);
		define('WP_TRANSBANK_CERT', $webpay_files['transbankCert']);

		//validate files
		if(!is_file(WP_PRIVATE_KEY) || !is_file(WP_CERT_FILE) || !is_file(WP_TRANSBANK_CERT))
			throw new Exception('Webpay Lib -> Invalid webpay files, files not found!');

		//set new client instance
		$this->client = new CCWebpayClient();
    }
}
