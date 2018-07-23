<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f7e24e4');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }