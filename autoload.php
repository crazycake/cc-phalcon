<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'f7647ee');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }