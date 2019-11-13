<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e9f04e3');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }