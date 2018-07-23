<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a4f0f7a');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }