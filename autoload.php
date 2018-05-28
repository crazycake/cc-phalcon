<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '76e3f38');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }