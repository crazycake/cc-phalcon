<?php
/**
 * Session Core Controller, includes basic and helper methods for sessions.
 * Requires a Phalcon DI Factory Services and a CoreController
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\Mvc\Controller;  //Phalcon Controller
//CrazyCake Traits
use CrazyCake\Traits\Session;

abstract class SessionCore extends \CoreController
{
    /**
     * abstract required methods
     */
    abstract protected function getUserSessionData($session);
    abstract protected function setUserSessionAsLoggedIn($user);

    /* traits */
    use Session;

    /**
     * Stores user session as array for direct access
     * @var array
     */
    protected $user_session;

    /** ---------------------------------------------------------------------------------------------------------------
     * Constructor function
     * --------------------------------------------------------------------------------------------------------------- **/
    protected function onConstruct()
    {
        //always call parent constructor
        parent::onConstruct();

        //exclude api controller includes
        if(MODULE_NAME == "api")
            return;

        //set session var
        $this->user_session = $this->_getUserSessionData();
        //set user data for view, filter is passed to exclude some properties
        $this->_setUserDataForView(array('id', 'account_flag', 'auth'));
    }
}
