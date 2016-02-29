<?php

namespace local;

class Cnf
{
	private static $arr_conf;

	private function __construct(){return FALSE;}
	private function __clone()    {return FALSE;}
	public  function __wakeup()   {return FALSE;}

	public static function init($file)
	{
		if (isset(self::$arr_conf))
			return FALSE;
		self::$arr_conf = require_once $file;
	}

	public static function get($conf = NULL)
	{
		if ( ! isset(self::$arr_conf))
			return FALSE;
		if (is_null($conf))
			return self::$arr_conf;
		if ( ! isset(self::$arr_conf[$conf]))
			return FALSE;
		return self::$arr_conf[$conf];
	}
}