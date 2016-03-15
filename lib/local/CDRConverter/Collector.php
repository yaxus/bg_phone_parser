<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Collector
{
	private static $instance;
	private        $data = [];

	private function __clone(){}
	private function __construct()
	{
		$this->data = array_fill(0, 24, []);
	}

	public static function init()
	{
		if (empty(self::$instance))
			self::$instance = new self();
		return self::$instance;
	}

	public function add(CDRFlow $cdr_flow)
	{
		//var_dump($cdr_flow); exit;
		$time = $cdr_flow->getTime();
		if ($time <= Parser::timeDay())
			$h = 0;
		elseif ($time >= Parser::timeDayEnd())
			$h = 23;
		else
			$h = (int) date("G", $time);

		$this->data[$h][] = $cdr_flow->getAsString();
	}

	public function getData()
	{
		//var_dump($this->data[15]); exit;
		return $this->data;
	}


	//
}