/**
 * Core WebPack
 * @module WebpackCore
 */

//load main libraries
require('html5shiv');
require('fastclick');
require('lodash');
require('vue');
require('q');
require('js-cookie');
require('jquery');
require('velocity');
require('velocity.ui');

//plugins
require('jquery.extended');
require('jquery.modality');
require('jquery.modality.dialog');
require('jquery.scrollTo');
require('jquery.formValidation');

/* Load modules */

//Core
var core = new (require('./modules/core.js'))();
//export core & make it a global var
module.exports.core = core;

var modules = [
    new (require('./modules/auth.js'))(),
    new (require('./modules/forms.js'))(),
    new (require('./modules/passRecovery.js'))(),
    new (require('./modules/facebook.js'))()
];

//set modules
core.setModules(modules);
