<?php
/**
 * Root Layout. Phalcon main template.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

if (empty($client) || empty($client->browser)) die("400 Bad Request");
?>
<!DOCTYPE html>
<html lang="{{ client.lang }}">
	<head>
		{# charset #}
		<meta charset="utf-8">

		{# viewport #}
		{% if client.isMobile %}
			<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" vmid="viewport" data-vue-meta="1">

			{# apple metas #}
			<meta name="apple-mobile-web-app-capable" content="yes">
			<meta name="apple-mobile-web-app-status-bar-style" content="black">
			<meta name="apple-mobile-web-app-title" content="{{ config.name }}">

			{# android metas #}
			<meta name="mobile-web-app-capable" content="yes">
			<meta name="application-name" content="{{ config.name }}">

			{# PWA metas #}
			{% if metas['theme_color'] is not empty %}
				<meta name="theme-color" content="{{ metas['theme_color'] }}">
			{% endif %}

			{% if metas['manifest'] is not empty %}
				<link rel="manifest" href="{{ url('manifest.json') }}">
			{% endif %}

		{% else %}
			<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
		{% endif %}

		{# IE: force last version of render compatibility mod  #}
		{% if client.browser == "MSIE" %}
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
		{% endif %}

		{# document metas #}
		{% if metas['description'] is not empty %}
			<meta name="description" content="{{ metas['description'] }}" vmid="description" data-vue-meta="1">
		{% endif %}

		<meta name="author" content="{{ metas['author'] is not empty ? metas['author'] : 'CrazyCake Technologies' }}">

		<meta name="robots" content="{{ metas['disallow_robots'] is not empty ? 'noindex,nofollow' : 'index,follow' }}" vmid="robots" data-vue-meta="1">

		<title>{{ metas['title'] is not empty ? metas['title'] : config.name }}</title>

		{# meta csrf token #}
		<meta name="csrf-token" content="{{ client.csrfToken }}">

		{# favicons #}
		<link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon.png') }}">
		<link rel="apple-touch-icon" href="{{ static_url('images/favicons/favicon-180.png') }}">

		{# custom metas #}
		{% if metas['custom'] is not empty %}
			{{ partial("templates/metas") }}
		{% endif %}

		{# custom CSS links #}
		{% if metas['links'] is not empty %}
			{{ partial("templates/links") }}
		{% endif %}

		{# Bundle CSS #}
		<link id="style" rel="stylesheet" type="text/css" href="{{ css_url }}">

		{# Js data #}
		{% if metas['script'] is not empty %}
			<script defer src="{{ url(metas['script']) }}"></script>
		{% endif %}

		{# Bundle JS (defered) #}
		<script defer src="{{ js_url }}"></script>

		{# Google Tag Manager [HEAD] #}
		{% if config.google.gtmID is not empty %}
			<script>
				(function(w,d,s,l,i){
					w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
					var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
					j.async=true;
					j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
					f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer','{{ config.google.gtmID }}');
			</script>
		{# Google Analytics (async loading) #}
		{% elseif config.google.analyticsUA is not empty %}
			<script>
				window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;
				ga('create','{{ config.google.analyticsUA }}','auto');
				ga('send','pageview');
			</script>
			<script async defer src="https://www.google-analytics.com/analytics.js"></script>
		{% endif %}

		{# reCaptcha plugin #}
		{% if config.google.reCaptchaID is not empty %}
			<script async defer src="https://www.google.com/recaptcha/api.js?render={{ config.google.reCaptchaID }}&hl={{ client.lang }}"></script>
		{% endif %}

	</head>
	{# flushes the buffer (optimization) #}
	<?php flush(); ?>
	<body class="{{ 'ux ua-'~client.browser|lower }}">

		{# app content wrapper #}
		<div id="app">
			{{ get_content() }}
		</div>

		{# Google Tag Manager [BODY] #}
		{% if config.google.gtmID is not empty %}
			<noscript>
				<iframe src="https://www.googletagmanager.com/ns.html?id={{ config.google.gtmID }}" height="0" width="0" style="display:none;visibility:hidden;">
				</iframe>
			</noscript>
		{% endif %}

		{# JS disabled fallback #}
		<noscript>
			<p>{{ trans._('This site requires Javascript. Please enable Javascript Engine in your browser settings.') }}</p>
		</noscript>
	</body>
</html>
