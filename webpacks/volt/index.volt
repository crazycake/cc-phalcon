<?php
/**
 * Root Layout. Phalcon loads this view first by default
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */
?>
<!DOCTYPE html>
<html{{ html_doc_class is defined ? ' class="'~html_doc_class~'"' : '' }}>
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
        {% if client.isIE %}
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        {% endif %}

        {# define vars #}
        {% set tag_title            = html_title is defined ? app.name~" - "~html_title : app.name %}
        {% set tag_meta_description = html_description is defined ? app.name~": "~html_description : app.name %}
        {% set tag_meta_robots      = html_disallow_robots is defined ? "noindex,nofollow" : "index,follow" %}

        {# descriptive metas #}
        <meta name="author" content="{{ app.name }} Team" />
        <meta name="description" content="{{ tag_meta_description }}" />
        <meta name="robots" content="{{ tag_meta_robots }}" />
        {# page title #}
        <title>{{ tag_title }}</title>

        {# favicons #}
        <link rel="shortcut icon" href="{{ static_url('images/favicons/favicon.ico') }}" />
        <link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon-192x192.png') }}" sizes="192x192" />
        <link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon-160x160.png') }}" sizes="160x160" />
        <link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon-96x96.png') }}" sizes="96x96" />
        <link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon-16x16.png') }}" sizes="16x16" />
        <link rel="icon" type="image/png" href="{{ static_url('images/favicons/favicon-32x32.png') }}" sizes="32x32" />
        {# Apple touch favicons #}
        <link rel="apple-touch-icon" sizes="57x57"   href="{{ static_url('images/favicons/apple-touch-icon-57x57.png') }}" />
        <link rel="apple-touch-icon" sizes="114x114" href="{{ static_url('images/favicons/apple-touch-icon-114x114.png') }}" />
        <link rel="apple-touch-icon" sizes="72x72"   href="{{ static_url('images/favicons/apple-touch-icon-72x72.png') }}" />
        <link rel="apple-touch-icon" sizes="144x144" href="{{ static_url('images/favicons/apple-touch-icon-144x144.png') }}" />
        <link rel="apple-touch-icon" sizes="60x60"   href="{{ static_url('images/favicons/apple-touch-icon-60x60.png') }}" />
        <link rel="apple-touch-icon" sizes="120x120" href="{{ static_url('images/favicons/apple-touch-icon-120x120.png') }}" />
        <link rel="apple-touch-icon" sizes="76x76"   href="{{ static_url('images/favicons/apple-touch-icon-76x76.png') }}" />
        <link rel="apple-touch-icon" sizes="152x152" href="{{ static_url('images/favicons/apple-touch-icon-152x152.png') }}" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{ static_url('images/favicons/apple-touch-icon-180x180.png') }}" />

        {# Windows 8 #}
        {% if client is defined and client.platform == 'Windows' %}
            <meta name="application-name" content="{{ app.name }}" />
            <meta name="msapplication-TileColor" content="#efefef">
            <meta name="msapplication-TileImage" content="{{ static_url('images/favicons/mstile-144x144.png') }}" />
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

        {# APP Scope vars #}
        {% if js_app is defined %}
            <script>
                APP = {{ js_app }};
                UA  = {{ js_client }};
            </script>
        {% else %}
            <script>
                console.log('App Core -> (warning) javascript APP or UA scope vars are not defined.');
            </script>
        {% endif %}

    </head>
    {# Flush the buffer (optimization) #}
    <?php  flush(); ?>
    <body{{ html_body_class is defined ? ' class="'~html_body_class~'"' : '' }}>

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
        <div id="app-flash" class="hide">
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

        {# GoogleAnalytics (Modern loader) #}
        {% if app.google is defined %}
            <script async src='//www.google-analytics.com/analytics.js'></script>
            <script>
                window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
                ga('create', '{{ app.google.analyticsUA }}', 'auto');
                ga('send', 'pageview');
            </script>
        {% endif %}

        {# reCaptcha plugin #}
        {% if js_recaptcha is defined and js_recaptcha %}
            <script>function recaptchaOnLoad(){ core.modules.forms.recaptchaOnLoad(); }</script>
            <script src="{{ client.protocol }}www.google.com/recaptcha/api.js?onload=recaptchaOnLoad&amp;render=explicit&amp;hl={{ client.lang }}" async defer></script>
        {% endif %}

        {# javascript disabled fallback #}
        <noscript class="app-no-js app-fixed text-center">
            {{ trans._('Este sitio funciona con Javascript. Porfavor activa el motor de Javascript en tu navegador.') }}
        </noscript>

       {# debug: output render time #}
        {% if constant("APP_ENVIRONMENT") != "production" %}
            <script>
                console.log('App Core -> PhalconPHP <?php echo \Phalcon\Version::get(); ?>. Page rendered in <?php echo number_format((float)(microtime(true) - APP_START), 3, ".", ""); ?> seconds.');
            </script>
        {% endif %}

    </body>
</html>
