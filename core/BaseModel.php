<?php
/**
 * Base Model
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

class BaseModel extends \Phalcon\Mvc\Model
{
    /* properties */
    
    /**
     * @var int
     */
    public $id;

    /** ------------------------------------------ § ------------------------------------------------- **/

    /**
     * Find Object by ID
     * @access public
     * @static
     * @param int $id
     * @return Object
     */
    public static function getObjectById($id)
    {
        return self::findFirst(array(
            "id = '".$id."'" //conditions
        ));
    }

    /** ------------------------------------------ § ------------------------------------------------- **/

    /**
     * Generates an alphanumeric code
     * @param  int $length The code length
     * @return string
     */
    protected function _generateAlphanumericCode($length = 8)
    {
        $code = "";

        for($k = 0; $k < $length; $k++) {

            $num  = chr(rand(48,57));
            $char = strtoupper(chr(rand(97,122)));
            $p    = rand(1,2);
            //append
            $code .= ($p == 1) ? $num : $char;
        }
        //replace ambiguos chars
        $placeholders = array("O", "I", "J", "B");
        $replacers    = array("0", "1", "X", "3");

        return str_replace($placeholders, $replacers, $code);
    }
}
