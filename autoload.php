<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f3887b4');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }