<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1db01a1');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }