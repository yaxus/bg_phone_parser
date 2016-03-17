<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Collector
{
	private static $instance;
	private static $data = [];

	private function __clone(){}
	private function __construct(){}

	public static function init()
	{
		self::$data = array_fill(0, 24, []);
		if (empty(self::$instance))
			self::$instance = new self();
		return self::$instance;
	}

	public function add(CDRFlow $cdr_flow)
	{
		$time = $cdr_flow->getTime();
		if ($time <= Parser::timeDay())
			$h = 0;
		elseif ($time >= Parser::timeDayEnd())
			$h = 23;
		else
			$h = (int) date("G", $time);

		self::$data[$h][] = $cdr_flow->getAsString();
	}

	public function getData()
	{
		return self::$data;
	}


	//
}