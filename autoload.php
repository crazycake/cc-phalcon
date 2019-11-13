<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '0f8a58a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }