<?php
/**
 * Base Model for User Checkouts Transactions
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Checkout;

//other imports
use CrazyCake\Helpers\DateHelper;
use CrazyCake\Helpers\FormHelper;

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
    public $currency;

    /**
     * @var string
     */
    public $date;

    /**
     * @var string
     */
    public $local_time;

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
        $this->skipAttributes(['_ext']);

        //get class
        $users_checkouts_class = \CrazyCake\Core\AppCore::getModuleClass("users_checkouts", false);
        //model relations
        $this->hasOne("buy_order", $users_checkouts_class, "buy_order");
    }

    /**
     * After Fetch Event
     */
    public function afterFetch()
    {
        //set properties
        $this->_ext = [
            //amount formatted
            "date_formatted"   => (new \DateTime($this->local_time))->format('d-m-Y H:i:s'),
            "amount_formatted" => FormHelper::formatPrice($this->amount, $this->currency)
        ];
    }

    /**
     * Before Validation Event [onCreate]
     */
    public function beforeValidationOnCreate()
    {
        //set server local time
        $this->local_time = date("Y-m-d H:i:s");
    }

    /** ------------------------------------------- § ------------------------------------------------ **/

}
