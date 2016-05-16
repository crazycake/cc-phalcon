/**
 * Test Module
 */
 /* global APP _ core */
 /* eslint no-undef: "error" */

//import foundation
require("foundation");
//import form validation
require("jquery.formValidation.foundation");

//creates a KO model
var model = function() {

    //++ Module
    var self  = this;
    self.name = "test";

    //++ UI Selector
    _.assign(APP.UI, {
        //settings
        alert : {
            position  : "fixed",
            top       : "belowHeader",
            top_small : "0"
        },
        loading : {
            position  : "fixed",
            top       : "14%",
            top_small : "20%",
            center    : true
        }
    });
};

//model instance
core.modules.test = new model();

core.init();

//load modules
core.loadModules({
     "test"         : null,
     "auth"         : null,
     "forms"        : null,
     "passRecovery" : null
 });

core.ready();
