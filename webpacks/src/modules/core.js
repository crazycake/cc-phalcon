/**
 * Core: main app module.
 * Dependencies: `jQuery.js`, `VueJs`, `q.js`, `lodash.js`.
 * Required scope vars: `{APP, UA}`.
 * Frontend Framework supported: `Foundation v.6.x`, `Bootstrap v3.x`
 * @class Core
 */

import ui  from "./core.ui.js";

export default function() {

    //Check that App Global scope vars are defined
    if (typeof APP == "undefined" || typeof UA == "undefined")
        throw new Error("Core -> Error: APP or UA global vars are not defined!");

    //self context
    var self = this;

    //++ Properties

    /**
     * @property ui
     * @type {object}
     */
    self.ui = new ui();

    /**
     * @property modules
     * @type {Boolean}
     */
    self.modules = {};

    /**
     * @property framework ```Foundation, Bootstrap, none```
     * @type {string}
     */
    self.framework = "none";

    /**
     * @property window.core
     * @type {object}
     */
    window.core = self;

    //++ jQuery setup

    $.ajaxSetup({
        cache : true  //improvement for third-party libs like Facebook.
    });

    //++ Methods ++

    /**
     * Set modules automatically for require function
     * @method init
     * @param {Array} modules - The required modules
     */
    self.setModules = function(modules) {

        if (!modules.length)
            return;

        for (var i = 0; i < modules.length; i++) {

            var mod = modules[i];

            if (typeof mod.name !== "undefined")
                self.modules[mod.name] = mod;
        }
    };

    /**
     * Core Ready Event, called automatically after loading modules.
     * @method ready
     */
    self.ready = function() {

        //load fast click for mobile
        if (UA.isMobile && typeof FastClick != "undefined")
            FastClick.attach(document.body);

        //load Foundation framework
        if (typeof Foundation != "undefined")
            self.initFoundation();
        //load Bootstrap framework
        else if (typeof $().emulateTransitionEnd == "function")
            self.initBootstrap();

        //load forms module
        if (typeof self.modules.forms !== "undefined")
            self.modules.forms.loadForms();

        //load UI module
        if (typeof self.ui !== "undefined")
            self.ui.init();

        if (APP.dev) { console.log("Core Ready!"); }
    };

    /**
     * Foundation Initializer, loaded automatically.
     * Call this function if an element has loaded dynamically and uses foundation js plugins.
     * @method initFoundation
     * @param {Object} element - The jQuery element, default is document object.
     */
    self.initFoundation = function(element) {

        if (APP.dev) { console.log("Core -> Initializing Foundation..."); }

        //check default element
        if (typeof element == "undefined")
            element = $(document);
        else if (element instanceof jQuery === false)
            element = $(element);

        //set framework
        self.framework = "foundation";
        //init foundation
        element.foundation();
    };

    /**
     * Bootstrap Initializer, loaded automatically.
     * @method initBootstrap
     */
    self.initBootstrap = function() {

        if (APP.dev) { console.log("Core -> Initializing Bootstrap..."); }

        //set framework
        self.framework = "bootstrap";
    };

    /**
     * Loads App modules, if module has a viewModel binds it to DOM automatically
     * @method loadModules
     * @param {Object} modules - The modules oject
     */
    self.loadModules = function(modules) {

        var mod_name, mod, vm, data;

        //1) call inits
        for (mod_name in modules) {

            //check module exists
            if (_.isUndefined(self.modules[mod_name])) {
                console.warn("Core -> Attempting to load an undefined view module (" + mod_name + ").");
                continue;
            }

            //get module
            mod  = self.modules[mod_name];
            data = modules[mod_name];

            //check if module has init method & call it
            if (_.isFunction(mod.init))
                mod.init(data);
        }

        //2) load viewModels
        for (mod_name in modules) {

            //check module exists
            if (_.isUndefined(self.modules[mod_name]))
                continue;

            //get module
            mod = self.modules[mod_name];

            //bind model to DOM?
            if (!_.isObject(mod.vm))
                continue;

            vm = _.assign({
                //vue element selector
                el : "#vue-" + mod_name
            }, mod.vm);

            if (APP.dev) { console.log("Core -> Binding " + mod_name + " View Model", vm); }

            //set new Vue instance (object prop updated)
            mod.vm = new Vue(vm);
        }
    };

    /**
     * Ajax request with form validation.
     * Validates a form, if valid, sends a promise request with Q lib.
     * @link https://github.com/kriskowal/q
     * @method ajaxRequest
     * @param  {Object} service - The URL service
     * @param  {Object} form - The form HTML object
     * @param  {Object} extended_data - An object to be extended as sending data (optional)
     * @param  {Object} events - Event handler object
     * @return {Object} Q promise
     */
    self.ajaxRequest = function(service, form, extended_data, events) {

        //validation, service is required
        if (typeof service === "undefined")
            throw new Error("Core -> ajaxRequest invalid inputs!");

        if (typeof form === "undefined")
            form = null;

        //define payload
        var payload = {};
        var submit_btn;

        //check for a non jquery object
        if (!_.isNull(form) && form instanceof jQuery === false)
            form = $(form);

        //check form element has a Foundation data-invalid attribute
        if (!_.isNull(form)) {

            //validate abide form
            if (!self.modules.forms.isFormValid(form))
                return Promise.resolve();

            //serialize data to URL encoding
            payload = form.serializeArray();
            //disable submit button
            submit_btn = form.find("button");

            if (submit_btn.length)
                submit_btn.attr("disabled","disabled");
        }

        //extend more data?
        if (_.isObject(extended_data)) {

            //check if element is null
            if ( _.isNull(form) )
                _.assign(payload, extended_data); //considerar objetos livianos (selectionDirection error)
            else
                payload.push({ name : "extended", value : extended_data });  //serialized object struct
        }

        //append CSRF token
        if (service.method == "POST") {

            //check if element is null
            if (_.isNull(form))
                payload[UA.tokenKey] = UA.token; //object style
            else
                payload.push({ name : UA.tokenKey, value : UA.token }); //serialized object struct
        }

        //make ajax request with promises
        return Promise.resolve(
            $.ajax({
                //request properties
                type     : service.method,
                url      : service.url,
                data     : payload,
                dataType : "json",
                timeout  : 14000 //timeout in seconds
            })
            //handle fail event for jQuery ajax request
            .fail(self.handleAjaxError)
        )
        //handle response
        .then(function(data) {

            //handle ajax response
            if (!self.handleAjaxResponse(data, events))
                return false;

            var payload = data.response.payload;

            //set true value if payload is null
            return _.isNull(payload) ? true : payload;
        })
        //promise finisher
        .finally(function() {

            if (_.isObject(submit_btn) && submit_btn.length)
                submit_btn.removeAttr("disabled"); //enable button?

            return true;
        });
    };

    /**
     * Ajax Response Handler, validates if response data has no errors.
     * Also can set event-callback function in case the response is an error.
     * @method handleAjaxResponse
     * @param  {Object} data - The JSON response object
     * @param  {Object} events - Alert Events Handler
     */
    self.handleAjaxResponse = function(data, events) {

        //undefined data?
        if (_.isUndefined(data) || _.isNull(data))
            return false;

        if (APP.dev) { console.log("Core -> handleAjaxResponse: ", data); }

        //check for error
        var response = data.response;

        var onErrorResponse = function() {

            var onCloseFn = null;
            var onClickFn = null;

            //set the callback function if set in error events functions
            if (_.isString(response.namespace) && _.isObject(events)) {

                if (_.isObject(events.onClose) && !_.isUndefined(events.onClose[response.namespace]))
                    onCloseFn = _.isFunction(events.onClose[response.namespace]) ? events.onClose[response.namespace] : null;

                if (_.isObject(events.onClick) && !_.isUndefined(events.onClick[response.namespace]))
                    onClickFn = _.isFunction(events.onClick[response.namespace]) ? events.onClick[response.namespace] : null;
             }

            //call the alert message
            self.ui.showAlert(response.payload, response.type, onCloseFn, onClickFn);
        };

        //check for ajax error
        if (response.status == "error") {

            self.handleAjaxError(response.code, response.error);
            return false;
        }
        //app errors
        else if (typeof response.type != "undefined") {

            onErrorResponse();
            return false;
        }
        //redirection
        else if (!_.isUndefined(response.redirect)) {

            self.redirectTo(response.redirect);
            return true;
        }
        //no errors, return true
        else {
            return true;
        }
    };

    /**
     * Ajax Error Response Handler
     * @method handleAjaxError
     * @param  {Object} x - The jQuery Response object
     * @param  {String} error - The jQuery error object
     */
    self.handleAjaxError = function(x, error) {

        //set message null as default
        var message = null;
        var log     = "";
        var code    = _.isObject(x) ? x.status : x;
        var text    = _.isObject(x) ? x.responseText : code;

        //sever parse error
        if (error == "parsererror") {
            message = APP.TRANS.ALERTS.INTERNAL_ERROR;
            log     = "Core -> parsererror: " + text;
        }
        //timeout
        else if (error == "timeout" || code == 408) {
            message = APP.TRANS.ALERTS.SERVER_TIMEOUT;
            log     = "Core -> timeout: " + x;
        }
        //400 bad request
        else if (code == 400) {
            message = APP.TRANS.ALERTS.BAD_REQUEST;
            log     = "Core -> bad request: " + code;
        }
        //403 access forbidden
        else if (code == 403) {
            message = APP.TRANS.ALERTS.ACCESS_FORBIDDEN;
            log     = "Core -> access forbidden: " + code;
        }
        //404 not found
        else if (code == 404) {
            message = APP.TRANS.ALERTS.NOT_FOUND;
            log     = "Core -> not found: " + code;
        }
        //method now allowed (invalid GET or POST method)
        else if (code == 405) {
            message = APP.TRANS.ALERTS.NOT_FOUND;
            log     = "Core -> method now allowed: " + code;
        }
        //invalid CSRF token
        else if (code == 498) {
            message = APP.TRANS.ALERTS.CSRF;
            log     = "Core -> invalid CSRF token: " + code;
        }
        else {
            message = APP.TRANS.ALERTS.INTERNAL_ERROR;
            log     = "Core -> unknown error: " + text;
        }

        //show log?
        if (APP.dev && log.length) { console.log(log); }

        //show the alert message
        self.ui.showAlert(message, "warning");
    };

    /**
     * Redirect router method
     * TODO: detect protocol schema.
     * @method redirectTo
     * @param  {String} uri - The webapp URI
     */
    self.redirectTo = function(uri) {

        var uri_map = {
           notFound : "error/notFound"
        };

        //check if has a uri map
        if (!_.isUndefined(uri_map[uri]))
            uri = uri_map[uri];

        //redirect to contact
        location.href = APP.baseUrl + uri;
    };

    /**
     * App debug methods
     * @method debug
     * @param  {String} option - The option string [ajax_timeout, ajax_loading, dom_events]
     * @param  {Object} object - A jQuery or HTML object element
     */
    self.debug = function(option, object) {

        var assert = true;

        //timeout simulator
        if (option == "timeout") {
            self.ajaxRequest( { method : "GET", url : "http://250.21.0.180:8081/fake/path/" } )
            .done();
        }
        //get dom events associated to a given object
        else if (option == "events") {
            var obj = _.isObject(object) ? object[0] : $(object)[0];
            return $._data(obj, "events");
        }
        else {
            assert = false;
        }

        //default return
        return "Core -> Assert ("+assert+")";
    };
}
