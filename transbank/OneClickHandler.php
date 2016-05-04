<?php
/**
 * CrazyCake OneClick
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

//imports
use Phalcon\Exception;

/**
 * Webpay OneClick Handler
 */
class OneClickHandler
{
    /* consts */
    const WP_ONE_CLICK_DEV_PAYMENT_URL = "https://webpay3g.orangepeople.cl/webpayserver/wswebpay/OneClickPaymentService?wsdl";

    /**
     * class map aray
     * @static
     * @var array
     */
    private static $classmap = [
        "removeUser"                      => __NAMESPACE__."\\removeUser",
        "oneClickRemoveUserInput"         => __NAMESPACE__."\\oneClickRemoveUserInput",
        "baseBean"                        => __NAMESPACE__."\\baseBean",
        "removeUserResponse"              => __NAMESPACE__."\\removeUserResponse",
        "initInscription"                 => __NAMESPACE__."\\initInscription",
        "oneClickInscriptionInput"        => __NAMESPACE__."\\oneClickInscriptionInput",
        "initInscriptionResponse"         => __NAMESPACE__."\\initInscriptionResponse",
        "oneClickInscriptionOutput"       => __NAMESPACE__."\\oneClickInscriptionOutput",
        "finishInscription"               => __NAMESPACE__."\\finishInscription",
        "oneClickFinishInscriptionInput"  => __NAMESPACE__."\\oneClickFinishInscriptionInput",
        "finishInscriptionResponse"       => __NAMESPACE__."\\finishInscriptionResponse",
        "oneClickFinishInscriptionOutput" => __NAMESPACE__."\\oneClickFinishInscriptionOutput",
        "codeReverseOneClick"             => __NAMESPACE__."\\codeReverseOneClick",
        "oneClickReverseInput"            => __NAMESPACE__."\\oneClickReverseInput",
        "codeReverseOneClickResponse"     => __NAMESPACE__."\\codeReverseOneClickResponse",
        "oneClickReverseOutput"           => __NAMESPACE__."\\oneClickReverseOutput",
        "authorize"                       => __NAMESPACE__."\\authorize",
        "oneClickPayInput"                => __NAMESPACE__."\\oneClickPayInput",
        "authorizeResponse"               => __NAMESPACE__."\\authorizeResponse",
        "oneClickPayOutput"               => __NAMESPACE__."\\oneClickPayOutput",
        "reverse"                         => __NAMESPACE__."\\reverse",
        "reverseResponse"                 => __NAMESPACE__."\\reverseResponse"
    ];

    /**
     * soap client
     * @var object
     */
    public $soapClient;

    /**
     * constructor
     * @param string $key_file_path The Key file path
     * @param string $cert_file_path The Cert file path
     * @param string $url The Soap URL service (optional)
     */
    function __construct($key_file_path, $cert_file_path, $url = self::WP_ONE_CLICK_DEV_PAYMENT_URL)
    {
        //options for SSL configuration
        $opts = [
            "ssl" => ["ciphers" => "RC4-SHA", "verify_peer" => false, "verify_peer_name" => false]
        ];

        try {
            //new soap client
            $this->soapClient = new \CrazyCake\Soap\SoapClientHelper($url, [
                "classmap"       => self::$classmap,
                "trace"          => true,
                "exceptions"     => true,
                "stream_context" => stream_context_create($opts)
            ]);
            //set security files
            $this->soapClient->setSecurityFiles($key_file_path, $cert_file_path);
        }
        catch (Exception $e) {
            throw new Exception("OneClick -> Soap Client Lib is required");
        }
    }

    function removeUser($removeUser)
    {
        $response = $this->soapClient->removeUser($removeUser);
        return $response;
    }

    function initInscription($initInscription)
    {
        $response = $this->soapClient->initInscription($initInscription);
        return $response;
    }

    function finishInscription($finishInscription)
    {
        $response = $this->soapClient->finishInscription($finishInscription);
        return $response;
    }

    function authorize($authorize)
    {
        $response = $this->soapClient->authorize($authorize);
        return $response;
    }

    function codeReverseOneClick($codeReverseOneClick)
    {
        $response = $this->soapClient->codeReverseOneClick($codeReverseOneClick);
        return $response;
    }

    function reverse($reverse)
    {
        $response = $this->soapClient->reverse($reverse);
        return $response;
    }
}

/** WSDL2 Handler classes (Required for SOAP client) */

class removeUser {
    var $arg0;
    //oneClickRemoveUserInput
}

class oneClickRemoveUserInput {
    var $tbkUser;
    //string
    var $username;
    //string
}

class baseBean {
}

class removeUserResponse {
    var $return;
    //boolean
}

class initInscription {
    var $arg0;
    //oneClickInscriptionInput
}

class oneClickInscriptionInput {
    var $email;
    //string
    var $responseURL;
    //string
    var $username;
    //string
}

class initInscriptionResponse {
    var $return;
    //oneClickInscriptionOutput
}

class oneClickInscriptionOutput {
    var $token;
    //string
    var $urlWebpay;
    //string
}

class finishInscription {
    var $arg0;
    //oneClickFinishInscriptionInput
}

class oneClickFinishInscriptionInput {
    var $token;
    //string
}

class finishInscriptionResponse {
    var $return;
    //oneClickFinishInscriptionOutput
}

class oneClickFinishInscriptionOutput {
    var $authCode;
    //string
    var $creditCardType;
    //creditCardType
    var $last4CardDigits;
    //string
    var $responseCode;
    //int
    var $tbkUser;
    //string
}

class codeReverseOneClick {
    var $arg0;
    //oneClickReverseInput
}

class oneClickReverseInput {
    var $buyorder;
    //long
}

class codeReverseOneClickResponse {
    var $return;
    //oneClickReverseOutput
}

class oneClickReverseOutput {
    var $reverseCode;
    //long
    var $reversed;
    //boolean
}

class authorize {
    var $arg0;
    //oneClickPayInput
}

class oneClickPayInput {
    var $amount;
    //decimal
    var $buyOrder;
    //long
    var $tbkUser;
    //string
    var $username;
    //string
}

class authorizeResponse {
    var $return;
    //oneClickPayOutput
}

class oneClickPayOutput {
    var $authCode;
    //string
    var $creditCardType;
    //creditCardType
    var $last4CardDigits;
    //string
    var $responseCode;
    //int
    var $transactionId;
    //long
}

class reverse {
    var $arg0;
    //oneClickReverseInput
}

class reverseResponse {
    var $return;
    //boolean
}
