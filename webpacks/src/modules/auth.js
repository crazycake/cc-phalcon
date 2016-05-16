/**
 * Auth View Model - Handles Auth actions
 * @class Auth
 */
 /* global APP $ _ core */
 /* eslint no-undef: "error" */

export default function() {

    //++ Module
    var self  = this;
    self.name = "auth";

    //++ View Model
    self.vm = {
        data : {
            email : ""
        },
        methods : {}
    };

    //++ UI selectors
	_.assign(APP.UI, {
		sel_account_modal : "#app-modal-account-activation"
	});

    //++ Methods

    /**
     * Register user by email
     * @method registerUserByEmail
     * @param {Object} event - The Event Handler
     */
    self.vm.methods.registerUserByEmail = function(e) {

        //request with promise
        core.ajaxRequest({ method : "POST", url : APP.baseUrl + "auth/register" }, e.target)
        .done();
    };

    /**
     * Login user by email
     * @method loginUserByEmail
     * @param {Object} event - The Event Handler
     */
    self.vm.methods.loginUserByEmail = function(e) {

        //set callback function for specific error message
        var events = {
            onClick : {
                ACCOUNT_PENDING : self.vm.openActivationForm //binded method
            }
        };

        //request with promise
        core.ajaxRequest({ method : "POST", url : APP.baseUrl + "auth/login" }, e.target, null, events)
        .done();
    };

    /**
     * Resend activation mail message
     * @method resendActivationMailMessage
     * @param {Object} event - The Event Handler
     */
    self.vm.methods.resendActivationMailMessage = function(e) {

        //request with promise
        core.ajaxRequest({ method : "POST", url :  APP.baseUrl + "auth/resendActivationMailMessage" }, e.target)
        .then(function(payload) {

            if (!payload) return;

            //modal closer
            core.ui.hideModal($(APP.UI.sel_account_modal));
            //show succes message
            core.ui.showAlert(payload, "success");

        }).done();
    };

    /**
     * Opens a modal for send activation mail action.
     * @method openActivationForm
     */
    self.vm.methods.openActivationForm = function() {

        //reset recaptcha
        core.modules.forms.recaptchaReload();
        //reset form field
        core.modules.forms.revalidateFormField($(APP.UI.sel_recaptcha).parents("form").eq(0), "reCaptchaValue");

        //new modal
        core.ui.newModal($(APP.UI.sel_account_modal));
    };
}
