<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'bf6e104');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }