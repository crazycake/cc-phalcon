<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '7a5a42d');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }