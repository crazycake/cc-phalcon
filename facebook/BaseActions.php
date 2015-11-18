<?php
/**
 * Base Facebook Actions
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Facebook;

//other imports
use CrazyCake\Utils\DateHelper;

class BaseActions extends \CrazyCake\Models\Base
{
    /* properties */

     /**
     * @var string
     */
    public $hashtag;

    /**
     * @var int
     */
    public $place_id;

    /**
     * @var int
     */
    public $album_id;

    /**
    * @var string
    */
    public $checkin_url;

    /**
    * @var string
    */
    public $checkin_text;

    /**
    * @var string
    */
    public $story_text;

    /**
    * @var string
    */
    public $photo_text;

    /**
     * Extended properties
     */
    public $_ext;

    /** ------------------------------------------- § --------------------------------------------------
        Init
    ------------------------------------------------------------------------------------------------- **/
    public function initialize()
    {
        //Skips fields/columns on both INSERT/UPDATE operations
        $this->skipAttributes(['_ext']);
    }
}
