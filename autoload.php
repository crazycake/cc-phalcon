<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '8ab4a02');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }