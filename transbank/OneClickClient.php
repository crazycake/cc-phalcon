<?php
/**
 * CrazyCake OneClickClient
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

//imports
use Phalcon\Exception;

/**
 * Webpay OneClick Client
 */
class OneClickClient
{
	/**
	 * @var object
	 */
	protected $handler;

	/**
	 * @var string
	 */
	protected $transbank_cert;

	/**
	 * Constructor
	 */
    public function __construct($setup = [])
    {
		//check required files
    	if (!isset($setup["oneClickKey"]) || !isset($setup["oneClickCert"]) || !isset($setup["oneClickTransbankCert"]))
    		throw new Exception("OneClickClient -> Invalid webpay setup input array.");

		//replace base uris
		$setup["oneClickKey"] 			= str_replace("./", PROJECT_PATH, $setup["oneClickKey"]);
		$setup["oneClickCert"] 		    = str_replace("./", PROJECT_PATH, $setup["oneClickCert"]);
		$setup["oneClickTransbankCert"] = str_replace("./", PROJECT_PATH, $setup["oneClickTransbankCert"]);

		//validate files
		if (!is_file($setup["oneClickKey"]) || !is_file($setup["oneClickCert"]) || !is_file($setup["oneClickTransbankCert"]))
			throw new Exception("OneClickClient -> Invalid webpay files, files not found!");

		//set gateway cert file
		$this->transbank_cert = $setup["oneClickTransbankCert"];

		//module class
    	$this->handler = new OneClickHandler($setup["oneClickKey"], $setup["oneClickCert"]);
    }

	/**
	 * The init process to credit card inscription
	 * @param  string $username     - The username as namespace
	 * @param  string $email        - The user email
	 * @param  string $response_url - The response URL
	 * @return object with token & inscription URL
	 */
	public function initCardInscription($username, $email, $response_url)
	{
		$inscription = new oneClickInscriptionInput();
		$inscription->username    = $username;
		$inscription->email 	  = $email;
		$inscription->responseURL = $response_url;

		$response = $this->handler->initInscription(["arg0" => $inscription]);

		$xml_response = $this->handler->soapClient->__getLastResponse();

		$soap_validation = new \SoapValidation($xml_response, $this->transbank_cert);
		$soap_validation->getValidationResult(); //Esto valida si el mensaje está firmado por Transbank

		$output = $response->return; //Esto obtiene el resultado de la operación

		return (object)[
			"token" 		  => $output->token, 	//Token de resultado
			"inscription_url" => $output->urlWebpay //Token de resultado
		];
	}

	/**
	 * The finish process of credit card inscription
	 * @param  string $received_token The received token by WebPay server
	 * @return object with successful inscription data
	 */
	public function finishCardInscription($received_token)
	{
		$inscription = new oneClickFinishInscriptionInput();
		$inscription->token = $received_token; // es el token de resultado obtenido en el metodo initInscription.

		$response = $this->handler->finishInscription(["arg0" => $inscription]);

		$xml_response = $this->handler->soapClient->__getLastResponse();

		$soap_validation = new \SoapValidation($xml_response, $this->transbank_cert);

		$output = $response->return;//Si la firma es válida

		//Datos de resultado de la inscripción OneClick
		return (object)[
			"response_code" => $output->responseCode,
			"auth_code" 	=> $output->authCode,
			"card_type" 	=> $output->creditCardType,
			"last_digits"   => $output->last4CardDigits,
			"gateway_user"  => $output->tbkUser,
		];
	}

	/**
	 * Remove a card inscription
	 * @param  int $tbk_user      The gateway user id
	 * @param  string $username The username
	 * @return boolean
	 */
	public function removeCardInscription($tbk_user, $username)
	{
		$remove_user = new oneClickRemoveUserInput();

		$remove_user->tbkUser  = $tbk_user; // identificador de usuario entregado en el servicio finishInscription
		$remove_user->username = $username; // identificador de usuario del comercio

		$response = $this->handler->removeUser(["arg0" => $remove_user]);

		$xml_response = $this->handler->soapClient->__getLastResponse();

		$soap_validation = new \SoapValidation($xml_response, $this->transbank_cert); //Si la firma es válida

		return $response->return; // Valor booleano que indica si el usuario fue removido.
	}

	/**
	 * Authorize a card payment
	 * @param  int $amount   The amount
	 * @param  string $buy_order The buy order
	 * @param  int $tbk_user  The gateway user id
	 * @param  string $username The username
	 * @return object
	 */
	public function authorizeCardPayment($amount, $buy_order, $tbk_user, $username)
	{
		$pay_input = new oneClickPayInput();
		$pay_input->amount 	 = $amount; // monto de pago
		$pay_input->buy_order = $buy_order; // orden de compra
		$pay_input->tbkUser	  = $tbk_user; // identificador de usuario entregado en el servicio finishInscription
		$pay_input->username  = $username; // identificador de usuario del comercio

		$auth_response = $this->handler->authorize(["arg0" => $pay_input]);

		$xml_response = $this->handler->soapClient->__getLastResponse();

		$soap_validation = new \SoapValidation($xml_response, $this->transbank_cert);

		$output = $auth_response->return;

		//Resultado de la autorización
		return (object)[
			"response_code"  => $output->responseCode,
			"auth_code" 	 => $output->authorizationCode,
			"card_type" 	 => $output->creditCardType,
			"last_digits"    => $output->last4CardDigits,
			"gateway_trx_id" => $output->transactionId,
		];
	}

	/**
	 * Reverse a transaction payment done with authorize method
	 * @param  string $buy_order The buy order
	 * @return mixed
	 */
	public function reverseCardTransaction($buy_order)
	{
		$reverse_input = new oneClickReverseInput();
		$reverse_input->buy_order= $buy_order;

		$response = $this->handler->codeReverseOneClick(["arg0" => $reverse_input]);

		$xml_response = $this->handler->soapClient->__getLastResponse();

		$soap_validation = new \SoapValidation($xml_response, $this->transbank_cert); //Si la firma es válida

		$output = $response->return;

		return (object)[
			"reversed"   => ($output ? $output->reversed : false),
			"reverse_id" => ($output ? $output->reverseCode : false)
		];
	}
}
