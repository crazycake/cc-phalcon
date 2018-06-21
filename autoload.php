<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a8a49d7');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }