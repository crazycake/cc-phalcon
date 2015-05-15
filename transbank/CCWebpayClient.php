<?php
/**
 * CrazyCake Webpay client
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @contibutors Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

require_once 'CCOneClick.php';

class CCWebpayClient
{
	/**
	 * The init process to credit card inscription
	 * @param  string $username     The username as namespace
	 * @param  string $email        The user email
	 * @param  string $response_url [description]
	 * @return object with token & inscription URL
	 */
	public function initCardInscription($username, $email, $response_url)
	{
		$oneClickService = new CCOneClick();
		$oneClickInscriptionInput = new oneClickInscriptionInput();
		$oneClickInscriptionInput->username = $username;
		$oneClickInscriptionInput->email = $email;
		$oneClickInscriptionInput->responseURL = $response_url;
		
		$oneClickInscriptionResponse = $oneClickService->initInscription(array("arg0" => $oneClickInscriptionInput));

		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, WP_TRANSBANK_CERT);
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
		$oneClickService = new CCOneClick();
		$oneClickFinishInscriptionInput = new oneClickFinishInscriptionInput();
		$oneClickFinishInscriptionInput->token = $received_token; // es el token de resultado obtenido en el metodo initInscription.
		
		$oneClickFinishInscriptionResponse = $oneClickService->finishInscription(array( "arg0" => $oneClickFinishInscriptionInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, WP_TRANSBANK_CERT); 

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
	public function removeCardInscription($tbkUser, $commerceUser){
		$oneClickService = new CCOneClick();
		$oneClickRemoveUserInput = new oneClickRemoveUserInput();

		$oneClickRemoveUserInput->tbkUser = $tbkUser; // identificador de usuario entregado en el servicio finishInscription
		$oneClickRemoveUserInput->username = $commerceUser; // identificador de usuario del comercio
		
		$removeUserResponse = $oneClickService->removeUser(array("arg0" => $oneClickRemoveUserInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, WP_TRANSBANK_CERT); //Si la firma es válida
		
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
		$oneClickService = new CCOneClick();
		$oneClickPayInput = new oneClickPayInput();
		$oneClickPayInput->amount = $amount; // monto de pago
		$oneClickPayInput->buyOrder = $buyOrder; // orden de compra
		$oneClickPayInput->tbkUser = $tbkUser; // identificador de usuario entregado en el servicio finishInscription
		$oneClickPayInput->username = $username; // identificador de usuario del comercio

		$oneClickauthorizeResponse = $oneClickService->authorize(array ("arg0" => $oneClickPayInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, WP_TRANSBANK_CERT);
		
		$oneClickPayOutput = $oneClickauthorizeResponse->return;

		//Resultado de la autorización
		$payload = new \stdClass();
		$payload->response_code	  = $oneClickPayOutput->responseCode;
		$payload->auth_code 	  = $oneClickPayOutput->authorizationCode;
		$payload->card_type  	  = $oneClickPayOutput->creditCardType;
		$payload->last_digits 	  = $oneClickPayOutput->last4CardDigits;
		//set webpay transaction_id
		$payload->gateway_transaction_id = $oneClickPayOutput->transactionId;
		return $payload;
	}

	/**
	 * Reverse a transaction payment done with authorize method
	 * @param  string $buyOrder The buy order
	 * @return mixed
	 */
	public function reverseCardTransaction($buyOrder){
		$oneClickService = new CCOneClick();
		$oneClickReverseInput = new oneClickReverseInput();
		$oneClickReverseInput->buyorder= $buyOrder;

		$revertTransaction = $oneClickService->codeReverseOneClick(array("arg0" => $oneClickReverseInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new \SoapValidation($xmlResponse, WP_TRANSBANK_CERT); //Si la firma es válida
		
		$response = $revertTransaction->return;

		$payload = new \stdClass();
		$payload->reversed 	 = $response ? $response->reversed : false;
		$payload->reverse_id = $response ? $response->reverseCode  : false;

		return $payload;
	}
}
