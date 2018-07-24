<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'dd2a6bd');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }