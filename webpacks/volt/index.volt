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
            <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no" />
            {# apple metas #}
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="black">
            {# android metas #}
            <meta name="mobile-web-app-capable" content="yes">
        {% else %}
            <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
        {% endif %}

        {# InternetExplorer: force last version of render compatibility mod  #}
        {% if client.browser == "MSIE" %}
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        {% endif %}

        {# define vars #}
        {% set tag_title            = html_title is defined ? html_title : config.name %}
        {% set tag_meta_description = html_description is defined ? html_description : config.name %}
        {% set tag_meta_author      = html_author is defined ? html_author : config.name~' Team' %}
        {% set tag_meta_robots      = html_disallow_robots is defined ? "noindex,nofollow" : "index,follow" %}

        {# descriptive metas #}
        <meta name="description" content="{{ tag_meta_description }}" />
        <meta name="author" content="{{ tag_meta_author }}" />
        <meta name="robots" content="{{ tag_meta_robots }}" />
        {# page title #}
        <title>{{ tag_title }}</title>

        {# favicons #}
        <link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon.png') }}" />
        <link rel="apple-touch-icon" href="{{ static_url('images/favicons/apple-touch-icon.png') }}" />

        {# Windows 8 #}
        {% if client.platform == "Windows" %}
            <meta name="application-name" content="{{ config.name }}" />
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
                UA  = {{ js_client }};
            </script>
        {% else %}
            <script>
                console.log('Core -> (warning) javascript APP or UA scope vars are not defined.');
            </script>
        {% endif %}

    </head>
    {# Flush the buffer (optimization) #}
    <?php  flush(); ?>
    <body class="{{ html_body_class is defined ? html_body_class : 'ua-'~client.browser|lower }}">

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
        <script type="text/javascript" src="{{ js_url }}"></script>

        {# APP JS Module Loader #}
        {% if js_loader is defined %}
            <script>{{ js_loader }}</script>
        {% endif %}

        {# App JS Core Event #}
        <script>core.ready();</script>

        {# GoogleAnalytics (Frontend only, async loading) #}
        {% if config.google is defined and constant("MODULE_NAME") == "frontend" %}
            <script>
                window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;
                ga('create','{{ config.google.analyticsUA }}','auto');
                ga('send','pageview')
            </script>
            <script src="//www.google-analytics.com/analytics.js" async defer></script>
        {% endif %}

        {# reCaptcha plugin #}
        {% if js_recaptcha is defined and js_recaptcha %}
            <script>function recaptchaOnLoad(){ core.modules.forms.recaptchaOnLoad(); }</script>
            <script src="{{ client.protocol }}www.google.com/recaptcha/api.js?onload=recaptchaOnLoad&amp;render=explicit&amp;hl={{ client.lang }}" async defer></script>
        {% endif %}

        {# javascript disabled fallback #}
        <noscript>
            {{ trans._('Este sitio funciona con Javascript. Porfavor activa el motor de Javascript en tu navegador.') }}
        </noscript>

        {# debug: output render time #}
        {% if constant("APP_ENV") != "production" %}
            <script>
                console.log('Core -> PhalconPHP <?php echo \Phalcon\Version::get(); ?>. Page rendered in <?php echo number_format((float)(microtime(true) - APP_ST), 3, ".", ""); ?> seconds.');
            </script>
        {% endif %}

    </body>
</html>
