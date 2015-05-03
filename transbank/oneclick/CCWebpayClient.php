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
	public function initInscription($username, $email, $url_comercio)
	{

		$oneClickService = new CCOneClick();
		$oneClickInscriptionInput = new oneClickInscriptionInput();
		$oneClickInscriptionInput->username = $username;
		$oneClickInscriptionInput->email = $email;
		$oneClickInscriptionInput->responseURL = $url_comercio;
		
		$oneClickInscriptionResponse = $oneClickService->initInscription(array("arg0" => $oneClickInscriptionInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new SoapValidation($xmlResponse, WP_TRANSBANK_CERT);
		$soapValidation->getValidationResult(); //Esto valida si el mensaje está firmado por Transbank
		
		$oneClickInscriptionOutput = $oneClickInscriptionResponse->return; //Esto obtiene el resultado de la operación

		$return = new stdClass();
		$return->token = $oneClickInscriptionOutput->token; //Token de resultado
		$return->inscriptionURL = $oneClickInscriptionOutput->urlWebpay;//URL para realizar el post
		return $return;
	}

	public function finishIscription($tokenOneClick)
	{
		$oneClickService = new CCOneClick();
		$oneClickFinishInscriptionInput = new oneClickFinishInscriptionInput();
		$oneClickFinishInscriptionInput->token = $tokenOneClick; // es el token de resultado obtenido en el metodo initInscription.
		
		$oneClickFinishInscriptionResponse = $oneClickService->finishInscription(array( "arg0" => $oneClickFinishInscriptionInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new SoapValidation($xmlResponse, WP_TRANSBANK_CERT); 

		$oneClickFinishInscriptionOutput = $oneClickFinishInscriptionResponse->return;//Si la firma es válida
		
		//Datos de resultado de la inscripción OneClick
		$return = new stdClass();
		$return->responseCode = $oneClickFinishInscriptionOutput->responseCode;
		$return->authCode = $oneClickFinishInscriptionOutput->authCode;
		$return->creditCardType = $oneClickFinishInscriptionOutput->creditCardType;
		$return->last4CardDigits = $oneClickFinishInscriptionOutput->last4CardDigits;
		$return->tbkUser = $oneClickFinishInscriptionOutput->tbkUser;
		return $return;

	}

	public function authorize($amount, $buyOrder, $tbkUser, $username)
	{
		$oneClickService = new CCOneClick();
		$oneClickPayInput = new oneClickPayInput();
		$oneClickPayInput->amount = $amount; // monto de pago
		$oneClickPayInput->buyOrder = $buyOrder; // orden de compra
		$oneClickPayInput->tbkUser = $tbkUser; // identificador de usuario entregado en el servicio finishInscription
		$oneClickPayInput->username = $username; // identificador de usuario del comercio

		$oneClickauthorizeResponse = $oneClickService->authorize(array ("arg0" => $oneClickPayInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new SoapValidation($xmlResponse, WP_TRANSBANK_CERT);
		
		$oneClickPayOutput = $oneClickauthorizeResponse->return;

		//Resultado de la autorización
		$return = new stdClass();
		$return->authorizationCode = $oneClickPayOutput->authorizationCode;
		$return->creditCardType = $oneClickPayOutput->creditCardType;
		$return->last4CardDigits = $oneClickPayOutput->last4CardDigits;
		$return->responseCode = $oneClickPayOutput->responseCode;
		$return->transactionId = $oneClickPayOutput->transactionId;
		return $return;
	}

	public function codeReverseOneClickResponse($buyOrder){
		$oneClickService = new CCOneClick();
		$oneClickReverseInput = new oneClickReverseInput();
		$oneClickReverseInput->buyorder= $buyOrder;

		$codeReverseOneClickResponse = $oneClickService->codeReverseOneClick(array("arg0" => $oneClickReverseInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new SoapValidation($xmlResponse, WP_TRANSBANK_CERT); //Si la firma es válida
		
		return $codeReverseOneClickResponse->return;

	}

	public function removeUser($tbkUser, $commerceUser){
		$oneClickService = new CCOneClick();
		$oneClickRemoveUserInput = new oneClickRemoveUserInput();

		$oneClickRemoveUserInput->tbkUser = $tbkUser; // identificador de usuario entregado en el servicio finishInscription
		$oneClickRemoveUserInput->username = $commerceUser; // identificador de usuario del comercio
		
		$removeUserResponse = $oneClickService->removeUser(array("arg0" => $oneClickRemoveUserInput));
		
		$xmlResponse = $oneClickService->soapClient->__getLastResponse();
		$soapValidation = new SoapValidation($xmlResponse, WP_TRANSBANK_CERT); //Si la firma es válida
		
		return $removeUserResponse->return; // Valor booleano que indica si el usuario fue removido.
		
	}
}
