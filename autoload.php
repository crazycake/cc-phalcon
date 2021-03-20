<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '9dd4d76');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
