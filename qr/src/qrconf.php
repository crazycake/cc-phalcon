<?php

/*
 * PHP QR Code encoder
 *
 * Common constants
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

namespace CrazyCake\Qr;

// Encoding modes
define('QR_MODE_NUL', -1);
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);
define('QR_MODE_STRUCTURE', 4);

// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

// Supported output formats
define('QR_FORMAT_TEXT', 0);
define('QR_FORMAT_PNG',  1);

//set assets path (relative paths)
define("QR_FONT_PATH_EDGES", QR_ASSETS_PATH . 'edges.ttf');
define("QR_FONT_PATH_FRAMES", QR_ASSETS_PATH . 'frames.ttf');
define("QR_FONT_PATH_DOTS", QR_ASSETS_PATH . 'dots.ttf');
define("QR_FONT_PATH_BDOTS", QR_ASSETS_PATH . 'bodydots.ttf');
define("QR_FONT_PATH_HAROP", QR_ASSETS_PATH . 'harop.ttf');
define("QR_FONT_PATH_SQUARETHINGS", QR_ASSETS_PATH . 'squarethings.ttf');

//load files (relative paths)
require_once "qrtools.php";
require_once "qrspec.php";
require_once "qrimage.php";
require_once "qrinput.php";
require_once "qrbitstream.php";
require_once "qrsplit.php";
require_once "qrrscode.php";
require_once "qrmask.php";
require_once "qrencode.php";
require_once "imageSmoothArc.php";
require_once "qrtag.php";
//modules
require_once "qrtagsdots.php";
require_once "qrtagsframes.php";
