<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Collector
{
	private static $instance;

	private $data = [];
	// TODO перенести в статические переменные
	private $time_day;
	private $time_day_end;

	private function __clone(){}
	private function __construct($time_day)
	{
		$this->data         = array_fill(0, 24, []);
		$this->time_day     = $time_day;
		$this->time_day_end = $time_day + 86400;
	}

	public static function init($time_day)
	{
		if (empty(self::$instance))
			self::$instance = new self($time_day);
		return self::$instance;
	}

	public function add(CDRFlow $cdr_flow)
	{
		//var_dump($cdr_flow); exit;
		$time = $cdr_flow->getTime();
		if ($time <= $this->time_day)
			$h = 0;
		elseif ($time >= $this->time_day_end)
			$h = 23;
		else
			$h = (int) date("G", $time);

		$this->data[$h][] = implode("\t", $cdr_flow->get());
	}

	public function getData()
	{
		//var_dump($this->data[15]); exit;
		return $this->data;
	}


	//
}