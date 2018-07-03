<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ba1f005');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }