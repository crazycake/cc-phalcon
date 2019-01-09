<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'a49210c');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }