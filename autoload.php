<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '96f9907');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }