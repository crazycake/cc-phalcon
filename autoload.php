<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'e08d744');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }