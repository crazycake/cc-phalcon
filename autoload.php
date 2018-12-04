<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '7111b51');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }