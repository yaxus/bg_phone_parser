<?php namespace local; defined('CONFPATH') or die('No direct script access.');

use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;

class Log
{
	protected static $_instance;
	protected static $dir   = 'logs';
	protected static $level = LogLevel::DEBUG;

	public function __construct($dir = NULL, $level = NULL)
	{
		if ( ! empty($dir))
			self::$dir = $dir;
		if ( ! empty($level))
		{
			$lev = strtoupper($level);
			self::$level = constant("Psr\Log\LogLevel::{$lev}");
		}
		$this->_instance();
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