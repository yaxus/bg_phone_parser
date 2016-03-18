<?php

namespace local\CDRConverter;

use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;

defined('CONFPATH') or die('No direct script access.');

class Log
{
	protected static $_instance;
	protected static $dir   = 'logs';
	protected static $level = LogLevel::DEBUG;

	public static function init($dir = NULL, $level = NULL)
	{
		if ( ! empty($dir))
			self::$dir = $dir;
		if ( ! empty($level))
		{
			$lev = strtoupper($level);
			self::$level = constant("\Psr\Log\LogLevel::{$lev}");
		}
		self::instance();
	}

	public static function instance()
	{
		if ( ! isset(self::$_instance))
		{
			// Create a new Log instance
			self::$_instance = new Logger(self::$dir, self::$level);
		}
		return self::$_instance;
	}
}