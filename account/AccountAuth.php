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
use CrazyCake\Utils\FormHelper;
use CrazyCake\Utils\ReCaptcha;

/**
 * Account Authentication
 */
trait AccountAuth
{
    /**
     * Before Render SignIn View Listener
     */
    abstract public function beforeRenderSignInView();

    /**
     * Before Render SignUp View Listener
     */
    abstract public function beforeRenderSignUpView();

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
    public function initAccountAuth($conf = array())
    {
        $this->account_auth_conf = $conf;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * View - Sign In (LogIn) action
     */
    public function signInAction()
    {
        //if loggedIn redirect to account
        $this->_redirectToAccount(true);

        //view vars
        $this->view->setVar("html_title", $this->account_auth_conf['trans']['title_sign_in']);
        //load js reCaptcha?
        $this->view->setVar("js_recaptcha", $this->account_auth_conf['js_recaptcha']);

        //call abstract method
        $this->beforeRenderSignInView();
    }

    /**
     * View - Sign Up (Register) action
     */
    public function signUpAction()
    {
        //if loggedIn redirect to account
        $this->_redirectToAccount(true);

        //view vars
        $this->view->setVar("html_title", $this->account_auth_conf['trans']['title_sign_up']);

        //check sign_up session data for auto completion field
        $signup_session = $this->_getSessionObjects("signup_session");
        $this->_destroySessionObjects("signup_session");
        $this->view->setVar("signup_session", $signup_session);

        //send birthday data for form
        if(isset($this->account_auth_conf['profile_request_params']['birthday']))
            $this->view->setVar("birthday_elements", FormHelper::getBirthdaySelectors());

        //call abstract method
        $this->beforeRenderSignUpView();
    }

    /**
     * Handler - Activation link handler, can dispatch to a view
     * @param string $encrypted_data - The encrypted data
     */
    public function activationAction($encrypted_data = null)
    {
        //if user is already loggedIn redirect
        $this->_redirectToAccount(true);

        //get decrypted data
        try {
            //get model classes
            $users_class  = $this->_getModuleClass('users');
            $tokens_class = $this->_getModuleClass('users_tokens');
            //handle the encrypted data with parent controller
            $data = $tokens_class::handleUserTokenValidation($encrypted_data);
            //assign values
            list($user_id, $token_type, $token) = $data;

            //check user-flag if is really pending
            $user = $users_class::getObjectById($user_id);

            if (!$user || $user->account_flag != 'pending')
                throw new Exception("user (id: ".$user->id.") don't have a pending account flag.");

            //save new account flag state
            $user->update(["account_flag" => 'enabled']);

            //get token object and remove it
            $token = $tokens_class::getTokenByUserAndValue($user_id, $token_type, $token);
            //delete user token
            $token->delete();

            //set a flash message to show on account controller
            $this->flash->success($this->account_auth_conf['trans']['activation_success']);

            //success login
            $this->_setUserSessionAsLoggedIn($user_id);
            $this->_handleResponseOnLoggedIn();
        }
        catch (Exception $e) {

            $data = $encrypted_data ? $this->cryptify->decryptForGetResponse($encrypted_data) : "invalid hash";

            $this->logger->error('AccountAuth::activationAction -> Error in account activation, decrypted data ('.$data."). Msg: ".$e->getMessage());
            $this->dispatcher->forward(["controller" => "errors", "action" => "expired"]);
        }
    }

    /**
     * Handler - Logout
     */
    public function logoutAction()
    {
        $this->_destroyUserSessionAndRedirect();
    }

    /**
     * Ajax - Login user by email & pass
     */
    public function loginAction()
    {
        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams([
            'email' => 'email',
            'pass'  => 'string'
        ]);

        //get model classes
        $users_class = $this->_getModuleClass('users');
        //find this user
        $user = $users_class::getUserByEmail($data['email']);

        //check user & given hash with the one stored (wrong combination)
        if (!$user || !$this->security->checkHash($data['pass'], $user->pass)) {
            $this->_sendJsonResponse(200, $this->account_auth_conf['trans']['auth_failed'], "alert");
        }

        //check user account flag
        if ($user->account_flag != 'enabled') {
            //set message
            $msg = $this->account_auth_conf['trans']['account_disabled'];
            $namespace = null;
            //check account is pending
            if ($user->account_flag == 'pending') {
                $msg = $this->account_auth_conf['trans']['account_pending'];
                //set name for javascript view
                $namespace = 'ACCOUNT_PENDING';
            }

            //show error message with custom handler
            $this->_sendJsonResponse(200, $msg, "warning", $namespace);
        }

        //success login
        $this->_setUserSessionAsLoggedIn($user->id);
        $this->_handleResponseOnLoggedIn();
    }

    /**
     * Ajax - Register user by email
     */
    public function registerAction()
    {
        $default_params = [
            'email'      => 'email',
            'pass'       => 'string',
            'first_name' => 'string',
            'last_name'  => 'string'
        ];

        $setting_params = isset($this->account_auth_conf['profile_request_params']) ? $this->account_auth_conf['profile_request_params'] : array();

        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams(array_merge($default_params, $setting_params));

        //validate names
        $nums = '0123456789';
        if(strcspn($data['first_name'], $nums) != strlen($data['first_name']) ||
           strcspn($data['last_name'], $nums) != strlen($data['last_name'])) {

            $this->_sendJsonResponse(200, $this->account_auth_conf['trans']['invalid_names'], "alert");
        }

        //format to capitalized name
        $data["first_name"] = mb_convert_case($data["first_name"], MB_CASE_TITLE, 'UTF-8');
        $data["last_name"]  = mb_convert_case($data["last_name"], MB_CASE_TITLE, 'UTF-8');

        //get model classes
        $users_class = $this->_getModuleClass('users');
        //set pending email confirmation status
        $data['account_flag'] = 'pending';

        //Save user, validations are applied in model
        $user = new $users_class();

        //if user dont exists, show error message
        if (!$user->save($data))
            $this->_sendJsonResponse(200, $user->filterMessages(), "alert");

        //set a flash message to show on account controller
        $this->flash->success(str_replace("{email}", $user->email, $this->account_auth_conf['trans']['activation_pending']));
        //send activation account email
        $this->_sendMailMessage("sendMailForAccountActivation", $user->id);
        //force redirection
        $this->_handleResponseOnLoggedIn("signIn", null, false);
    }

    /**
     * Ajax [POST] - Resend activation mail message (fallback in case mail sending failed)
     */
    public function resendActivationMailMessageAction()
    {
        $data = $this->_handleRequestParams([
            'email'                => 'email',
            '@g-recaptcha-response' => 'string'
        ]);

        //google reCaptcha helper
        $recaptcha = new ReCaptcha($this->config->app->google->reCaptchaKey);

        //check valid reCaptcha
        if (empty($data['g-recaptcha-response']) || !$recaptcha->isValid($data['g-recaptcha-response'])) {
            //show error message
            return $this->_sendJsonResponse(200, $this->account_auth_conf['trans']['recaptcha_failed'], "alert");
        }

        //get model classes
        $users_class = $this->_getModuleClass('users');
        $user = $users_class::getUserByEmail($data['email'], 'pending');

        //check if user exists is a pending account
        if (!$user)
            $this->_sendJsonResponse(200, $this->account_auth_conf['trans']['account_not_found'], 'alert');

        //send email message with password recovery steps
        $this->_sendMailMessage("sendMailForAccountActivation", $user->id);

        //set payload
        $payload = str_replace("{email}", $data['email'], $this->account_auth_conf['trans']['activation_pending']);

        //send JSON response
        $this->_sendJsonResponse(200, $payload);
        return;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */
}
