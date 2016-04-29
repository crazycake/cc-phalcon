/**
 * Forms View Model - Handle Form Validation & Actions
 * Dependencies: ```formValidation jQuery plugin```, ```google reCaptcha JS SDK```
 * @class Forms
 */
module.exports = function() {

    //++ Module
    var self  = this;
    self.name = "forms";

    //++ Components
    Vue.component('birthday-selector', {
        template: '#vue-template-birthday-selector',
        //data must be a function
        data : function () {
            return {
                day   : "",
                month : "",
                year  : ""
            };
        },
        methods : {
            flashBirthdayValues : function () {
                this.day   = "";
                this.month = "";
                this.year  = "";
            }
        },
        computed : {
            birthdayValue : function () {

                return this.year + "-" + this.month + "-" + this.day;
            }
        }
    });

    //++ UI Selectors
	_.assign(APP.UI, {
        sel_recaptcha : "#app-recaptcha"
	});

    //++ Methods

    /**
     * Autoloads validation for ```form[data-validate]``` selector
     * @method loadForms
     * @param {Object} context - The jQuery object element context (optional)
     */
    self.loadForms = function(context) {

        var forms = (typeof context === "undefined") ? $("form[data-validate]") : $("form[data-validate]", context);

        if(!forms.length) return;

        //loop through each form
        forms.each(function() {
            //object reference
            var form = $(this);
            //get required inputs
            var elements = form.find("[data-fv-required]");
            var options  = {
                fields : {}
            };

            //loop through each element form
            elements.each(function() {
                //object reference
                var name = $(this).attr("name");

                //skip undefined names
                if(typeof name === "undefined") return;

                //set validators
                options.fields[name] = {
                    validators : { notEmpty : {} }
                };

                //append required props
                self.assignFieldValidatorPattern($(this), options.fields[name].validators);
            });

            //new Form Validation instance
            self.newFormValidation(form, options);
        });
    };

    /**
     * Creates a New Form Validation.
     * Ref: [http://formvalidation.io/api/]
     * TODO: set bootstrap icon classes (glyphs)
     * @method newFormValidation
     * @param  {Object} form - A form jQuery object or native element
     * @param  {Object} options - Extended Options
     */
    self.newFormValidation = function(form, options) {

        //default selector
        if(typeof form == "undefined" || _.isNull(form))
            throw new Error("App Forms -> newFormValidation: A Form object is required!");

        if(form instanceof jQuery === false)
            form = $(form);

         //set settings
         var opts = {
            framework : core.framework,
            err       : { clazz : 'form-error' },
            /*icon : {
                valid      : 'fa fa-check',
                invalid    : 'fa fa-times',
                validating : 'fa fa-refresh'
            }*/
            //on success
            onSuccess: function(e) {
                //prevent default submit
                e.preventDefault();
           }
        };

        //extend options
        _.assign(opts, options);

        //init plugin
        if(APP.dev) { console.log("App Forms -> loading form with options:", opts); }

        form.formValidation(opts);
    };

    /**
     * Check if a Form is valid [formValidator]
     * @method isFormValid
     * @param  {Object} form - A form jQuery object or native element
     * @return {Boolean}
     */
    self.isFormValid = function(form) {

        if(form instanceof jQuery === false)
            form = $(form);

        if(typeof form.data === "undefined" || typeof form.data('formValidation') === "undefined")
            throw new Error("App Core -> form object has no formValidation instance.");

        //check for input hidden fields that are required
        var inputs_hidden = form.find('input[type="hidden"][data-fv-excluded="false"]');

        if(inputs_hidden.length) {

            if(APP.dev) { console.log("App Core -> Revalidating hidden inputs..."); }
            //loop
            inputs_hidden.each(function() {
                //revalidate field
                form.data('formValidation').revalidateField($(this));
            });
        }

        //force validation first (API call)
        $(form).data('formValidation').validate();
        //check result
        var is_valid = form.data('formValidation').isValid();

        if(!is_valid && APP.dev) {

            console.log("App Core -> Some form element(s) are not valid:");

            form.data('formValidation').getInvalidFields().each(function() {
                console.log($(this).attr("name"), $(this));
            });
        }

        return is_valid;
    };

    /**
     * Revalidates a field in form.
     * @method revalidateFormField
     * @param  {Object} form - A form jQuery object or native element
     * @param  {String} field - The field name
     */
    self.revalidateFormField = function(form, field) {

        if(form instanceof jQuery === false)
            form = $(form);

        var fv = form.data('formValidation');
        fv.updateStatus(field, 'NOT_VALIDATED');
    };

    /**
     * Enable or Disable form submit buttons
     * @param  {Object} form - A form jQuery object or native element
     * @param  {Boolean} flag - The enable/disable flag
     */
    self.enableFormSubmitButtons = function(form, flag) {

        if(form instanceof jQuery === false)
            form = $(form);

        if(typeof flag === "undefined")
            flag = true;

        var fv = form.data('formValidation');
        fv.disableSubmitButtons(!flag);
    };

    /**
     * Resets a form validation
     * @method resetForm
     * @param  {Object} form - A form jQuery object or native element
     */
    self.resetForm = function(form) {

        if(form instanceof jQuery === false)
            form = $(form);

        //clean form
        form.data('formValidation').resetForm();
        form.find('input, textarea').val("");
        form.find("select").prop('selectedIndex', 0);
    };

    /**
     * Add a dynamic field to form
     * @method addField
     * @param  {String} field_name - The field name
     * @param  {Object} context - A jQuery object or native element
     * @param  {Object} validators_obj - Validators Object (formValidation)
     */
    self.addField = function(field_name, context, validators_obj) {

        if(context instanceof jQuery === false)
            context = $(context);

        //field target
        var field;
        //set object
        if(field_name instanceof jQuery === true)
            field = field_name;
        else
            field = $("[name='"+field_name+"']", context);

        //default validator
        var v = {validators : { notEmpty : {} }};

        if(typeof validators_obj === "object")
            v = {validators : validators_obj};

        //append required props
        self.assignFieldValidatorPattern(field, v.validators);

        var form = field.closest("form");

        if(typeof form === "undefined")
            return console.warn("App Forms [addField] -> Can't find closest element form for field:", field);
        else if(typeof form.data('formValidation') === "undefined")
            return;

        var fv = form.data('formValidation');
        //formValidation API
        fv.addField(field_name, v);
    };

    /**
     * Assign validator field pattern in data attribute
     * @param  {object} field - The jQuery field object
     * @param  {object} validators - The validators object
     */
    self.assignFieldValidatorPattern = function(field, validators) {

        try {

            var pattern = field.attr("data-fv-required");

            if(!pattern.length)
                return;

            var obj = eval('({' + pattern + '})');
            //append required props
            _.assign(validators, obj);
        }
        catch(e) {}
    };

    /**
    * App Google reCaptcha onLoad Callback.
    * Function name is defined in script loader.
    * @method recaptchaOnLoad
    * @property {Object} grecaptcha is global and is defined by reCaptcha SDK.
    */
    self.recaptchaOnLoad = function() {

        if(APP.dev) { console.log("App Core -> reCaptcha loaded! Main Selector: " + APP.UI.sel_recaptcha); }

        //calback function when user entered valid data
        var callback_fn = function(value) {

            if(APP.dev) { console.log("App Core -> reCaptcha validation OK!"); }

            //set valid option on sibling input hidden
            $(APP.UI.sel_recaptcha).siblings('input').eq(0).val("1");
            //reset form field
            self.revalidateFormField($(APP.UI.sel_recaptcha).parents("form").eq(0), 'reCaptchaValue');
        };
        //render reCaptcha through API call
        grecaptcha.render(APP.UI.sel_recaptcha.replace("#", ""), {
            'sitekey'  : APP.googleReCaptchaID,
            'callback' : callback_fn
        });

        //hide after x secs
        setTimeout(function() {
            //clean any previous error
            $(APP.UI.sel_recaptcha).siblings('small').eq(0).empty();
        }, 1500);
    };

    /**
     * Reloads a reCaptcha element.
     * @method recaptchaReload
     */
    self.recaptchaReload = function() {

        if(APP.dev) { console.log("App Core -> reloading reCaptcha..."); }

        //reset reCaptcha
        if(typeof grecaptcha != "undefined")
            grecaptcha.reset();

        //clean hidden input for validation
        $(APP.UI.sel_recaptcha).siblings('input:hidden').eq(0).val("");
    };
};
