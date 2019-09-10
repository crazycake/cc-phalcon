<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '995b47c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }