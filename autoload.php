<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e6f8187');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }