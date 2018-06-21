<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f4a2f0a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }