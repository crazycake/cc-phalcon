<?php
/**
 * Account Pass Trait
 * This class has common actions for account password controllers
 * Requires a Frontend or Backend Module
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use CrazyCake\Phalcon\AppModule;
use Phalcon\Exception;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Password Recovery
 */
trait AccountPassRecovery
{
    /**
     * Config var
     * @var array
     */
    public $account_pass_recovery_conf;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    public function initAccountPassRecovery($conf = array())
    {
        $this->account_pass_recovery_conf = $conf;
    }

    /**
     * Phalcon Initializer Event
     */
    protected function initialize()
    {
        parent::initialize();
        //if loggedIn redirect to account
        $this->_redirectToAccount(true);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * View - Password recovery Action (send instructions view)
     */
    public function recoveryAction()
    {
        //view vars
        $this->view->setVar("html_title", $this->account_pass_recovery_conf["trans"]["title_recovery"]);
        $this->view->setVar("js_recaptcha", true); //load reCaptcha

        //load javascript modules
        $this->_loadJsModules($this->account_pass_recovery_conf["js_modules"]);
    }

    /**
     * View - Set New password action (vista donde se crea una nueva contraseña)
     * @param string $encrypted_data - The encrypted data
     */
    public function newAction($encrypted_data = null)
    {
        //get decrypted data
        try {
            //handle the encrypted data with parent controller
            $tokens_class = AppModule::getClass("user_token");
            $tokens_class::handleUserTokenValidation($encrypted_data);

            //view vars
            $this->view->setVar("html_title", $this->account_pass_recovery_conf["trans"]["title_create_pass"]);
            $this->view->setVar("edata", $encrypted_data); //pass to view the encrypted data

            //load javascript
            $this->_loadJsModules($this->account_pass_recovery_conf["js_modules"]);
        }
        catch (Exception $e) {
            $this->logger->error("AccountPass::newAction -> Error in account activation, encrypted data (" . $encrypted_data . "). Trace: " . $e->getMessage());
            $this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
        }
    }

    /**
     * Ajax - Send Recovery password email Message with further instructions
     */
    public function sendRecoveryInstructionsAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams([
            "email"                 => "email",
            "@g-recaptcha-response" => "string",
        ]);

        //google reCaptcha helper
        $recaptcha = new ReCaptcha($this->config->app->google->reCaptchaKey);

        //check valid reCaptcha
        if (empty($data["g-recaptcha-response"]) || !$recaptcha->isValid($data["g-recaptcha-response"])) {
            //show error message
            return $this->_sendJsonResponse(200, $this->account_pass_recovery_conf["trans"]["recaptcha_failed"], "alert");
        }

        //check if user exists is a active account
        $user_class = AppModule::getClass("user");
        $user       = $user_class::getUserByEmail($data["email"], "enabled");

        //if user not exists, send message
        if (!$user)
            $this->_sendJsonResponse(200, $this->account_pass_recovery_conf["trans"]["account_not_found"], "alert");

        //send email message with password recovery steps
        $this->_sendMailMessage("sendMailForPasswordRecovery", $user->id);

        //set a flash message to show on account controller
        $this->flash->success(str_replace("{email}", $data["email"], $this->account_pass_recovery_conf["trans"]["pass_mail_sent"]));

        //send JSON response
        $this->_sendJsonResponse(200, ["redirect" => "signIn"]);
    }

    /**
     * Ajax - Saves a new password set by the user in the post-recovery password view
     */
    public function saveNewPasswordAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams([
            "edata" => "string",
            "pass"  => "string"
        ]);

        //validate encrypted data
        $payload = false;
        try {
            //get model classes
            $user_class   = AppModule::getClass("user");
            $tokens_class = AppModule::getClass("user_token");

            $edata = $tokens_class::handleUserTokenValidation($data["edata"]);
            list($user_id, $token_type, $token) = $edata;

            //get user
            $user = $user_class::getById($user_id);

            if (!$user)
                throw new Exception("got an invalid user (id:" . $user_id . ") when validating encrypted data.");

            //save new account flag state
            $user->update(["pass" => $this->security->hash($data["pass"])]);

            //get token object
            $token = $tokens_class::getTokenByUserAndValue($user_id, $token_type, $token);
            //delete user token
            $token->delete();

            //set a flash message to show on account controller
            $this->flash->success($this->account_pass_recovery_conf["trans"]["new_pass_saved"]);

            //abstract parent controller
            $this->_setUserSessionAsLoggedIn($user->id);
        }
        catch (Exception $e) {
            $this->logger->error("AccountPass::saveNewPasswordAction -> Error saving new password. Trace: ".$e->getMessage());
            return $this->_sendJsonResponse(400);
        }

        //send JSON response
        $this->_sendJsonResponse(200, ["redirect" => "signIn"]);
    }
}
