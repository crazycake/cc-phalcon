<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '2b8118d');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
