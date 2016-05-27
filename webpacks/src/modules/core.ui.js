/**
 * App Core UI
 * Dependencies: `jQuery.js`, `VueJs`, `q.js`, `lodash.js`.
 * Required scope vars: `{APP, UA}`.
 * @class Core.UI
 */

export default new function() {

    //self context
    var self = this;

    //++ UI

    //Set App data for selectors
    if (_.isUndefined(APP.UI)) APP.UI = {};

    //common jQuery selectors
    _.assign(APP.UI, {
        //selectors
        sel_body_wrapper   : "#wrapper",
        sel_header         : "#header",
        sel_footer         : "#footer",
        sel_loading_box    : "#app-loading",
        sel_flash_messages : "#app-flash",
        sel_alert_box      : "div.app-alert",
        //uris
        uri_img_fallback : "images/icons/icon-image-fallback.png",
        //setting vars
        pixel_ratio : _.isUndefined(window.devicePixelRatio) ? 1 : window.devicePixelRatio
    });

    //set dynamic required props as default values
    if (_.isUndefined(APP.UI.alert))
        APP.UI.alert = { position : "fixed", top : "5%", top_small : "0" };

    if (_.isUndefined(APP.UI.loading))
        APP.UI.loading = { position : "fixed", top : "25%", top_small : "25%" };

    //++ Methods ++

    /**
     * Core UI Init
     * @method ready
     */
    self.init = function() {

        //load UI module
        if (typeof core.modules.ui !== "undefined")
            core.modules.ui.init();

        //ajax setup
        self.setAjaxLoadingHandler();
        //load retina images & fallback
        self.retinaImages();
        self.fallbackImages();
        //check server flash messages
        self.showFlashAlerts();
    };

    /**
     * jQuery Ajax Handler, loaded automatically.
     * @method setAjaxLoadingHandler
     */
    self.setAjaxLoadingHandler = function() {

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
                ajax_timer = setTimeout( function() { app_loading.show("fast"); }, 1000);
                return;
            }
            //otherwise clear timer and hide loading
            clearTimeout(ajax_timer);
            app_loading.fadeOut("fast");
        };

        //ajax events
        $(document)
         .ajaxSend(function(event, xhr, options)     { handler(options, true);  })
         .ajaxComplete(function(event, xhr, options) { handler(options, false); })
         .ajaxError(function(event, xhr, options)    { handler(options, false); });
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
        var types = ["success", "warning", "info", "alert", "secondary"];

        if (_.isUndefined(payload))
            return;

        //array filter
        if (_.isArray(payload) && payload.length > 0)
            payload = payload[0];

        if (_.isUndefined(type) || _.indexOf(types, type) == -1)
            type = "info";

        if (_.isUndefined(autohide))
            autohide = true;

        var wrapper_class    = APP.UI.sel_alert_box.replace("div.", "");
        var identifier_class = _.uniqueId(wrapper_class); //unique ID

        //create elements and set classes
        var div_alert    = $("<div data-alert>").addClass(wrapper_class + " " + identifier_class + " alert-box " + type);
        var div_holder   = $("<div>").addClass("holder");
        var div_content  = $("<div>").addClass("content");
        var anchor_close = $("<a>").attr("href", "javascript:void(0)").addClass("close").html("&times");
        var span_text    = $("<span>").addClass("text").html(payload);
        var span_icon    = $("<span>").addClass("icon-wrapper").html("<i class='icon-"+type+"'></i>");
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
        $("body").append(div_alert);
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
        $(APP.UI.sel_alert_box).not("div."+identifier_class).fadeOut("fast");

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

        var messages = $(APP.UI.sel_flash_messages).children("div");

        if (!messages.length)
            return;

        messages.each(function() {
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
            var div_loading = $("<div>").attr("id", APP.UI.sel_loading_box.replace("#",""));
            div_loading.html(APP.TRANS.ACTIONS.LOADING);

            //append to body
            $("body").append(div_loading);
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

            element.foundation("open");
        }
        //new bootstrap modal
        else if (core.framework == "bootstrap") {

            element.modal(options);
        }
    };

    /**
     * Hides a crated modal
     * @param  {object} element - The jquery element
     */
    self.hideModal = function(element) {

        //new foundation modal
        if (core.framework == "foundation") {

            element.foundation("close");
        }
        //new bootstrap modal
        else if (core.framework == "bootstrap") {

            element.modal("hide");
        }
    };

    /**
     * Creates a new dialog object
     * @method newDialog
     * @param {Object} element - The jQuery element object
     * @param {Object} options - Widget options
     */
    self.newDialog = function(options) {

        $.ccdialog(options);
    };

    /**
     * Creates a new layer object
     * @method newLayer
     * @param {Object} element - The jQuery element object
     * @param {Object} options - Widget options
     */
    self.newLayer = function(element, options) {

        element.cclayer(options);
    };

    /**
     * Closes cclayer dialog
     * @method isOverlayVisible
     */
    self.isOverlayVisible = function() {

        return $.cclayer.isVisible();
    };

    /**
     * Hides cclayer
     * @method hideLayer
     */
    self.hideLayer = function() {

        $.cclayer.close();
    };

    /**
     * Hides cclayer dialog
     * @method hideDialog
     */
    self.hideDialog = function() {

        self.hideLayer();
    };

    /**
     * Closes cclayer dialog
     * @method closeDialog
     */
    self.isOverlayVisible = function() {

        return $.cclayer.isVisible();
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
        if (core.framework == "foundation")
            return size === Foundation.MediaQuery.current;

        //bootstrap
        if (core.framework == "bootstrap") {

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

            var media_query = "(-webkit-min-device-pixel-ratio: 1.5), (min--moz-device-pixel-ratio: 1.5), (-o-min-device-pixel-ratio: 3/2), (min-resolution: 1.5dppx)";

            if (window.devicePixelRatio > 1)
                return true;

            if (window.matchMedia && window.matchMedia(media_query).matches)
                return true;

            return false;
        };

        if (!isRetina()) return;

        //get elements
        var elements = (typeof context != "undefined") ? $("img[data-retina]", context) : $("img[data-retina]");

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

            if (APP.dev) { console.log("Core UI -> failed loading image:", $(this).attr("src")); }

            $(this).attr("src", core.staticUrl(APP.UI.uri_img_fallback));
        });
    };

    /**
     * Image preloader, returns an array with image paths [token replaced: "$"]
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
            objects[i].src = image_path.replace("$", (i+1));
        }

        return objects;
    };
};
