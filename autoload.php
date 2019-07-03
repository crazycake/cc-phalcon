<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '52d3eb8');

// load App
require "phalcon/App.php";

/**
 * SD Kint shortcut function 
 **/
function ss(...$vars) { s(...$vars); exit; }