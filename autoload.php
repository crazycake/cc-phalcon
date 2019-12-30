<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'c3133c5');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }