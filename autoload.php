<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1a6e5b5');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }