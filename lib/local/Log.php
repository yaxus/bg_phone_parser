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
			Log::$dir = $dir;
		if ( ! empty($level))
		{
			$lev = strtoupper($level);
			Log::$level = constant("Psr\Log\LogLevel::{$lev}");
		}
		$this->instance();
	}

	public static function instance()
	{
		if ( ! isset(Log::$_instance))
		{
			// Create a new Log instance
			Log::$_instance = new Logger(Log::$dir, Log::$level);
		}
		return Log::$_instance;
	}
}