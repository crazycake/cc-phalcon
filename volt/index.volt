<?php
/**
 * Root Layout. Phalcon main template.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */
?>
<!DOCTYPE html>
<html lang="{{ client.lang }}">
	<head>
		{# charset #}
		<meta charset="utf-8" />

		{# viewport #}
		{% if client.isMobile %}
			<meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no,minimal-ui" />
			{# apple metas #}
			<meta name="apple-mobile-web-app-capable" content="yes" />
			<meta name="apple-mobile-web-app-status-bar-style" content="black" />
			<meta name="apple-mobile-web-app-title" content="{{ config.name }}" />
			{# android metas #}
			<meta name="mobile-web-app-capable" content="yes" />
			<meta name="application-name" content="{{ config.name }}" />

		{% else %}
			<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
		{% endif %}

		{# InternetExplorer: force last version of render compatibility mod  #}
		{% if client.browser == "MSIE" %}
			<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		{% endif %}

		{# description meta #}
		<meta name="description" content="{{ html_description is defined ? html_description : config.name  }}" />

		{# author meta #}
		<meta name="author" content="{{ html_author is defined ? html_author : 'CrazyCake Technologies' }}" />

		{# robots meta #}
		<meta name="robots" content="{{ html_disallow_robots is defined ? 'noindex,nofollow' : 'index,follow' }}" />

		{# page title #}
		<title>{{ html_title is defined ? html_title : config.name }}</title>

		{# favicons #}
		<link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon.png') }}" />
		<link rel="apple-touch-icon" href="{{ static_url('images/favicons/apple-touch-icon.png') }}" />

		{# Windows 8 #}
		{% if client.platform == "Windows" %}
			<meta name="msapplication-TileColor" content="#efefef" />
			<meta name="msapplication-TileImage" content="{{ static_url('images/favicons/mstile.png') }}" />
		{% endif %}

		{# custom metas #}
		{% if html_metas is defined %}
			{{ partial("templates/metas") }}
		{% endif %}

		{# APP EXTERNAL CSS LINKS #}
		{% if css_links is defined and css_links is iterable %}
			{% for link in css_links %}
				<link href="{{ link }}" rel="stylesheet" />
			{% endfor %}
		{% endif %}

		{# APP CSS #}
		<link rel="stylesheet" type="text/css" href="{{ css_url }}" />

		{# APP Global scope vars #}
		{% if js_app is defined %}
			<script>
				APP = {{ js_app }};
			</script>
		{% else %}
			<script> console.log('Core -> (warning) javascript APP or UA scope vars are not defined.'); </script>
		{% endif %}


		{# Google Tag Manager [HEAD] #}
		{% if config.google.gtmUA is not empty %}
			<script>
				(function(w,d,s,l,i){
					w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
					var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
					j.async=true;
					j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
					f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer','{{ config.google.gtmUA }}');
			</script>
		{% endif %}

	</head>
	{# Flush the buffer (optimization) #}
	<?php flush(); ?>
	<body class="{{ 'ua-'~client.browser|lower~' '~client.platform|lower }}{{ html_body_class is defined ? ' '~html_body_class : '' }}">

		{# app content wrapper #}
		{% if html_app_wrapper is defined and !html_app_wrapper %}
			{# layout content #}
			{{ get_content() }}
		{% else %}
			<div id="app">
				{# layout content #}
				{{ get_content() }}
			</div>
		{% endif %}

		{# flash messages #}
		<div id="app-flash" style="display:none;">
			{{ flash.output() }}
		</div>

		{# APP JS #}
		<script src="{{ js_url }}" type="text/javascript"></script>

		{# APP JS Module Loader #}
		{% if js_loader is defined %}
			<script>{{ js_loader }}</script>
		{% endif %}

		{# Google Analytics (async loading) #}
		{% if config.google.analyticsUA is not empty %}
			<script>
				window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;
				ga('create','{{ config.google.analyticsUA }}','auto');
				ga('send','pageview')
			</script>
			<script src="//www.google-analytics.com/analytics.js" async defer></script>
		{% endif %}

		{# Google Tag Manager [BODY] #}
		{% if config.google.gtmUA is not empty %}
			<noscript>
				<iframe src="https://www.googletagmanager.com/ns.html?id={{ gtmUA }}" height="0" width="0" style="display:none;visibility:hidden;">
				</iframe>
			</noscript>
		{% endif %}

		{# recaptcha plugin #}
		{% if js_recaptcha is not empty %}
			<script src="//www.google.com/recaptcha/api.js?onload={{ js_recaptcha }}&amp;render=explicit&amp;hl={{ client.lang }}" async defer></script>
		{% endif %}

		{# javascript disabled fallback #}
		<noscript>
			{{ trans._('Este sitio funciona con Javascript. Por favor activa el motor de Javascript en tu navegador.') }}
		</noscript>

		{# debug: output render time #}
		{% if constant("APP_ENV") != "production" %}
			<script>
				console.log('Core -> PhalconPHP <?php echo \Phalcon\Version::get(); ?>, page rendered in <?php echo number_format((float)(microtime(true) - APP_ST), 3, ".", ""); ?> seconds.');
			</script>
		{% endif %}

	</body>
</html>
