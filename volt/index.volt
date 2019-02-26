<?php
/**
 * Root Layout. Phalcon main template.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */
?>
<!DOCTYPE html>
<html lang="{{ client.lang }}">
	<head>
		{# charset #}
		<meta charset="utf-8" />

		{# viewport #}
		{% if client.isMobile %}
			<meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no" />

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
			<meta name="viewport" content="width=device-width,initial-scale=1.0,shrink-to-fit=no" />
		{% endif %}

		{# IE: force last version of render compatibility mod  #}
		{% if client.browser == "MSIE" %}
			<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		{% endif %}

		{# document metas #}
		<meta name="description" content="{{ metas['description'] is not empty ? metas['description'] : config.name }}" vmid="description" data-vue-meta="true" />

		<meta name="author" content="{{ metas['author'] is not empty ? metas['author'] : 'CrazyCake Technologies' }}" />

		<meta name="robots" content="{{ metas['disallow_robots'] is not empty ? 'noindex,nofollow' : 'index,follow' }}" />

		<title>{{ metas['title'] is not empty ? metas['title'] : config.name }}</title>

		{# favicons #}
		<link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon.png') }}" />
		<link rel="apple-touch-icon" href="{{ static_url('images/favicons/apple-touch-icon.png') }}" />

		{# windows 8 #}
		{% if client.platform == "Windows" %}
			<meta name="msapplication-TileColor" content="{{ metas['ms_tile_color'] is not empty ? metas['ms_tile_color'] : '#EDEDED' }}" />
			<meta name="msapplication-TileImage" content="{{ static_url('images/favicons/mstile.png') }}" />
		{% endif %}

		{# custom metas #}
		{% if metas['custom'] is not empty %}
			{{ partial("templates/metas") }}
		{% endif %}

		{# custom CSS links #}
		{% if metas['links'] is not empty %}
			{{ partial("templates/links") }}
		{% endif %}

		{# Bundle CSS #}
		<link id="style" rel="stylesheet" type="text/css" href="{{ css_url }}" />

		{# Js data #}
		{% if metas['script'] is not empty %}
			<script defer src="{{ url(metas['script']) }}"></script>
		{% endif %}

		{# Bundle JS (defered) #}
		<script defer src="{{ js_url }}"></script>

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
	{# flushes the buffer (optimization) #}
	<?php flush(); ?>
	<body class="{{ 'ux ua-'~client.browser|lower~' '~client.platform|lower }}">

		{# app content wrapper #}
		<div id="app">
			{{ get_content() }}
		</div>

		{# Google Analytics (async loading) #}
		{% if config.google.analyticsUA is not empty %}
			<script defer>
				window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;
				ga('create','{{ config.google.analyticsUA }}','auto');
				ga('send','pageview');
			</script>
			<script async defer src="https://www.google-analytics.com/analytics.js"></script>
		{% endif %}

		{# Google Tag Manager [BODY] #}
		{% if config.google.gtmUA is not empty %}
			<noscript>
				<iframe src="https://www.googletagmanager.com/ns.html?id={{ config.google.gtmUA }}" height="0" width="0" style="display:none;visibility:hidden;">
				</iframe>
			</noscript>
		{% endif %}

		{# reCaptcha plugin #}
		{% if config.google.reCaptchaID is not empty %}
			<script async defer src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoaded&render={{ config.google.reCaptchaID }}&hl={{ client.lang }}"></script>
		{% endif %}

		{# JS disabled fallback #}
		<noscript>
			<p>{{ trans._('Este sitio funciona con Javascript. Por favor activa el motor de Javascript en tu navegador.') }}</p>
		</noscript>
	</body>
</html>
