<?php
/**
 * CrazyCake SoapClientHelper
 * @author Cristhoper Jaña <cristhoper.jana@crazycake.cl>
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Soap;

require_once "lib/xmlseclibs.php";
require_once "lib/soap-wsse.php";
require_once "lib/soap-validation.php";

/**
 * SOAP client helper
 */
class SoapClientHelper extends \SoapClient
{
    /**
     * @var string
     */
    protected $key_file;

    /**
     * @var string
     */
    protected $cert_file;

    /**
     * Set Soap Security files
     * @param string $key - The key file path
     * @param string $cert - The cert file path
     */
    function setSecurityFiles($key = null, $cert = null)
    {
        $this->key_file  = $key;
        $this->cert_file = $cert;
    }

	/**
     * Implements Soap Client::_doRequest Method
     * @link http://php.net/manual/en/soapclient.dorequest.php
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int $version
     * @param int $one_way
     */
    function __doRequest($request, $location, $saction, $version, $one_way = null)
    {
        $doc = new \DOMDocument("1.0");
        $doc->loadXML($request);

        $wsse = new \WSSESoap($doc);
        $key  = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA1,array("type" => "private"));

        $key->loadKey($this->key_file, true);

        $options = array("insertBefore" => true);

        $wsse->signSoapDoc($key, $options);
        $wsse->addIssuerSerial($this->cert_file);

        $key = new \XMLSecurityKey(\XMLSecurityKey::AES256_CBC);
        $key->generateSessionKey();

        $retVal = parent::__doRequest($wsse->saveXML(), $location, $saction, $version);
        
        $doc = new \DOMDocument();
        $doc->loadXML($retVal);

        return $doc->saveXML();
    }
}
