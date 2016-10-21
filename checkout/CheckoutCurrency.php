<?php
/**
 * Checkout Currency
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Services\Redis;
use Carbon\Carbon;

/**
 * Checkout Currency
 */
trait CheckoutCurrency
{
    /**
     * API URL to get chilean currencies values
     * @var string
     */
    private static $API_URL_CLP_CURRENCY = "http://www.mindicador.cl/";

    /**
     * Redis key to store Dollar day value to CLP currency
     * @var string
     */
    private static $REDIS_KEY_USD_CLP_VALUE = "CHECKOUT_CURRENCY_USD_CLP";


    /** ------------------------------------------- § ------------------------------------------------ **/

    /**
     * CLI - Saves in Redis currency CLP - USD value conversion
     */
    protected function storeDollarChileanPesoValue()
    {
        if (MODULE_NAME !== "cli")
            throw new Exception("This action is only for CLI app.");

        try {

            //get value from remote API
            $value = $this->apiChileanCurrencyRequest();

            if (empty($value))
                throw new Exception("Invalid value received from api chilean currency");

            //redis service
            $redis = new Redis();

            //set key
            $redis->set(self::$REDIS_KEY_USD_CLP_VALUE, $value);

            $output = "[".date("d-m-Y H:i:s")."] storeDollarChileanPesoValue -> Stored value '$value' in Redis. \n";
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
    protected function chileanPesoToDollar($amount = 0)
    {
        $value = $this->getChileanPesoToDollarConversion();

        //fallback
        if (empty($value))
            throw new Exception("Invalid chilean currency value stored in Redis. Run CLI to store value");

        //apply conversion
        return number_format((float)($amount / $value), 2, '.', '');
    }

    /**
     * Get currency conversion
     */
	protected function getChileanPesoToDollarConversion()
	{
		//redis service
        $redis = new Redis();

		return $redis->get(self::$REDIS_KEY_USD_CLP_VALUE);
	}

    /**
     * Calls API Chilean Currency to get indicator values
     * @param  string $indicator - Values: [uf, ivp, dolar, dolar_intercambio, euro, ipc, utm, imacec, tpm, libra_cobre, tasa_desempleo]
     * @return float - The value
     */
    protected function apiChileanCurrencyRequest($indicator = "dolar")
    {
        //subtract now 12 hours
        $date = (new Carbon())->subHours(12);

        //get dollar value for today
        $api_url = self::$API_URL_CLP_CURRENCY."api/$indicator/".$date->format("d-m-Y");
        //print output
        $this->colorize("Requesting: ".$api_url);

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

        $value = (float)($indicator->valor);

        $this->colorize("Saving value in Redis: ".$value);

        return $value;
    }
}
