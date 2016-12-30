<?php
/**
 * Checkout Currency
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//imports
use Phalcon\Exception;
use Predis\Client as Redis;
//core
use CrazyCake\Phalcon\App;

/**
 * Checkout Currency
 */
trait CheckoutCurrency
{
    /**
     * API URL to get chilean currencies values
     * @var string
     */
    private static $API_CURRENCY_URL = "http://apilayer.net/api/live?access_key=2ffe4397dee8b3c1a767dba701315f8e";

    /**
     * Redis key to store Dollar day value to CLP currency
     * @var string
     */
    private static $REDIS_KEY_USD_CLP_VALUE = "CHECKOUT_CURRENCY_USD_CLP";

    /** ------------------------------------------- § ------------------------------------------------ **/

	/**
	 * Set Redis client
	 */
	protected function newRedisClient()
	{
        return new Redis([
			"scheme" 	 => "tcp",
			"host"   	 => getenv("REDIS_HOST") ?: "redis",
			"port"   	 => 6379,
			"persistent" => false
		]);
	}

    /**
     * CLI - Saves in Redis currency CLP - USD value conversion
     */
    protected function storeDollarChileanPesoValue()
    {
        try {

            //get value from remote API
            $value = $this->apiChileanCurrencyRequest();

            if (empty($value))
                throw new Exception("Invalid value received from api chilean currency");

			$redis = $this->newRedisClient();
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
        $value = $this->dollarToChileanPeso();

        //fallback
        if (empty($value))
            throw new Exception("Invalid chilean currency value stored in Redis. Run CLI to store value");

        //apply conversion
        return number_format((float)($amount / $value), 2, '.', '');
    }

    /**
     * Get currency conversion USD to CLP
     */
	protected function dollarToChileanPeso($amount = 1.00)
	{
		//redis service
		$redis = $this->newRedisClient();

		$value = $redis->get(self::$REDIS_KEY_USD_CLP_VALUE);

        //set value if is empty
		if(empty($value) && $new_value = $this->apiChileanCurrencyRequest())
            $redis->set(self::$REDIS_KEY_USD_CLP_VALUE, $new_value);

		return $redis->get(self::$REDIS_KEY_USD_CLP_VALUE) * $amount;
	}

    /**
     * Calls API Chilean Currency to get indicator values
     * @param  string $indicator - Values: [uf, ivp, dolar, dolar_intercambio, euro, ipc, utm, imacec, tpm, libra_cobre, tasa_desempleo]
     * @param  int $interval - horas iterativas
     * @return float - The value
     */
    protected function apiChileanCurrencyRequest($indicator = "dolar")
    {
		try {
	        // get dollar value for today
	        $api_url = self::$API_CURRENCY_URL."&currencies=CLP&source=USD&format=1";

	        // print output for CLI
			if(method_exists($this, "colorize"))
		        $this->colorize("Requesting: ".$api_url);

            // curl request
            $curl = curl_init($api_url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $json = curl_exec($curl);
            curl_close($curl);

		    // get data
		    $data = json_decode($json);

		    // check struct
		    if (!$data || empty($data->success))
				throw new Exception("Invalid response struct o empty payload: ".json_encode($data, JSON_UNESCAPED_SLASHES));

            // get value
		    $value = (float)($data->quotes->USDCLP);

			// print output for CLI
			if(method_exists($this, "colorize"))
		    	$this->colorize("Saving value in Redis: ".$value);

			return $value;
		}
		catch(Exception $e) {
            $msg = $e->getMessage();
        }

		if(method_exists($this, "colorize"))
			$this->colorize($msg, "ERROR");

		return null;
    }
}
