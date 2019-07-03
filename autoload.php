<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ef7fb1f');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }