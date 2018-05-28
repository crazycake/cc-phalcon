<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'fa7c939');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }