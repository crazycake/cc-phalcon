<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a0da96a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }