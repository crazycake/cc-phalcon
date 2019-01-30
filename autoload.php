<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', 'ef4e414');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }