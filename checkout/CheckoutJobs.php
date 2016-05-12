<?php
/**
 * Checkout Jobs
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Services\Redis;

/**
 * Checkout Jobs for CLI
 */
trait CheckoutJobs
{
    /**
     * API URL to get chilean currencies values
     * @var string
     */
    private static $API_URL_CLP_CURRENCY = "http://www.mindicador.cl/";

    /**
     * Redis cache key to store Dollar day value to CLP currency
     * @var string
     */
    private static $CACHE_KEY_USD_CLP_VALUE = "USD_CLP";

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Initializer
     */
    protected function initCheckoutJobs()
    {
        $this->colorize("userCheckoutCleaner: Cleans expired user checkouts", "WARNING");
        $this->colorize("storeDollarChileanPesoValue: API request & stores in cache dollar CLP conversion value", "WARNING");
    }

    /**
     * CLI - Users Checkouts cleaner (for expired checkout)
     */
    public function userCheckoutCleanerAction()
    {
        if (MODULE_NAME !== "cli")
            throw new Exception("This action is only for CLI app.");

        $user_checkout_class = AppModule::getClass("user_checkout");

        //delete pending checkouts with default expiration time
        $objs_deleted = $user_checkout_class::deleteExpired();

        //rows affected
        if ($objs_deleted) {

            $output = "[".date("d-m-Y H:i:s")."] userCheckoutCleaner -> Expired Checkouts deleted: ".$objs_deleted."\n";
            $this->output($output);
        }
    }

    /**
     * CLI - Saves in cache CLP - USD value conversion
     */
    public function storeDollarChileanPesoValueAction()
    {
        if (MODULE_NAME !== "cli")
            throw new Exception("This action is only for CLI app.");

        try {

            //get value from remote API
            $value = $this->_apiChileanCurrencyRequest();

            if (empty($value))
                throw new Exception("Invalid value received from api chilean currency");

            //redis service
            $redis = new Redis();

            //set key
            $redis->set(self::$CACHE_KEY_USD_CLP_VALUE, $value);

            $output = "[".date("d-m-Y H:i:s")."] storeDollarChileanPesoValue -> Stored value '$value' in redis cache. \n";
            //print output
            $this->output($output);
        }
        catch (Exception $e) {

            $this->logger->error("CheckoutJob::storeChileanPesoToDollarConversion -> failed retriving API data. Err: ".$e->getMessage());
        }
    }

    /**
     * Gets CLP to USD currency conversion value
     * @param int $amount - The CLP amount
     * @return float The USD value
     */
    public function chileanPesoToDollar($amount = 0)
    {
        //redis service
        $redis = new Redis();

        $value = $redis->get(self::$CACHE_KEY_USD_CLP_VALUE);

        //fallback
        if (empty($value))
            throw new Exception("Invalid value stored in cache. Run CLI to store value");

        //apply conversion
        return number_format((float)($amount / $value), 2, '.', '');
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * Calls API Chilean Currency to get indicator values
     * @param  string $indicator - Values: [uf, ivp, dolar, dolar_intercambio, euro, ipc, utm, imacec, tpm, libra_cobre, tasa_desempleo]
     * @return float - The value
     */
    private function _apiChileanCurrencyRequest($indicator = "dolar")
    {
        //get dollar value for today
        $api_url = self::$API_URL_CLP_CURRENCY."api/$indicator/".date("d-m-Y");

        //try both approaches
        if (ini_get("allow_url_fopen")) {

            $json = file_get_contents($api_url);
        }
        else {

            $curl = curl_init($api_url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $json = curl_exec($curl);
            curl_close($curl);
        }

        //get data
        $data = json_decode($json);

        //check struct
        if (!$data || !is_array($data->serie) || empty($data->serie))
            throw new Exception("Missing 'serie' property for parsed JSON object: ".json_encode($data));

        $indicator = $data->serie[0];

        if (!isset($indicator->valor))
            throw new Exception("Missing 'valor' property for parsed JSON object: ".json_encode($data));

        return  (float)($indicator->valor);
    }
}
