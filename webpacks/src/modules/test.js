/**
 * Test KO Module with Foundation
 */

//import foundation
require("foundation");
//import form validation
require('jquery.formValidation.foundation');

//creates a KO model
var model = function() {

    //++ Module
    var self        = this;
    self.moduleName = "test";

    //++ UI Selector
    _.assign(APP.UI, {
        //settings
        alert : {
            position    : "fixed",
            top         : "belowHeader",
            topForSmall : "0"
        },
        loading : {
            position    : "fixed",
            top         : "14%",
            topForSmall : "20%",
            center      : true
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
