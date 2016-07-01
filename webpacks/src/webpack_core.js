/**
 * Core WebPack
 * ES6 required (babel)
 * @module WebpackCore
 */

//load main libraries
import "html5shiv";
import "fastclick";
import "lodash";
import "vue";
import "bluebird";
import "js-cookie";
import "jquery";
import "velocity";
import "velocity.ui";
import "fg-loadcss";

//plugins
import "./plugins/jquery.extended";
import "./plugins/jquery.cclayer";
import "./plugins/jquery.ccdialog";
import "./plugins/jquery.formValidation";
import "./plugins/jquery.formValidation.bootstrap4";
import "./plugins/jquery.formValidation.foundation6";

//modules
import core from "./modules/core.js";
import auth from "./modules/auth.js";
import forms from "./modules/forms.js";
import passRecovery from "./modules/passRecovery.js";
import facebook from "./modules/facebook.js";

/* Load modules */

//export core property
module.exports.core = core;

//set modules
core.setModules([
    auth,
    forms,
    passRecovery,
    facebook
]);
