/**
 * Modality Dialog jQuery plugin v 1.0
 * Requires jQuery 1.7.x or superior, and Modality plugin
 * Supports mayor browsers including IE8
 * @author Nicolas Pulido M.
 * Usage: 
 * $(element).modalityDialog({
	title       : (stirng) the dialog title
	content     : (string) The dialog content, can be an HTML input
	width       : (int) width size in pixels or a string with an int + measure unit
	fixed       : (boolean) set if dialog has fixed or absolute position
	buttons     : (array) array of buttons with the following object struct:
					{ label : (string), click : (function) }
	onClose     : (function) event onClose dialog
	escape      : (boolean) Allows user to escape the modal with ESC key or a Click outside the element. Defaults is true.
	zindex      : (int) css z-index value, default value is 100.
	smallScreen : (int) small screen width Threshold, defaults value is 640.
});
 */

(function($)
{
	if(typeof $.modality !== "function")
		throw new Error('Modality Dialogs -> jQuery modality plugin is required');

	/** ------------------------------------------------------------------------------------------------
		Modality public methods
	------------------------------------------------------------------------------------------------ **/
	$.modalityDialog = function(options) {

		if(typeof options == "undefined")
			options = {};

		//returns the core object
		return $.modalityDialog.core.init(options);
	};

	/**
	 * Closes Modality
	 */
	$.modalityDialog.close = function() { 
		$.modality.close();
	};

	/** ------------------------------------------------------------------------------------------------
		Modality element
	------------------------------------------------------------------------------------------------ **/

	//DEFAULT VALUES
	$.modalityDialog.defaults = {
		title        : "",
		content      : "",
		width        : "60%",
		fixed        : false,
		overlay      : true,
		overlayAlpha : 70,
		overlayColor : "#000",
		buttons      : [],
		onClose      : null,
		escape       : true,
		zindex       : 100,
		smallScreen  : 640
	};

	//CORE
	$.modalityDialog.core = {

		init: function(options) {
			//extend options
			this.opts = $.extend({}, $.modalityDialog.defaults, options);
			//drop a previously created dialog
			this.drop();
			this.create(this.opts);
			this.show(this.opts);

			return this;
		},
		create: function(options) {

			var self = this;
			//wrappers
			var div_wrapper = $("<div>").addClass("modality-dialog").css("display", "none");
			var div_box     = $("<div>").addClass("box");

			//contents
			var div_title  = $("<div>").addClass("header").html(options.title);
			var div_body   = $("<div>").addClass("body").html(options.content);
			var div_footer = $("<div>").addClass("footer");
						
			//appends
			div_wrapper.appendTo("body");
			div_box.appendTo(div_wrapper);
			div_title.appendTo(div_box);
			div_body.appendTo(div_box);

			//width
			div_wrapper.width(options.width);

			//fix width for small screens
			if($(window).width() <= options.smallScreen && parseInt(options.width) < 80)
				div_wrapper.width("90%");

			//check if dialog must have buttons
			if(typeof options.buttons !== 'object')
				return;

			//append buttons?
			var show_footer = false;
			//loop through buttons
			var index = 0;
			for(var key in options.buttons) {

				var btn = options.buttons[key];

				if(typeof btn !== 'object' || typeof btn.label == 'undefined')
					continue;

				var button_element = $("<button>")
										.attr("name", 'button-'+index)
										.addClass('button-'+index)
										.html(btn.label);

				if(typeof btn.click === 'function')
					button_element.click(btn.click);
				else
					button_element.click(self.close);

				button_element.appendTo(div_footer);

				show_footer = true;
				index++;
			}

			//footer append
			if(show_footer)
				div_footer.appendTo(div_box);
		},
		drop: function() {
			//removes an existing dialog
			if($("div.modality-dialog").length)
				$("div.modality-dialog").remove();
		},
		show: function(options) {

			var fn_onclose = null;
			//check onClose function
			if(typeof options.onClose === 'function')
				fn_onclose = options.onClose;

			//show modal
			$("div.modality-dialog").modality({
				fixed           : options.fixed,
				overlay         : options.overlay,
				overlayAlpha    : options.overlayAlpha,
				overlayColor    : options.overlayColor,
				escape          : options.escape,
				zindex          : options.zindex,
				onClose         : fn_onclose
			});
		},
		close: function() { 
			//simpleModal - close
			$.modality.close();
		}
	};
	/** ------------------------------------------------------------------------------------------------
		jQuery setup
	------------------------------------------------------------------------------------------------ **/
	//creating an event "destroyed"
	jQuery.event.special.destroyed = {
		remove: function(o) {
		  if(o.handler)
			o.handler();
		}
	};

})(jQuery);
