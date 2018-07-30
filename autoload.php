<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'bd3ab94');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }