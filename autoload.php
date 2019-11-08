<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '83d022f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }