<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '4b1db61');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }