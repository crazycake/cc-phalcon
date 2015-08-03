<?php
/**
 * CrazyCake OneClickClient
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

//imports
use Phalcon\Exception;

class OneClickClient
{
	/**
	 * @var object
	 */
	protected $oneclick;

	/**
	 * @var string
	 */
	protected $transbank_cert;

	/**
	 * Constructor
	 */
    public function __construct($setup = array())
    {
		//check required files
    	if(!isset($setup['OneClickKey']) || !isset($setup['OneClickCert']) || !isset($setup['OneClickTransbankCert']))
    		throw new Exception('OneClickClient -> Invalid webpay setup input array.');

		//validate files
		if(!is_file($setup['OneClickKey']) || !is_file($setup['OneClickCert']) || !is_file($setup['OneClickTransbankCert']))
			throw new Exception('OneClickClient -> Invalid webpay files, files not found!');

		//set gateway cert file
		$this->transbank_cert = $setup['OneClickTransbankCert'];

		//module class
    	$this->oneclick = new OneClick($setup['OneClickKey'], $setup['OneClickCert']);
    }

	/**
	 * The init process to credit card inscription
	 * @param  string $username     The username as namespace
	 * @param  string $email        The user email
	 * @param  string $response_url [description]
	 * @return object with token & inscription URL
	 */
	public function initCardInscription($username, $email, $response_url)
	{
		$oneClickInscriptionInput = new oneClickInscriptionInput();
		$oneClickInscriptionInput->username = $username;
		$oneClickInscriptionInput->email = $email;
		$oneClickInscriptionInput->responseURL = $response_url;

		$oneClickInscriptionResponse = $this->oneclick->initInscription(array("arg0" => $oneClickInscriptionInput));

		$xmlResponse = $this->oneclick->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, $this->transbank_cert);
		$soapValidation->getValidationResult(); //Esto valida si el mensaje está firmado por Transbank
		$oneClickInscriptionOutput = $oneClickInscriptionResponse->return; //Esto obtiene el resultado de la operación

		$payload = new \stdClass();
		$payload->token 	= $oneClickInscriptionOutput->token; //Token de resultado
		$payload->inscriptionURL = $oneClickInscriptionOutput->urlWebpay;//URL para realizar el post
		return $payload;
	}

	/**
	 * The finish process of credit card inscription
	 * @param  string $received_token The received token by WebPay server
	 * @return object with successful inscription data
	 */
	public function finishCardInscription($received_token)
	{
		$oneClickFinishInscriptionInput = new oneClickFinishInscriptionInput();
		$oneClickFinishInscriptionInput->token = $received_token; // es el token de resultado obtenido en el metodo initInscription.

		$oneClickFinishInscriptionResponse = $this->oneclick->finishInscription(array( "arg0" => $oneClickFinishInscriptionInput));

		$xmlResponse = $this->oneclick->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, $this->transbank_cert);

		$oneClickFinishInscriptionOutput = $oneClickFinishInscriptionResponse->return;//Si la firma es válida

		//Datos de resultado de la inscripción OneClick
		$payload = new \stdClass();
		$payload->response_code   = $oneClickFinishInscriptionOutput->responseCode;
		$payload->auth_code 	  = $oneClickFinishInscriptionOutput->authCode;
		$payload->card_type 	  = $oneClickFinishInscriptionOutput->creditCardType;
		$payload->last_digits 	  = $oneClickFinishInscriptionOutput->last4CardDigits;
		$payload->gateway_user 	  = $oneClickFinishInscriptionOutput->tbkUser;
		return $payload;
	}

	/**
	 * Remove a card inscription
	 * @param  int $tbkUser      The gateway user id
	 * @param  string $commerceUser The username
	 * @return boolean
	 */
	public function removeCardInscription($tbkUser, $commerceUser)
	{
		$oneClickRemoveUserInput = new oneClickRemoveUserInput();

		$oneClickRemoveUserInput->tbkUser = $tbkUser; // identificador de usuario entregado en el servicio finishInscription
		$oneClickRemoveUserInput->username = $commerceUser; // identificador de usuario del comercio

		$removeUserResponse = $this->oneclick->removeUser(array("arg0" => $oneClickRemoveUserInput));

		$xmlResponse = $this->oneclick->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, $this->transbank_cert); //Si la firma es válida

		return $removeUserResponse->return; // Valor booleano que indica si el usuario fue removido.
	}

	/**
	 * Authorize a card payment
	 * @param  int $amount   The amount
	 * @param  string $buyOrder The buy order
	 * @param  int $tbkUser  The gateway user id
	 * @param  string $username The username
	 * @return object
	 */
	public function authorizeCardPayment($amount, $buyOrder, $tbkUser, $username)
	{
		$oneClickPayInput = new oneClickPayInput();
		$oneClickPayInput->amount = $amount; // monto de pago
		$oneClickPayInput->buyOrder = $buyOrder; // orden de compra
		$oneClickPayInput->tbkUser = $tbkUser; // identificador de usuario entregado en el servicio finishInscription
		$oneClickPayInput->username = $username; // identificador de usuario del comercio

		$oneClickauthorizeResponse = $this->oneclick->authorize(array ("arg0" => $oneClickPayInput));

		$xmlResponse = $this->oneclick->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, $this->transbank_cert);

		$oneClickPayOutput = $oneClickauthorizeResponse->return;

		//Resultado de la autorización
		$payload = new \stdClass();
		$payload->response_code	   = $oneClickPayOutput->responseCode;
		$payload->auth_code 	   = $oneClickPayOutput->authorizationCode;
		$payload->card_type  	   = $oneClickPayOutput->creditCardType;
		$payload->card_last_digits = $oneClickPayOutput->last4CardDigits;
		//set webpay transaction_id
		$payload->gateway_transaction_id = $oneClickPayOutput->transactionId;
		return $payload;
	}

	/**
	 * Reverse a transaction payment done with authorize method
	 * @param  string $buyOrder The buy order
	 * @return mixed
	 */
	public function reverseCardTransaction($buyOrder)
	{
		$oneClickReverseInput = new oneClickReverseInput();
		$oneClickReverseInput->buyorder= $buyOrder;

		$revertTransaction = $this->oneclick->codeReverseOneClick(array("arg0" => $oneClickReverseInput));

		$xmlResponse = $this->oneclick->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, $this->transbank_cert); //Si la firma es válida

		$response = $revertTransaction->return;

		$payload = new \stdClass();
		$payload->reversed 	 = $response ? $response->reversed : false;
		$payload->reverse_id = $response ? $response->reverseCode  : false;

		return $payload;
	}
}
