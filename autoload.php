<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '33369e6');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }