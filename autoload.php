<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '97815ea');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }