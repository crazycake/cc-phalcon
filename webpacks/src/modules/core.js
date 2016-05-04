/**
 * App Core: main app module.
 * Dependencies: `jQuery.js`, `VueJs`, `q.js`, `lodash.js`.
 * Required scope vars: `{APP, UA}`.
 * Frontend Framework supported: `Foundation v.6.x`, `Bootstrap v4.x`
 * @class Core
 */

module.exports = function() {

    //Check that App Global scope vars are defined
    if (typeof APP == "undefined" || typeof UA == "undefined")
        throw new Error('App Core -> Error: APP or UA global vars are not defined!');

    //self context
    var self = this;

    //++ Properties

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

    //++ UI vars

    //Set App data for selectors
    if (_.isUndefined(APP.UI)) APP.UI = {};

    //common jQuery selectors
    _.assign(APP.UI, {
        //selectors
        sel_body_wrapper     : "#wrapper",
        sel_header           : "#header",
        sel_footer           : "#footer",
        sel_loading_box      : "#app-loading",
        sel_flash_messages   : "#app-flash",
        sel_alert_box        : "div.app-alert",
        //setting vars
        url_img_fallback     : APP.staticUrl + 'images/icons/icon-image-fallback.png',
        pixel_ratio          : _.isUndefined(window.devicePixelRatio) ? 1 : window.devicePixelRatio
    });

    //set dynamic required props as default values
    if (_.isUndefined(APP.UI.alert))
        APP.UI.alert = { position : "fixed", top : "5%", top_small : "0" };

    if (_.isUndefined(APP.UI.loading))
        APP.UI.loading = { position : "fixed", top : "25%", top_small : "25%" };

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
        else if (typeof $().emulateTransitionEnd == 'function')
            self.initBootstrap();

        //load forms module
        if (typeof core.modules.forms !== "undefined")
            core.modules.forms.loadForms();

        //load UI module
        if (typeof core.modules.ui !== "undefined")
            core.modules.ui.init();

        //ajax setup
        self.setAjaxHandler();
        //load retina images & fallback
        self.retinaImages();
        self.fallbackImages();
        //check server flash messages
        self.showFlashAlerts();

        if (APP.dev) { console.log("App Core -> Ready!"); }
    };

    /**
     * Foundation Initializer, loaded automatically.
     * Call this function if an element has loaded dynamically and uses foundation js plugins.
     * @method initFoundation
     * @param {Object} element - The jQuery element, default is document object.
     */
    self.initFoundation = function(element) {

        if (APP.dev) { console.log("App Core -> Initializing Foundation..."); }

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

        if (APP.dev) { console.log("App Core -> Initializing Bootstrap..."); }

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
                console.warn("App Core -> Attempting to load an undefined view module ("+mod_name+").");
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
                el : '#vue-' + mod_name
            }, mod.vm);

            if (APP.dev) { console.log("App Core -> Binding " + mod_name + " View Model", vm); }

            //set new Vue instance (object prop updated)
            mod.vm = new Vue(vm);
        }
    };

    /**
     * jQuery Ajax Handler, loaded automatically.
     * @method setAjaxHandler
     */
    self.setAjaxHandler = function() {

        //this vars must be declared outside ajaxHandler function
        var ajax_timer;
        var app_loading = self.showLoading(true); //hide by default

        //ajax handler, show loading if ajax takes more than a X secs, only for POST request
        var handler = function(options, show_loading) {

            //only for POST request
            if (options.type.toUpperCase() !== "POST") // && options.type.toUpperCase() !== "GET"
                return;

            //show loading?
            if (show_loading) {
                //clear timer
                clearTimeout(ajax_timer);
                //waiting time to show loading box
                ajax_timer = setTimeout( function() { app_loading.show('fast'); }, 1000);
                return;
            }
            //otherwise clear timer and hide loading
            clearTimeout(ajax_timer);
            app_loading.fadeOut('fast');
        };

        //ajax events
        $(document)
         .ajaxSend(function(event, xhr, options)     { handler(options, true);  })
         .ajaxComplete(function(event, xhr, options) { handler(options, false); })
         .ajaxError(function(event, xhr, options)    { handler(options, false); });
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
            throw new Error("App Core -> ajaxRequest invalid inputs!");

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
                return Q();

            //serialize data to URL encoding
            payload = form.serializeArray();
            //disable submit button
            submit_btn = form.find('button');

            if (submit_btn.length)
                submit_btn.attr('disabled','disabled');
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
        return Q(
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
            if (!core.handleAjaxResponse(data, events))
                return false;

            var payload = data.response.payload;

            //set true value if payload is null
            return _.isNull(payload) ? true : payload;
        })
        //promise finisher
        .fin(function() {

            if (_.isObject(submit_btn) && submit_btn.length)
                submit_btn.removeAttr('disabled'); //enable button?
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

        if (APP.dev) { console.log("App Core [handleAjaxResponse]:", data); }

        //check for error
        var error    = false;
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
            self.showAlert(response.payload, response.type, onCloseFn, onClickFn);
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
        if (error == 'parsererror') {
            message = APP.TRANS.ALERTS.INTERNAL_ERROR;
            log     = "App Core -> parsererror: " + text;
        }
        //timeout
        else if (error == 'timeout' || code == 408) {
            message = APP.TRANS.ALERTS.SERVER_TIMEOUT;
            log     = "App Core -> timeout: " + x;
        }
        //400 bad request
        else if (code == 400) {
            message = APP.TRANS.ALERTS.BAD_REQUEST;
            log     = "App Core -> bad request: " + code;
        }
        //403 access forbidden
        else if (code == 403) {
            message = APP.TRANS.ALERTS.ACCESS_FORBIDDEN;
            log     = "App Core -> access forbidden: " + code;
        }
        //404 not found
        else if (code == 404) {
            message = APP.TRANS.ALERTS.NOT_FOUND;
            log     = "App Core -> not found: " + code;
        }
        //method now allowed (invalid GET or POST method)
        else if (code == 405) {
            message = APP.TRANS.ALERTS.NOT_FOUND;
            log     = "App Core -> method now allowed: " + code;
        }
        //invalid CSRF token
        else if (code == 498) {
            message = APP.TRANS.ALERTS.CSRF;
            log     = "App Core -> invalid CSRF token: " + code;
        }
        else {
            message = APP.TRANS.ALERTS.INTERNAL_ERROR;
            log     = "App Core -> unknown error: " + text;
        }

        //show log?
        if (APP.dev && log.length) { console.log(log); }

        //show the alert message
        self.showAlert(message, 'warning');
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
     * App post-action message alerts.
     * @method showAlert
     * @param  {String} payload - The payload content
     * @param  {String} type - The Message type [success, warning, info, alert]
     * @param  {Function} on_close - The onClose callback function (optional).
     * @param  {Function} on_click - The onClick callback function (optional).
     * @param  {Boolean} autohide - Autohides the alert after 8 seconds (optional).
     */
    self.showAlert = function(payload, type, on_close, on_click, autohide) {

        //set alert types
        var types = ['success', 'warning', 'info', 'alert', 'secondary'];

        if (_.isUndefined(payload))
            return;

        //array filter
        if (_.isArray(payload) && payload.length > 0)
            payload = payload[0];

        if (_.isUndefined(type) || _.indexOf(types, type) == -1)
            type = 'info';

        if (_.isUndefined(autohide))
            autohide = true;

        var wrapper_class    = APP.UI.sel_alert_box.replace("div.", "");
        var identifier_class = _.uniqueId(wrapper_class); //unique ID

        //create elements and set classes
        var div_alert    = $("<div data-alert>").addClass(wrapper_class + ' ' + identifier_class + ' alert-box ' + type);
        var div_holder   = $("<div>").addClass("holder");
        var div_content  = $("<div>").addClass("content");
        var anchor_close = $("<a>").attr("href", "javascript:void(0)").addClass("close").html("&times");
        var span_text    = $("<span>").addClass("text").html(payload);
        var span_icon    = $("<span>").addClass("icon-wrapper").html('<i class="icon-'+type+'"></i>');
         //append elements
        div_alert.append(div_holder);
        div_holder
            .append(div_content)
            .append(anchor_close);
        div_content
            .append(span_icon)
            .append(span_text);
        //css style
        div_alert.css("z-index", 99999);
        //set block property
        div_alert.alive = true;

        //SHOW alert appending to body
        $('body').append(div_alert);
        //center object after appended to body, special case for mobile
        var center_object = function() {

            //check if is mobile
            if (self.checkScreenSize("small")) {

                div_alert.addClass("small-screen");
                //center(x,y)
                div_alert.center(APP.UI.alert.position, APP.UI.alert.top_small);
                return;
            }

            //normal screens
            var top_value = APP.UI.alert.top;
            //special cases
            if (top_value == "belowHeader") {

                var header = $(APP.UI.sel_header);
                top_value  = header.length ? header.position().top + header.outerHeight() : "0";
            }

            //set CSS position x,y
            div_alert.center(APP.UI.alert.position, top_value);
        };
        //call method
        center_object();
        //set center event on window resize
        $(window).resize(function() { center_object(); });
        //remove presents alerts
        $(APP.UI.sel_alert_box).not("div."+identifier_class).fadeOut('fast');

        var hide_alert = function() {

            // bind onClose function if defined
            if (_.isFunction(on_close))
                on_close();

            if (!autohide)
                return;

            div_alert.alive = false;
            div_alert.fadeOut("fast", function() {
                $(this).remove();
            });
        };

        //set anchor close click event
        anchor_close.click(hide_alert);

        // bind onClick function if defined
        if (_.isFunction(on_click)) {

            // add click-able cursor & oneclick event
            div_alert
                .css("cursor", "pointer")
                .one( "click", function() {
                    //callback function & hide alert
                    on_click();
                    hide_alert();
                });
        }

        //autoclose after x seconds
        _.delay(function() {
            //check if object already exists
            if (div_alert.alive)
                hide_alert();
        }, 8000);

        return true;
    };

    /**
     * Prints pending server flash messages (stored in session), loaded automatically.
     * @method showFlashAlerts
     */
    self.showFlashAlerts = function() {

        //check for a flash message pending
        if (!$(APP.UI.sel_flash_messages).length)
            return;

        var messages = $(APP.UI.sel_flash_messages).children('div');

        if (!messages.length)
            return;

        messages.each(function(index) {
            //set a delay to show once at a time
            var html = $(this).html();
            var type = $(this).attr("class");
            //show message
            if (html.length)
                self.showAlert(html, type);
        });

        return true;
    };

    /**
     * Display a loading alert message.
     * @method showLoading
     * @param  {Boolean} hidden - Forces the loading element to be hidden.
     * @return {Object} A jQuery object element
     */
    self.showLoading = function(hidden) {

        if (_.isUndefined(hidden))
            hidden = false;

        //set loading object selector
        var loading_obj = $(APP.UI.sel_loading_box);

        //create loading object?
        if (!loading_obj.length) {

            //create object and append to body
            var div_loading = $("<div>").attr('id', APP.UI.sel_loading_box.replace("#",""));
            div_loading.html(APP.TRANS.ACTIONS.LOADING);

            //append to body
            $('body').append(div_loading);
            //re-asign  var
            loading_obj = $(APP.UI.sel_loading_box);

            //add special behavior for small screen
            if (self.checkScreenSize("small"))
                loading_obj.addClass("small-screen");

            var top = self.checkScreenSize("small") ? APP.UI.loading.top_small : APP.UI.loading.top;

            if (typeof APP.UI.loading.center != "undefined" && APP.UI.loading.center)
                loading_obj.center(APP.UI.loading.position, top);
        }

        //dont show for hidden flag (debug only)
        if (!hidden)
            loading_obj.show("fast");

        return loading_obj;
    };

    /**
     * Creates a new modal object
     * @method newModal
     * @param {Object} element - The jQuery element object
     * @param {Object} options - Widget options
     */
    self.newModal = function(element, options) {

        if (typeof options == "undefined")
            options = {};

        //new foundation modal
        if (core.framework == "foundation") {

            element.foundation('open');
        }
        //new bootstrap modal
        else if (core.framework == "bootstrap") {
            element.modal(options);
        }
    };

    /**
     * Close a modal object
     * @method closeModal
     * @param  {Object} element - The jQuery element object
     */
    self.closeModal = function(element) {

        if (core.framework == "foundation")
            element.foundation('close');
        else if (core.framework == "bootstrap")
            element.modal('hide');
    };

    /**
     * Validates screen size is equal to given size.
     * @TODO: check screen size with Bootstrap
     * @method checkScreenSize
     * @param  {String} size - The Screen size: small, medium, large.
     * @return {Boolean}
     */
    self.checkScreenSize = function(size) {

        //foundation
        if (self.framework == "foundation")
            return size === Foundation.MediaQuery.current;

        //bootstrap
        if (self.framework == "bootstrap") {

            if ($(window).width() < 768)
                return size === "small";
            else if ($(window).width() >= 768 && $(window).width() <= 992)
                return size === "medium";
            else if ($(window).width() > 992 && $(window).width() <= 1200)
                return size === "large";
            else
                return size === "x-large";
        }

        return false;
    };

    /**
     * Toggle Retina Images for supported platforms.
     * @method retinaImages
     * @param  {Object} context - A jQuery element context (optional)
     */
    self.retinaImages = function(context) {

        //check if client supports retina
        var isRetina = function() {

            var media_query = '(-webkit-min-device-pixel-ratio: 1.5), (min--moz-device-pixel-ratio: 1.5), (-o-min-device-pixel-ratio: 3/2), (min-resolution: 1.5dppx)';

            if (window.devicePixelRatio > 1)
                return true;

            if (window.matchMedia && window.matchMedia(media_query).matches)
                return true;

            return false;
        };

        if (!isRetina()) return;

        //get elements
        var elements = (typeof context != "undefined") ? $('img[data-retina]', context) : $('img[data-retina]');

        //for each image with attr data-retina
        elements.each(function() {

            var obj = $(this);

            var src = obj.attr("src");
            var ext = src.slice(-4);
            //set new source
            var new_src = src.replace(ext, "@2x"+ext);

            obj.removeAttr("data-retina");
            obj.attr("src", new_src);
        });
    };

    /**
     * Fallback for images that failed loading.
     * @TODO: set event click to reload image source.
     * @method fallbackImages
     */
    self.fallbackImages = function() {

        $("img").error(function() {

            if (APP.dev) { console.log("App Core -> failed loading image:", $(this).attr("src")); }

            $(this).attr("src", APP.UI.url_img_fallback);
        });
    };

    /**
     * Image preloader, returns an array with image paths [token replaced: '$']
     * @method preloadImages
     * @param  {String} image_path - The source path
     * @param  {Int} indexes - The indexes, example: image1.png, image2.png, ...
     * @return {Array} The image object array
     */
    self.preloadImages = function(image_path, indexes) {

        if (_.isUndefined(indexes) || indexes === 0)
            indexes = 1;

        var objects = [];

        //preload images
        for (var i = 0; i < indexes; i++) {
            //create new image object
            objects[i] = new Image();
            //if object has a '$' symbol replace with index
            objects[i].src = image_path.replace('$', (i+1));
        }

        return objects;
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
            self.ajaxRequest( { method : 'GET', url : 'http://250.21.0.180:8081/fake/path/' } );
        }
        else if (option == "loading") {
            $(APP.UI.sel_loading_box).show();
        }
        //get dom events associated to a given object
        else if (option == "events") {
            var obj = _.isObject(object) ? object[0] : $(object)[0];
            return $._data(obj, 'events');
        }
        else {
            assert = false;
        }

        //default return
        return "Assert ("+assert+")";
    };
};
