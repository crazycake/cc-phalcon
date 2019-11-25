<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'b4d1ab4');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }