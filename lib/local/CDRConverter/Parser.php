<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Parser
{
	// Не посылается команда в биллинг на загрузку CDR
	// TODO Изменить на $send_command
	private $debug = FALSE;

	private $time_day;
	//private $raw_files     = [];
	
	private $bg_source_id;
	private $bg_stream_socket;
	private $conv_dir;
	private $cdr_data;
	//private $skip_zero_dur = TRUE;
	//private $trunk_indexes;

	private $isset_err     = FALSE;
	private $conf_params   = [
		'bg_source_id',
		'bg_stream_socket',
		'conv_dir',
		'debug',
	];

	private $sources       = [];


	public function __construct(array $conf)
	{
		//var_dump(__FUNCTION__); exit;
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

	public function treat($date)
	{
		if ($this->isset_err)
		{
			Log::instance()->info("Remove all errors...");
			return FALSE;
		}
		$this->time_day = strtotime($date);


		Log::instance()->debug("Start CDR coverter process.");

		$collector = Collector::init($this->time_day);
		foreach ($this->sources as $src_cfg)
		{
			$source = new Source($src_cfg, $this->time_day, $collector);
			$source->convAllFiles();
		}
		//var_dump($collector); exit;

		Log::instance()->debug("End CDR coverter process.");

		$zip_status = $this->zipping($this->conv_dir, $collector->getData());
		if ($zip_status === TRUE)
		{
			// Сообщаем серверу биллинга, что можно забирать CDR'ы
			return $this->send_command_load($this->time_day);
		}
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
				Log::instance()->error("{$errstr} ({$errno})");
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

	protected function zipping($conv_dir, $data)
	{
		// создаем директорию для файлов ZIP
		$dir = $conv_dir.date("Y/m/", $this->time_day);
		if ( ! file_exists($dir) AND ! mkdir($dir, 0755, TRUE))
		{
			Log::instance()->error("Not create directory: {$dir}.");
			return FALSE;
		}
		
		// чистим директорию если там что-то есть
		// array_map('unlink', glob($dir.'*.zip'));
		
		// объект для создания арховов ZIP
		$zip = new \ZipArchive();
		
		$day = date("j", $this->time_day);
		foreach ($data as $hour => $arr_data)
		{
			$name = sprintf('%1$02d_%2$02d', $day, $hour);
			$file_zip = $dir.$name.'.zip';
			if ( ! $zip->open($file_zip, \ZIPARCHIVE::OVERWRITE))
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
