<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '83c55c7');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }