<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'feab694');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }