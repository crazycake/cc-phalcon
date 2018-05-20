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
			{# PWA metas #}
			{% if metas['theme_color'] is not empty %}
				<meta name="theme-color" content="{{ metas['theme_color'] }}" />
			{% endif %}
			{% if metas['manifest'] is not empty %}
				<link rel="manifest" href="{{ url('manifest.json') }}" />
			{% endif %}

		{% else %}
			<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
		{% endif %}

		{# InternetExplorer: force last version of render compatibility mod  #}
		{% if client.browser == "MSIE" %}
			<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		{% endif %}

		{# description meta #}
		<meta name="description" content="{{ metas['description'] is not empty ? metas['description'] : config.name }}" />

		{# author meta #}
		<meta name="author" content="{{ metas['author'] is not empty ? metas['author'] : 'CrazyCake Technologies' }}" />

		{# robots meta #}
		<meta name="robots" content="{{ metas['disallow_robots'] is not empty ? 'noindex,nofollow' : 'index,follow' }}" />

		{# page title #}
		<title>{{ metas['title'] is not empty ? metas['title'] : config.name }}</title>

		{# favicons #}
		<link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon.png') }}" />
		<link rel="apple-touch-icon" href="{{ static_url('images/favicons/apple-touch-icon.png') }}" />

		{# Windows 8 #}
		{% if client.platform == "Windows" %}
			<meta name="msapplication-TileColor" content="{{ metas['ms_tile_color'] is not empty ? metas['ms_tile_color'] : '#EDEDED' }}" />
			<meta name="msapplication-TileImage" content="{{ static_url('images/favicons/mstile.png') }}" />
		{% endif %}

		{# custom metas #}
		{% if metas['custom'] is not empty %}
			{{ partial("templates/metas") }}
		{% endif %}

		{# APP EXTERNAL CSS LINKS #}
		{% if css_links is defined and css_links is iterable %}
			{% for link in css_links %}
				<link href="{{ link }}" rel="stylesheet" />
			{% endfor %}
		{% endif %}

		{# APP CSS #}
		<link id="style" rel="stylesheet" type="text/css" href="{{ css_url }}" />

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
		{% if js_loader is not empty %}
			<script>{{ js_loader }}</script>
		{% endif %}

		{# JS Defer URLs #}
		{% if js_defer is not empty %}
			{% for defer in js_defer %}
				<script defer src="{{ defer }}" type="text/javascript"></script>
			{% endfor %}
		{% endif %}

		{# Google Analytics (async loading) #}
		{% if config.google.analyticsUA is not empty %}
			<script>
				window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;
				ga('create','{{ config.google.analyticsUA }}','auto');
				ga('send','pageview');
			</script>
			<script src="//www.google-analytics.com/analytics.js" async defer></script>
		{% endif %}

		{# Google Tag Manager [BODY] #}
		{% if config.google.gtmUA is not empty %}
			<noscript>
				<iframe src="https://www.googletagmanager.com/ns.html?id={{ config.google.gtmUA }}" height="0" width="0" style="display:none;visibility:hidden;">
				</iframe>
			</noscript>
		{% endif %}

		{# reCaptcha plugin #}
		{% if js_recaptcha is not empty %}
			<script src="//www.google.com/recaptcha/api.js?onload={{ js_recaptcha }}&amp;render=explicit&amp;hl={{ client.lang }}" async defer></script>
		{% endif %}

		{# javascript disabled fallback #}
		<noscript>
			<p>{{ trans._('Este sitio funciona con Javascript. Por favor activa el motor de Javascript en tu navegador.') }}</p>
		</noscript>

		{# debug: output render time #}
		<script>
			console.log('App {{ config.version }} - Engine <?php echo \Phalcon\Version::get()." [".CORE_VERSION."], rendered in ".number_format((float)(microtime(true) - APP_ST), 3, ".", "")." secs."; ?>');
		</script>

	</body>
</html>
