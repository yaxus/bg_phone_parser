<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Parser
{
	// Не посылаеть команду в биллинг на загрузку CDR
	private $send_command_on_bg = FALSE;

	private static $time_day;
	private static $time_day_end;

	private $bg_source_id;
	private $bg_stream_socket;
	private $conv_dir;

	private $isset_err     = FALSE;
	private $conf_params   = [
		'bg_source_id',
		'bg_stream_socket',
		'conv_dir',
		'send_command_on_bg',
	];

	private $sources       = [];


	public function __construct()
	{
		$conf = Cnf::get();
		$this->set_params($conf);

		if ( ! isset($conf['sources']) OR ! is_array($conf['sources']))
			Log::instance()->error("Parameter: 'sources' is not set or is not array.");
		else
			$this->sources = $conf['sources'];
	}

	public function treat($time_day)
	{
		if ($this->isset_err)
		{
			Log::instance()->info("Remove all errors...");
			return FALSE;
		}
		self::$time_day     = $time_day;
		self::$time_day_end = self::$time_day + 86400;

		Log::instance()->debug("Start CDR coverter process.");

		$collector = Collector::init();
		foreach ($this->sources as $src_cfg)
		{
			$source = new Source($src_cfg, $collector);
			$source->convAllFiles();
		}

		Log::instance()->debug("End CDR coverter process.");

		$zip_status = $this->zipping($this->conv_dir, $collector);
		if ($zip_status === TRUE AND $this->send_command_on_bg === TRUE)
		{
			// Сообщаем серверу биллинга, что можно забирать CDR'ы
			return $this->send_command_load($collector);
		}
		return FALSE;
	}

	public static function timeDay()
	{
		return self::$time_day;
	}

	public static function timeDayEnd()
	{
		return self::$time_day_end;
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

	protected function zipping($conv_dir, Collector $collector)
	{
		// создаем директорию для файлов ZIP
		$dir = $conv_dir.date("Y/m/", self::$time_day);
		if ( ! file_exists($dir) AND ! mkdir($dir, 0755, TRUE))
		{
			Log::instance()->error("Not create directory: {$dir}.");
			return FALSE;
		}

		$day = date("d", self::$time_day);

		// чистим директорию если там что-то есть
		array_map('unlink', glob($dir.$day.'*.zip'));

		// объект для создания арховов ZIP
		$zip = new \ZipArchive();

		foreach ($collector->getData() as $h => $data)
		{
			$name = sprintf('%1$02d_%2$02d', $day, $h);
			$file_zip = $dir.$name.'.zip';
			if ( ! $zip->open($file_zip, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE))
			{
			    Log::instance()->error("Not create zip archive: {$file_zip}.");
			    return FALSE;
			}
			$zip->addFromString($name, implode("\n", $data));
			$zip->close();
		}
		return TRUE;
	}

	protected function send_command_load(Collector $collector)
	{
		$load_date = date("Y-m-d", self::$time_day);

		foreach ($collector->getData() as $h => $data)
		{
			$fp = stream_socket_client($this->bg_stream_socket, $errno, $errstr, 3);
			if (! $fp)
			{
				Log::instance()->error("{$errstr} ({$errno})");
				return FALSE;
			}
			$cmd = "load={$load_date}-{$h}-{$this->bg_source_id}\n";
			if (fwrite($fp, $cmd))
				Log::instance()->info("On the socket: {$this->bg_stream_socket} send command: ".trim($cmd));
			else
			{
				Log::instance()->error("Command is not send: ".trim($cmd));
				$err = TRUE;
			}
			fclose($fp);
		}
		if ( ! empty($err))
			return FALSE;
		return TRUE;
	}

}
