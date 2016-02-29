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
	
	private $bg_source_id;
	private $bg_stream_socket;
	private $conv_dir;
	private $skip_zero_dur = TRUE;
	private $trunk_indexes;
	private $dublicates = [];
	private $dublicates_limit = 100;

	private $cdr_data      = []; // for zipping


	private $isset_err     = FALSE;
	private $conf_params   = [
		'bg_source_id',
		'bg_stream_socket',
		'conv_dir',
		'skip_zero_dur',
		'trunk_indexes',
		'debug',
	];

	// TODO -> Source
	//private $source_params = [
	//	'raw_dir',
	//	'pattern_files',
	//	'skip_first_rows',

	//	'obj_converter',
	//	'count_raw',
	//	'delimiter',
	//	'indexes',
	//];

	private $sources       = [];


	public function __construct(array $conf)
	{
		$this->set_params($conf);

		if ( ! isset($conf['sources']) OR ! is_array($conf['sources']))
			Log::instance()->error("Parameter: 'sources' is not set or is not array.");
		else
			$this->sources = $conf['sources'];
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

	//protected function set_source_params($arr, $key_source = 0)
	//{
	//	$src = [];
	//	foreach ($this->source_params as $param)
	//	{
	//		if (empty($arr[$param]))
	//		{
	//			Log::instance()->error("Source parameter #{$key_source}: {$param} is not set.");
	//			$this->isset_err = TRUE;
	//		}
	//		else
	//			$src[$param] = $arr[$param];
	//	}
	//	$this->sources[$key_source] = $src;
	//}

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
		{
			//$this->treat_source($src_cfg);
			$source = new CDRConverter_Source($src_cfg, $this->time_day_start);
			$source->exec();
		}

		Log::instance()->debug("End CDR coverter process.");

		$zip_status = $this->zipping($this->conv_dir, $this->cdr_data);
		if ($zip_status === TRUE)
		{
			// Сообщаем серверу биллинга, что можно забирать CDR'ы
			return $this->send_command_load($this->time_day_start);
		}
		return FALSE;
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
			$fp = stream_socket_client($this->bg_stream_socket, $errno, $errstr, 3);
			if (! $fp)
			{
				Log::instance()->error("$errstr ({$errno})");
				$err = TRUE;
			}
			else 
			{
				// echo "load={$load_date}-{$h}-{$this->bg_source_id}\n";$cdr_arr['dur_oper']
				$cmd = "load={$load_date}-{$h}-{$this->bg_source_id}\n";
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

}
