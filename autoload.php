<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '9855a33');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }