<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e9e9111');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }