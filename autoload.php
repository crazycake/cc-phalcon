<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '97fc615');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }