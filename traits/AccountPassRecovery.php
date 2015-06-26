<?php
/**
 * Account Pass Trait
 * This class has common actions for account password controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//imports
use CrazyCake\Utils\ReCaptcha;

/**
 * Account Password Trait
 */
trait AccountPassRecovery
{
    /**
     * abstract required methods
     */
    abstract public function setConfigurations();

    /**
     * Config var
     * @var array
     */
    public $accountConfig;

    /* --------------------------------------------------- § -------------------------------------------------------- */

     /**
      * Init Phalcon Controller
      */
    protected function initialize()
    {
        parent::initialize();
        //if loggedIn redirect to account
        $this->_redirectToAccount(true);
    }

    /**
     * View - Password recovery Action (send instructions view)
     */
    public function recoveryAction()
    {
        //load javascript
        $this->_loadJavascriptFiles($this->accountConfig['javascript_files']);

        //view vars
        $this->view->setVar("html_title", $this->accountConfig['text_title_recovery']);
        $this->view->setVar("js_recaptcha", true); //load reCaptcha
    }

    /**
     * View - Set New password action (vista donde se crea una nueva contraseña)
     */
    public function newAction($encrypted_data = null)
    {
        //get decrypted data
        try {
            //handle the encrypted data with parent controller
            $tokens_class = $this->getModuleClassName('users_tokens');
            $tokens_class::handleUserTokenValidation($encrypted_data);

            //load javascript
            $this->_loadJavascriptFiles($this->accountConfig['javascript_files']);

            //view vars
            $this->view->setVar("html_title", $this->accountConfig['text_title_create_pass']);
            $this->view->setVar("edata", $encrypted_data); //pass to view the encrypted data
        }
        catch (\Exception $e) {
            $this->logger->error('AccountPass::newAction -> Error in account activation, encrypted data (' . $encrypted_data . "). Trace: " . $e->getMessage());
            $this->dispatcher->forward(array("controller" => "errors", "action" => "expired"));
        }
    }

    /**
     * Ajax - Send Recovery password email Message with further instructions
     */
    public function sendRecoveryInstructionsAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams(array(
            'email'                => 'email',
            'g-recaptcha-response' => 'string',
        ));

        //google reCaptcha helper
        $recaptcha = new ReCaptcha($this->config->app->googleReCaptchaKey);

        if (!$recaptcha->checkResponse($data['g-recaptcha-response'])) {
            //show error message
            $this->_sendJsonResponse(200, $this->accountConfig['text_recaptcha_failed'], true);
        }

        //check if user exists is a active account
        $users_class = $this->getModuleClassName('users');
        $user = $users_class::getUserByEmail($data['email'], $users_class::$ACCOUNT_FLAGS['enabled']);

        //if user not exists, send message
        if (!$user)
            $this->_sendJsonResponse(200, $this->accountConfig['text_account_not_found'], 'alert');

        //send email message with password recovery steps
        $this->_sendAsyncMailMessage($this->accountConfig['method_mailer_pass_recovery'], $user->id);

        //set a flash message to show on account controller
        $this->flash->success(str_replace("{email}", $data['email'], $this->accountConfig['text_pass_mail_sent']));

        //send JSON response
        $this->_sendJsonResponse(200);
        return;
    }

    /**
     * Ajax - Saves a new password set by the user in the post-recovery password view
     */
    public function saveNewPasswordAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams(array(
            'edata' => 'string',
            'pass'  => 'string',
        ));

        //validate encrypted data
        $payload = false;
        try {
            //get model classes
            $users_class  = $this->getModuleClassName('users');
            $tokens_class = $this->getModuleClassName('users_tokens');

            $edata = $tokens_class::handleUserTokenValidation($data['edata']);
            list($user_id, $token_type, $token) = $edata;

            //get user
            $user = $users_class::getObjectById($user_id);

            if (!$user)
                throw new \Exception("got an invalid user (id:" . $user_id . ") when validating encrypted data.");

            //get token object
            $token = $tokens_class::getTokenByUserAndValue($user_id, $token, $token_type);

            //save new account flag state
            $user->update(array("pass" => $this->security->hash($data['pass'])));
            //delete user token
            $token->delete();

            //set a flash message to show on account controller
            $this->flash->success($this->accountConfig['text_new_pass_saved']);

            //abstract parent controller
            $this->_setUserSessionAsLoggedIn($user->id);

            $payload = true;
        }
        catch (\Exception $e) {
            $this->logger->error("AccountPass::saveNewPasswordAction -> Error saving new password. Trace: " . $e->getMessage());
            $this->_sendJsonResponse(400);
            return;
        }

        //send JSON response
        $this->_sendJsonResponse(200, $payload);
        return;
    }
}
