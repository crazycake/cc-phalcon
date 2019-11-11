<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1e84f6c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }