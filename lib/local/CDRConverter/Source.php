<?php

namespace local;

defined('CONFPATH') or die('No direct script access.');

class CDRConverter_Source
{
	private $skipped_cdr;
	private $time_day;

	private $src_cnf;
	private $raw_dir;
	private $pattern_files;
	private $skip_first_rows;

	private $conf_params = [
		'raw_dir',
		'pattern_files',
		'skip_first_rows',
		// TODO -> CDR
		//'obj_converter',
		//'count_raw',
		//'delimiter',
		//'indexes',
	];

	public function __construct(array $src_conf, $time_day)
	{
		$this->set_params($src_conf);
		$this->time_day = $time_day;
	}

	public function exec()
	{
		$files = $this->files($this->raw_dir, $this->pattern_files, $this->time_day);
		$cdr = new CDRConverter_CDR($this->src_cnf);

		foreach ($files as $f)
		{
			if ( ! $this->valid_file($f))
				continue;

			$cdr->setFile($f);

			foreach (file($f) as $num => $raw_string)
			{
				$cdr->setNumStr($num);
				$cdr_arr = $cdr->conv($raw_string);
				// TODO remove exit;
				var_dump($cdr_arr); exit;

				if ( ! $cdr_arr)
					continue;

				// Пропускаем нулевую длительность
				// TODO вынести в CDR
				if ($this->skip_zero_dur === TRUE AND $cdr_arr['dur_oper'] == 0)
					continue;

				// Если запись дублируется
				$this->is_dublicate($cdr->file_num_str(), $cdr_arr);
				// if ($this->is_dublicate($cdr_arr))
				// {
				// 	Log::instance()->info("CDR #{$cdr->file_num_str()} is dublicate");
				// 	continue;
				// }

				$time = $cdr->time();
				if ($time <= $this->time_day_start)
					$h = 0;
				elseif ($time >= $this->time_day_end)
					$h = 23;
				else
					$h = (int) date("G", $time);

				$this->cdr_data[$h][] = implode("\t", $cdr_arr);
			}
			Log::instance()->info("Processed all: {$num}; Of these skipped: {$cdr->skip_count()}.");
		}
	}

	protected function files($dir, $pattern, $time_day)
	{
		// $arr_files = glob($this->raw_dir."rtu".date("Ymd", $time_day)."*");
		return glob($dir.date($pattern, $time_day));
		// $this->raw_files = array_merge($this->raw_files, $arr_files);
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