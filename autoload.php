<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'cbf749b');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }