<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f05b5cb');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }