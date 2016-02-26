<?php

namespace local;
use ZipArchive;

defined('CONFPATH') or die('No direct script access.');

class CDRConverter_Parser
{
	// Не посылается команда в биллинг на загрузку CDR
	// TODO Изменить на $send_command
	private $debug = FALSE;

	private $time_day_start;
	private $time_day_end;
	private $raw_files     = [];
	
	private $source_id;
	private $conv_dir;
	private $skip_zero_dur = TRUE;
	private $stream_socket;
	private $trunk_indexes;
	private $dublicates = [];
	private $dublicates_limit = 100;

	private $cdr_data      = []; // for zipping


	private $isset_err     = FALSE;
	private $conf_params   = [
		'source_id',
		'conv_dir',
		'pass_zero_dur',
		'stream_socket',
		'trunk_indexes',
		'skip_zero_dur',
		'debug',
	];

	private $source_params = [
		'obj_cdr',
		'raw_dir',
		'pattern_files',
		'count_raw',
		'delimiter',
		'indexes',
	];

	private $sources       = [];


	public function __construct(array $conf)
	{
		$this->set_params($conf);

		if ( ! isset($conf['sources']))
			$this->set_source_params($conf);
		else
			foreach ($conf['sources'] as $key => $source)
				$this->set_source_params($source, $key);
	}

	protected function set_params($arr)
	{
		foreach ($this->conf_params as $param)
			if ( ! isset($arr[$param]))
			{
				Log::instance()->error("Parameter: {$param} is not set.");
				$this->isset_err = TRUE;
			}
			else
				$this->{$param} = $arr[$param];
	}

	protected function set_source_params($arr, $key_source = 0)
	{
		$src = [];
		foreach ($this->source_params as $param)
		{
			if (empty($arr[$param]))
			{
				Log::instance()->error("Source parameter #{$key_source}: {$param} is not set.");
				$this->isset_err = TRUE;
			}
			else
				$src[$param] = $arr[$param];
		}
		$this->sources[$key_source] = $src;
	}

	public function treat($date)
	{
		if ($this->isset_err)
		{
			Log::instance()->info("Remove all errors...");
			return FALSE;
		}
		$this->time_day_start = strtotime($date);
		$this->time_day_end   = $this->time_day_start + 86400;

		Log::instance()->debug("Start CDR coverter process.");
		$this->cdr_data = array_fill(0, 24, array());

		foreach ($this->sources as $src_cfg)
			$this->treat_source($src_cfg);

		Log::instance()->debug("End CDR coverter process.");

		$zip_status = $this->zipping($this->conv_dir, $this->cdr_data);
		if ($zip_status === TRUE)
		{
			// Сообщаем серверу биллинга, что можно забирать CDR'ы
			return $this->send_command_load($this->time_day_start);
		}
		return FALSE;
	}
	
	protected function treat_source($src_cfg)
	{
		$files = $this->files($src_cfg['raw_dir'], $src_cfg['pattern_files'], $this->time_day_start);
		$cdr = new $src_cfg['obj_cdr']($src_cfg['delimiter']);
		$cdr->set_count_raw($src_cfg['count_raw']);
		$cdr->set_indexes($src_cfg['indexes']);
		$cdr->set_trunk_indexes($this->trunk_indexes);
		$cdr->set_skip_zero($this->skip_zero_dur);

		foreach ($files as $f)
		{
			if ( ! $this->valid_file($f))
				continue;

			$cdr->init_new_file($f);

			foreach (file($f) as $key => $raw_string)
			{
				$cdr_arr = $cdr->convert($raw_string);
				// var_dump($raw_string, $res); exit;
				if ( ! $cdr_arr)
					continue;

				// Пропускаем нулевую длительность
				// TODO вынести в CDR
				if ($this->pass_zero_dur === TRUE AND $cdr_arr['dur_oper'] == 0)
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
			Log::instance()->info("Processed all: {$cdr->file_num_str()}; Of these skipped: {$cdr->skip_count()}.");
		}
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
			Log::instance()->warning("CDR #{$num_str} is dublicated CDR #{$dublicated_str} ({$str_p})");
			$this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]][] = $num_str;
			return TRUE;
		}
		$this->dublicates = array_slice($this->dublicates, -$this->dublicates_limit-1);
		$this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]][] = $num_str;
		return FALSE;
	}


	protected function send_command_load($time_day)
	{
		if ($this->debug)
			return FALSE;
		$load_date = date("Y-m-d", $time_day);
		for ($h=0;$h<24;$h++)
		{
			$fp = stream_socket_client($this->stream_socket, $errno, $errstr, 3); 
			if (! $fp)
			{
				Log::instance()->error("$errstr ({$errno})");
				$err = TRUE;
			}
			else 
			{
				// echo "load={$load_date}-{$h}-{$this->source_id}\n";$cdr_arr['dur_oper']
				$cmd = "load={$load_date}-{$h}-{$this->source_id}\n";
				if (fwrite($fp, $cmd))
					Log::instance()->info("Send command: ".trim($cmd));
				else
				{
					Log::instance()->error("Command is not send: ".trim($cmd));
					$err = TRUE;
				}
			}
			fclose($fp);
		}
		if ( ! empty($err))
			return FALSE;
		return TRUE;
	}

	// !!!
	public function files($dir, $pattern, $time_day)
	{
		// $arr_files = glob($this->raw_dir."rtu".date("Ymd", $time_day)."*");
		return glob($dir.date($pattern, $time_day));
		// $this->raw_files = array_merge($this->raw_files, $arr_files);
	}

	// public function clear_raw_files()
	// {
	// 	$this->raw_files = array();
	// }

	protected function zipping($conv_dir, $data)
	{
		// создаем директорию для файлов ZIP
		$dir = $conv_dir.date("Y/m/", $this->time_day_start);
		if ( ! file_exists($dir) AND ! mkdir($dir, 0755, TRUE))
		{
			Log::instance()->error("Not create directory: {$dir}.");
			return FALSE;
		}
		
		// чистим директорию если там что-то есть
		// array_map('unlink', glob($dir.'*.zip'));
		
		// объект для создания арховов ZIP
		$zip = new ZipArchive();
		
		$day = date("j", $this->time_day_start);
		foreach ($data as $hour => $arr_data)
		{
			$name = sprintf('%1$02d_%2$02d', $day, $hour);
			$file_zip = $dir.$name.'.zip';
			if ( ! $zip->open($file_zip, ZIPARCHIVE::OVERWRITE))
			{
			    Log::instance()->error("Not create zip archive: {$file_zip}.");
			    return FALSE;
			}
			$zip->addFromString($name, implode("\n", $arr_data));
			$zip->close();
		}
		return TRUE;
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


}
