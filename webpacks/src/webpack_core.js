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
import "q";
import "js-cookie";
import "jquery";
import "velocity";
import "velocity.ui";
import "jquery.scrollTo";

//plugins
import "./plugins/jquery.extended";
import "./plugins/jquery.cclayer";
import "./plugins/jquery.ccdialog";
import "./plugins/jquery.formValidation";
import "./plugins/jquery.formValidation.bootstrap";
import "./plugins/jquery.formValidation.foundation";

//modules
import core from "./modules/core.js";
import auth from "./modules/auth.js";
import forms from "./modules/forms.js";
import passRecovery from "./modules/passRecovery.js";
import facebook from "./modules/facebook.js";

/* Load modules */

//export core & make it a global var
let app = new core();

module.exports.core = app;

//set modules
app.setModules([
    new auth(),
    new forms(),
    new passRecovery(),
    new facebook()
]);
