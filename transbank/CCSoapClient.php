<?php
/**
 * CrazyCake SoapClient
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @contibutors Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Transbank;

require_once 'soap/xmlseclibs.php';
require_once 'soap/soap-wsse.php';

class CCSoapClient extends SoapClient
{
	//implementa interface soap
    function __doRequest($request, $location, $saction, $version, $one_way = null)
    {
        $doc = new DOMDocument('1.0');
        $doc->loadXML($request);

        $objWSSE = new WSSESoap($doc);
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1,array('type' => 'private'));
        $objKey->loadKey(WP_PRIVATE_KEY, TRUE);

        $options = array("insertBefore" => TRUE);
        $objWSSE->signSoapDoc($objKey, $options);
        $objWSSE->addIssuerSerial(WP_CERT_FILE);
        $objKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
        $objKey->generateSessionKey();
        
        $retVal = parent::__doRequest($objWSSE->saveXML(), $location, $saction, $version);
        $doc = new DOMDocument();
        $doc->loadXML($retVal);
        return $doc->saveXML();
    }
}
