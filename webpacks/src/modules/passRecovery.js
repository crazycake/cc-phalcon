/**
 * Password Recovery View Model
 * @class PassRecovery
 */
module.exports = function() {

    //++ Module
    var self        = this;
    self.moduleName = "passRecovery";

    //++ View Model
    self.vm = {
        methods : {}
    };

    //++ Methods

    /**
     * Send Recovery Instructions
     * @method sendRecoveryInstructions
     * @param  {Object} event - The Event Handler
     */
    self.vm.methods.sendRecoveryInstructions = function(e) {

        //request with promise
    	core.ajaxRequest({ method : 'POST', url : APP.baseUrl + 'password/sendRecoveryInstructions' }, e.target)
		.done();
    };

    /**
     * Saves new password from recovery password form
     * @method saveNewPassword
     * @param  {Object} event - The Event Handler
     */
    self.vm.methods.saveNewPassword = function(e) {

        //request with promise
        core.ajaxRequest({ method : 'POST', url : APP.baseUrl + 'password/saveNewPassword' }, e.target)
        .done();
    };
};