<?php
/**
 * CrazyCake Webpay
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

//imports
use Phalcon\Exception;

class Webpay
{
	/* vars */
	public $client;

	private static $MODULES = array(
		"oneclick" => __NAMESPACE__."\\OneClickClient"
	);

	/**
	 * Constructor
	 * @param array $setup The cert & key files path (OneClickKey, OneClickCert, OneClickTransbankCert)
	 */
    public function __construct($setup = array())
    {
   		//validation
    	if(empty($setup))
    		throw new Exception('Webpay Lib -> Invalid Webpay files path for constructor. Array is required.');

		//set new client instance
		if($setup['module']) {
			$module = self::$MODULES[$setup['module']];
			try {
				$this->client = new $module($setup);
			}
			catch (Exception $e) {
				throw new Exception("Webpay Lib -> Error instancing Webpay module class. Message:".$e->getMessage());
			}
		}
		else {
			throw new Exception('Webpay Lib -> Invalid module!');
		}
    }
}
