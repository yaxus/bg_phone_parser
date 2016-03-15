<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Source
{
	//private $time_day_start;
	//private $time_day_end;

	private $src_cnf;
	private $raw_dir;
	private $pattern_files;
	private $files           = [];
	private $skip_first_rows;
	private $cdr_flow;        // obj CDR

	private $dublicates = [];
	private $dublicates_limit = 100;

	private $conf_params = [
		'raw_dir',
		'pattern_files',
		'skip_first_rows',
	];

	public function __construct(array $src_conf, $time_day, Collector $collector)
	{
		$this->set_params($src_conf);
		$this->collector = $collector;
		$this->cdr_flow  = new CDRFlow($this->src_cnf);
		$this->files     = $this->files($this->raw_dir, $this->pattern_files, $time_day);
	}

	public function convAllFiles()
	{
		foreach ($this->files as $f)
			$this->_convFile($f);
	}

	public function convEachFile()
	{
		$e = each($this->files);
		if (empty($e[1]))
			return FALSE;
		return $this->_convFile(($e[1]));
	}

	protected function _convFile($f)
	{
		$ret = [];
		if ( ! $this->valid_file($f))
			return $ret;

		$cdr = $this->cdr_flow;

		$cdr->setFile($f);

		$processed_all =
		$skipped       = 0;
		foreach (file($f) as $ind => $raw_string)
		{
			$num = $ind+1;
			// TODO добавить проверку $this->skip_first_rows и пропускать
			// и создать методы для ее установки
			if ( ! empty($this->skip_first_rows) AND $num <= $this->skip_first_rows)
				continue;

			$cdr->setNumStr($num);
			if ($cdr->conv($raw_string))
				$processed_all++;
			else
				continue;

			$cdr_arr = $cdr->get();

			// Пропускаем нулевую длительность
			if ($cdr->isSkipped() === TRUE)
			{
				$cdr_str = implode('; ', $cdr_arr);
				Log::instance()->debug("Skip string #{$num}: {$cdr_str}. Established via: {$cdr->getSkipFunction()}. ");
				$skipped++;
				continue;
			}

			// Если запись дублируется
			$this->is_dublicate($num, $cdr_arr);
			// Добавляем обработанную запись в коллектор
			$this->collector->add($cdr);
		}
		Log::instance()->info("Processed all: {$processed_all}; Of these skipped: {$skipped}.");
		return TRUE;
	}

	protected function is_dublicate($num_str, $c)
	{
		$i = ['datet', 'duration', 'A164', 'B164'];
		if (isset($this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]]))
		{
			$vals = $this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]];
			foreach ($i as $ind)
				$params[] = $c[$ind];
			$params[] = $c['port_from'];
			$params[] = $c['port_to'];
			$str_p = implode('; ', $params);
			$dublicated_str = implode(', ', $vals);
			Log::instance()->warning("CDR #{$num_str} is dublicated CDR(s) #{$dublicated_str} ({$str_p})");
			$this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]][] = $num_str;
			return TRUE;
		}
		$this->dublicates = array_slice($this->dublicates, -$this->dublicates_limit-1);
		$this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]][] = $num_str;
		return FALSE;
	}

	protected function files($dir, $pattern, $time_day)
	{
		return glob($dir.date($pattern, $time_day));
	}

	protected function valid_file($file_name)
	{
		// 1. Права на чтение
		if ( ! is_readable($file_name))
		{
			Log::instance()->error("File: {$file_name} is not readable.");
			return FALSE;
		}
		// 2. Не пустой
		elseif (filesize($file_name) == 0)
		{
			Log::instance()->warning("File: {$file_name} is empty.");
			return FALSE;
		}
		return TRUE;
	}

	protected function set_params($arr)
	{
		$this->src_cnf = $arr;
		foreach ($this->conf_params as $param)
			if ( ! isset($arr[$param]))
			{
				Log::instance()->error("Source parameter: {$param} is not set.");
				$this->isset_err = TRUE;
			}
			else
				$this->{$param} = $arr[$param];
	}

}