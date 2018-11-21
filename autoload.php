<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'eab0075');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }