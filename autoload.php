<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e6aaee8');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }