<?php
/**
 * Base Model for User Checkouts Transactions
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkouts;

//other imports
use CrazyCake\Utils\DateHelper;
use CrazyCake\Utils\FormHelper;

/**
 * Base Tickets Model
 */
class BaseUsersCheckoutsTrx extends \CrazyCake\Models\Base
{
    /* properties */

    /**
     * @var string
     */
    public $buy_order;

    /**
     * @var string
     */
    public $gateway;

    /**
     * @var int
     */
    public $trx_id;

    /**
     * @var string
     */
    public $type;

    /**
     * @var int
     */
    public $card_last_digits;

    /**
     * @var int
     */
    public $amount;

    /**
    * @var string
    */
    public $coin;

    /**
     * @var string
     */
    public $date;

    /**
     * @var string
     */
    public $created_at;

    /**
     * Extended properties
     */
    public $_ext;

    /**
     * Initilizer
     */
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(['created_at', '_ext']);
    }

    /**
     * After Fetch Event
     */
    public function afterFetch()
    {
        //set properties
        $this->_ext = [
            //amount formatted (default coin)
            "amount_formatted" => FormHelper::formatPrice($this->amount, $this->coin)
        ];
    }
    /** ------------------------------------------- § ------------------------------------------------ **/

}
