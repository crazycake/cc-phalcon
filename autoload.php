<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1bfe0f5');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }