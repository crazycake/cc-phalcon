<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '3db80a9');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }