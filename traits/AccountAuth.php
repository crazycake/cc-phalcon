<?php
/**
 * Account Manager Trait
 * This class has common actions for account manager controllers
 * Requires a Frontend or Backend Module with CoreController and SessionTrait
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Traits;

//imports
use Phalcon\Exception;
use CrazyCake\Utils\DateHelper;
use CrazyCake\Utils\ReCaptcha;

trait AccountAuth
{
	/**
     * abstract required methods
     */
    abstract public function setConfigurations();
    abstract public function beforeRenderSignInView();
    abstract public function beforeRenderSignUpView();

    /**
     * Config var
     * @var array
     */
    public $accountConfig;

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * View - Sign In (LogIn) action
     */
    public function signInAction()
    {
        //if loggedIn redirect to account
        $this->_redirectToAccount(true);

        //view vars
        $this->view->setVar("html_title", $this->accountConfig['text_title_sign_in']);
        $this->view->setVar("js_recaptcha", true); //load reCaptcha

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
        $this->view->setVar("html_title", $this->accountConfig['text_title_sign_up']);

        //send birthday data for form
        if(isset($this->accountConfig['birthday_form_fields']) && $this->accountConfig['birthday_form_fields'])
            $this->view->setVar("bday_elements", $this->__getBirthdaySelectors());

        //call abstract method
        $this->beforeRenderSignUpView();
    }

    /**
     * Handler - Activation link handler, can dispatch to a view
     * @param string $encrypted_data
     */
    public function activationAction($encrypted_data = null)
    {
        //if user is already loggedIn redirect
        $this->_redirectToAccount(true);

        //get decrypted data
        try {
            //get model classes
            $users_class  = $this->getModuleClassName('users');
            $tokens_class = $this->getModuleClassName('users_tokens');
            //handle the encrypted data with parent controller
            $data = $tokens_class::handleUserTokenValidation($encrypted_data);
            //assign values
            list($user_id, $token_type, $token) = $data;

            //check user-flag if is really pending
            $user = $users_class::getObjectById($user_id);

            if (!$user || $user->account_flag != 'pending')
                throw new Exception("user (id: ".$user->id.") don't have a pending account flag.");

            //save new account flag state
            $user->update( array("account_flag" => 'enabled') );

            //get token object and remove it
            $token = $tokens_class::getTokenByUserAndValue($user_id, $token_type, $token);
            //delete user token
            $token->delete();

            //set a flash message to show on account controller
            $this->flash->success($this->accountConfig['text_activation_success']);

            //save session data
            $this->_setUserSessionAsLoggedIn($user_id);
            $this->_redirectToAccount();
        }
        catch (Exception $e) {

            if(!isset($user_id))
                $user_id = "unknown";

            $this->logger->error('AccountAuth::activationAction -> Error in account activation, encrypted data ('.$encrypted_data.", id: ".$user_id."). Trace: ".$e->getMessage());
            $this->dispatcher->forward(array("controller" => "errors", "action" => "expired"));
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
        $data = $this->_handleRequestParams(array(
            'email' => 'email',
            'pass'  => 'string'
        ));

        //get model classes
        $users_class = $this->getModuleClassName('users');
        //find this user
        $user = $users_class::getUserByEmail($data['email']);

        //check user & given hash with the one stored (wrong combination)
        if (!$user || !$this->security->checkHash($data['pass'], $user->pass)) {
            $this->_sendJsonResponse(200, $this->accountConfig['text_auth_failed'], 'alert');
        }

        //check user account flag
        if ($user->account_flag != 'enabled') {
            //set message
            $msg = $this->accountConfig['text_account_disabled'];
            $namespace = null;
            //check account is pending
            if ($user->account_flag == 'pending') {
                $msg = $this->accountConfig['text_account_pending'];
                //set name for javascript view
                $namespace = 'ACCOUNT_PENDING';
            }

            //show error message with custom handler
            $this->_sendJsonResponse(200, $msg, true, $namespace);
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
        $default_params = array(
            'email'      => 'email',
            'pass'       => 'string',
            'first_name' => 'string',
            'last_name'  => 'string'
        );

        $setting_params = isset($this->accountConfig['register_request_params']) ? $this->accountConfig['register_request_params'] : array();

        //validate and filter request params data, second params are the required fields
        $data = $this->_handleRequestParams(array_merge($default_params, $setting_params));

        //get model classes
        $users_class = $this->getModuleClassName('users');
        //set pending email confirmation status
        $data['account_flag'] = 'pending';

        //Save user, validations are applied in model
        $user = new $users_class();

        //if user dont exists, show error message
        if (!$user->save($data))
            $this->_sendJsonResponse(200, $user->filterMessages(), true);

        //send activation account email
        $this->_sendAsyncMailMessage($this->accountConfig['method_mailer_activation'], $user->id);
        //set a flash message to show on account controller
        $this->flash->success(str_replace("{email}", $user->email, $this->accountConfig['text_activation_pending']));
        //NOTE: clean any session redirections
        $this->_cleanSessionRedirection();

        //handle response
        $this->_handleResponseOnLoggedIn();
    }

    /**
     * Ajax - Resend activation mail message (fallback in case mail sending failed)
     */
    public function resendActivationMailMessageAction()
    {
        $data = $this->_handleRequestParams(array(
            'email'                => 'email',
            'g-recaptcha-response' => 'string'
        ));

        //get model classes
        $users_class = $this->getModuleClassName('users');

        //google reCaptcha helper
        $recaptcha = new ReCaptcha($this->config->app->google->reCaptchaKey);

        if (!$recaptcha->checkResponse($data['g-recaptcha-response'])) {
            //show error message
            $this->_sendJsonResponse(200, $this->accountConfig['text_recaptcha_failed'], true);
        }

        //check if user exists is a pending account
        $user = $users_class::getUserByEmail($data['email'], 'pending');

        //if user was not found send error message
        if (!$user)
            $this->_sendJsonResponse(200, $this->accountConfig['text_account_not_found'], 'alert');

        //send email message with password recovery steps
        $this->_sendAsyncMailMessage($this->accountConfig['method_mailer_activation'], $user->id);

        //set payload
        $payload = str_replace("{email}", $data['email'], $this->accountConfig['text_activation_pending']);

        //send JSON response
        $this->_sendJsonResponse(200, $payload);
        return;
    }

    /* --------------------------------------------------- § -------------------------------------------------------- */

    /**
     * Get birthday options form HTML select element
     * @access private
     * @return array
     */
    private function __getBirthdaySelectors()
    {
        //days
        $days_array = array();
        $days_array["0"] = $this->translate->_("Día");
        //loop
        for ($i = 1; $i <= 31; $i++) {
            $prefix = ($i <= 9) ? "_0$i" : "_$i";
            $days_array[$prefix] = $i;
        }

        //months
        $months_array = array();
        $months_array["0"] = $this->translate->_("Mes");
        //loop
        for ($i = 1; $i <= 12; $i++) {
            $prefix = ($i <= 9) ? "_0$i" : "_$i";
            $month = strftime('%m', mktime(0, 0, 0, $i, 1));
            //get abbr month
            $month = DateHelper::getTranslatedMonthName($month, true);
            //set month array
            $months_array[$prefix] = $month;
        }

        //years
        $years_array = array();
        $years_array["0"] = $this->translate->_("Año");
        //loop
        for ($i = (int) date('Y'); $i >= 1914; $i--)
            $years_array["_$i"] = $i;

        return array($years_array, $months_array, $days_array);
    }
}
