<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '1ea6bb3');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }