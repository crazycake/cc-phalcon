<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'b6ec1b9');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }