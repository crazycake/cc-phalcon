<?php
/**
 * Autoload phar file
 */

DEFINE('CORE_VERSION', '2d844d6');

// load App
require "phalcon/App.php";

/**
 * Kint global shortcut function
 * @param Mixed $vars - The input vars
 **/
function ss(...$vars) { s(...$vars); exit; }
