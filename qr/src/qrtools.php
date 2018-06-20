<?php
/*
 * PHP QR Code encoder
 *
 * Toolset, handy and debug utilites.
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

class QRtools {

	public static function binarize($frame)
	{
		$len = count($frame);
		foreach ($frame as &$frameLine) {

			for ($i = 0; $i < $len; $i++)
				$frameLine[$i] = (ord($frameLine[$i])&1)?'1':'0';
		}

		return $frame;
	}
	
	public static function log($outfile, $err)
	{
		if (empty(QR_LOG_DIR) || empty($err))
			return;
			
		if ($outfile !== false)
			file_put_contents(QR_LOG_DIR.basename($outfile).'-qrlib-errors.log', date('Y-m-d H:i:s').': '.$err, FILE_APPEND);
		else
			file_put_contents(QR_LOG_DIR.'qrlib-errors.log', date('Y-m-d H:i:s').': '.$err, FILE_APPEND);
	}
	
	public static function markTime($markerId)
	{
		list($usec, $sec) = explode(" ", microtime());

		$time = ((float)$usec + (float)$sec);

		if (!isset($GLOBALS['qr_time_bench']))
			$GLOBALS['qr_time_bench'] = [];

		$GLOBALS['qr_time_bench'][$markerId] = $time;
	}
}

QRtools::markTime('start');
