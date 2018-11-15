<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '34f6b5a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }