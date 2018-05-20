<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '805a1c0');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }