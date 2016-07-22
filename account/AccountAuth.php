<?php
/**
 * Account Manager Trait
 * This class has common actions for account manager controllers
 * Requires a Frontend or Backend Module
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Account;

//imports
use Phalcon\Exception;

//core
use CrazyCake\Phalcon\AppModule;
use CrazyCake\Helpers\Forms;
use CrazyCake\Helpers\ReCaptcha;

/**
 * Account Authentication
 */
trait AccountAuth
{
    /**
     * Event on user logged in
     */
    abstract public function onLoggedIn($user_id);

    /**
     * Disptach event on logged in
     * @param string $uri - target Uri
     * @param array $payload - Optional data
     */
    abstract public function onLoggedInDispatch($uri = "account", $payload = null);

    /**
     * Session Destructor with Autoredirection (logout)
     */
    abstract public function onLogout($uri = "signIn");

    /**
     * Config var
     * @var array
     */
    public $account_auth_conf;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * This method must be call in constructor parent class
     * @param array $conf - The config array
     */
    public function initAccountAuth($conf = [])
    {
        //defaults
        $defaults = [
            "js_recaptcha" => false,
            "oauth"        => false,
        ];

        $this->account_auth_conf = array_merge($conf, $defaults);
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * View - Sign In (LogIn) action
     */
    public function signInAction()
    {
        //if loggedIn redirect to account
        $this->redirectToAccount(true);

        //view vars
        $this->view->setVar("html_title", $this->account_auth_conf["trans"]["TITLE_SIGN_IN"]);
        //load js reCaptcha?
        $this->view->setVar("js_recaptcha", $this->account_auth_conf["js_recaptcha"]);

        //call abstract method
        if(method_exists($this, "beforeRenderSignInView"))
            $this->beforeRenderSignInView();
    }

    /**
     * View - Sign Up (Register) action
     */
    public function signUpAction()
    {
        //if loggedIn redirect to account
        $this->redirectToAccount(true);

        //view vars
        $this->view->setVar("html_title", $this->account_auth_conf["trans"]["TITLE_SIGN_UP"]);

        //check sign_up session data for auto completion field
        $signup_session = $this->getSessionObjects("signup_session");

        $this->view->setVar("signup_session", $signup_session);
        $this->destroySessionObjects("signup_session");

        //send birthday data for form
        if (isset($this->account_auth_conf["required_fields"]["birthday"]))
            $this->view->setVar("birthday_elements", Forms::getBirthdaySelectors());

        //call abstract method
        if(method_exists($this, "beforeRenderSignUpView"))
            $this->beforeRenderSignUpView();
    }

    /**
     * Handler - Activation link handler, can dispatch to a view
     * @param string $encrypted_data - The encrypted data
     */
    public function activationAction($encrypted_data = null)
    {
        //if user is already loggedIn redirect
        $this->redirectToAccount(true);

        //get decrypted data
        try {
            //get model classes
            $user_class   = AppModule::getClass("user");
            $token_class = AppModule::getClass("user_token");

            //handle the encrypted data with parent controller
            $data = $token_class::handleEncryptedValidation($encrypted_data);
            //assign values
            list($user_id, $token_type, $token) = $data;

            //check user-flag if is really pending
            $user = $user_class::getById($user_id);

            if (!$user || $user->account_flag != "pending")
                throw new Exception("user (id: ".$user->id.") dont have a pending account flag.");

            //save new account flag state
            $user->update(["account_flag" => "enabled"]);

            //get token object and remove it
            $token = $token_class::getTokenByUserAndValue($user_id, $token_type, $token);
            //delete user token
            $token->delete();

            //set a flash message to show on account controller
            $this->flash->success($this->account_auth_conf["trans"]["ACTIVATION_SUCCESS"]);

            //success login
            $this->onLoggedIn($user_id);
            //session
            $this->onLoggedInDispatch();
        }
        catch (Exception $e) {

            $data = $encrypted_data ? $this->cryptify->decryptData($encrypted_data) : "invalid hash";

            $this->logger->error("AccountAuth::activationAction -> Error in account activation, decrypted data (".$data."). Msg: ".$e->getMessage());
            $this->dispatcher->forward(["controller" => "error", "action" => "expired"]);
        }
    }

    /**
     * Handler - Logout
     */
    public function logoutAction()
    {
        //session controller
        $this->onLogout();
    }

    /**
     * Action - Login user by email & pass
     */
    public function loginAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->handleRequest([
            "email" => "email",
            "pass"  => "string"
        ], "POST");

        //get model classes
        $user_class  = AppModule::getClass("user");
        $token_class = AppModule::getClass("user_token");

        //find this user
        $user = $user_class::getUserByEmail($data["email"]);

        //check user & given hash with the one stored (wrong combination)
        if (!$user || !$this->security->checkHash($data["pass"], $user->pass)) {
            $this->jsonResponse(200, $this->account_auth_conf["trans"]["AUTH_FAILED"], "alert");
        }

        //check user account flag
        if ($user->account_flag != "enabled") {
            //set message
            $msg       = $this->account_auth_conf["trans"]["ACCOUNT_DISABLED"];
            $namespace = null;
            //check account is pending
            if ($user->account_flag == "pending") {

                $msg = $this->account_auth_conf["trans"]["ACCOUNT_PENDING"];
                //set name for javascript view
                $namespace = "ACCOUNT_PENDING";
            }

            //show error message with custom handler
            $this->jsonResponse(200, $msg, "warning", $namespace);
        }

        //set payload
        $payload = null;

        //for api oauth
        if($this->account_auth_conf["oauth"])
            $payload = $token_class::newTokenIfExpired($user->id, "access");

        //success login
        $this->onLoggedIn($user->id);

        //session controller, dispatch response
        $this->onLoggedInDispatch("account", $payload);
    }

    /**
     * Ajax - Register user by email
     */
    public function registerAction()
    {
        $default_params = [
            "email"      => "email",
            "pass"       => "string",
            "first_name" => "string",
            "last_name"  => "string"
        ];

        $setting_params = isset($this->account_auth_conf["required_fields"]) ? $this->account_auth_conf["required_fields"] : [];

        //validate and filter request params data, second params are the required fields
        $data = $this->handleRequest(array_merge($default_params, $setting_params),
                                     "POST");

        //validate names
        $nums = "0123456789";
        if (strcspn($data["first_name"], $nums) != strlen($data["first_name"]) ||
           strcspn($data["last_name"], $nums) != strlen($data["last_name"])) {

            $this->jsonResponse(200, $this->account_auth_conf["trans"]["INVALID_NAMES"], "alert");
        }

        //format to capitalized name
        $data["first_name"] = mb_convert_case($data["first_name"], MB_CASE_TITLE, "UTF-8");
        $data["last_name"]  = mb_convert_case($data["last_name"], MB_CASE_TITLE, "UTF-8");

        //get model classes
        $user_class = AppModule::getClass("user");
        //set pending email confirmation status
        $data["account_flag"] = "pending";

        //Save user, validations are applied in model
        $user = new $user_class();

        //if user dont exists, show error message
        if (!$user->save($data))
            $this->jsonResponse(200, $user->allMessages(), "alert");

        //set a flash message to show on account controller
        $this->flash->success(str_replace("{email}", $user->email, $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]));
        //send activation account email
        $this->sendMailMessage("accountActivation", $user->id);
        //force redirection
        $this->onLoggedInDispatch(false);
    }

    /**
     * Ajax [POST] - Resend activation mail message (fallback in case mail sending failed)
     */
    public function resendActivationMailMessageAction()
    {
        $data = $this->handleRequest([
            "email"                 => "email",
            "@g-recaptcha-response" => "string"
        ], "POST");

        //google reCaptcha helper
        $recaptcha = new ReCaptcha($this->config->app->google->reCaptchaKey);

        //check valid reCaptcha
        if (empty($data["g-recaptcha-response"]) || !$recaptcha->isValid($data["g-recaptcha-response"])) {
            //show error message
            return $this->jsonResponse(200, $this->account_auth_conf["trans"]["RECAPTCHA_FAILED"], "alert");
        }

        //get model classes
        $user_class = AppModule::getClass("user");
        $user       = $user_class::getUserByEmail($data["email"], "pending");

        //check if user exists is a pending account
        if (!$user)
            $this->jsonResponse(200, $this->account_auth_conf["trans"]["ACCOUNT_NOT_FOUND"], "alert");

        //send email message with password recovery steps
        $this->sendMailMessage("accountActivation", $user->id);

        //set payload
        $payload = str_replace("{email}", $data["email"], $this->account_auth_conf["trans"]["ACTIVATION_PENDING"]);

        //send JSON response
        $this->jsonResponse(200, $payload);
        return;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */
}
