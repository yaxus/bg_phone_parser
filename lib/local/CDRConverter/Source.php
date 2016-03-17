<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Source
{
	private $src_cnf;         // Конфигурация источника
	private $collector;       // obj Collector
	private $cdr_flow;        // obj CDRFlow
	private $raw_dir;         // Директория с файлами для обработки
	private $pattern_files;   // Шаблон файлов для обработки, задается через date($this->pattern_files, ...)
	private $files = [];      // Файлы с CDR для обработки
	private $skip_first_rows; // Пропускать строки вначе файла

	// Обнаружение повторяющихся CDR записей и вывод в лог
	private $dublicates = [];        // Массив последних обработанных CDR
	private $dublicates_limit = 100; // Хранить последних N записей

	private $conf_params = [
		'raw_dir',
		'pattern_files',
		'skip_first_rows',
	];

	public function __construct(array $src_conf, Collector $collector)
	{
		$this->setParams($src_conf);
		$this->collector = $collector;
		$this->cdr_flow  = new CDRFlow($this->src_cnf);
		$this->files     = $this->listFiles();
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
		if ( ! $this->validFile($f))
			return $ret;

		$cdr = $this->cdr_flow;

		$cdr->setFile($f);

		$processed_all =
		$skipped       = 0;
		foreach (file($f) as $ind => $raw_string)
		{
			$num = $ind+1;
			// Пропустить строки вначале файла
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
				$cdr_str   = $cdr->getAsString();
				$skip_func = $cdr->getSkipFunction();
				Log::instance()->debug("Skip string #{$num}: {$cdr_str}. Established via: {$skip_func}. ");
				$skipped++;
				continue;
			}

			// Если запись дублируется, пишем в лог сообщение
			$this->isDublicate($cdr);
			// TODO проверить на ошибочную длительность
			// Добавляем обработанную запись в коллектор
			$this->collector->add($cdr);
		}
		Log::instance()->info("Processed all: {$processed_all}; Of these skipped: {$skipped}.");
		return TRUE;
	}

	protected function isDublicate(CDRFlow $cdr_flow)
	{
		$i       = ['datet', 'duration', 'A164', 'B164'];
		$c       = $cdr_flow->get();
		$num_str = $cdr_flow->getNumStr();
		if (isset($this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]]))
		{
			$vals           = $this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]];
			$cdr_str        = $cdr_flow->getAsString();
			$dublicated_str = implode(', ', $vals);
			Log::instance()->warning("CDR #{$num_str} is dublicated CDR(s) #{$dublicated_str} ({$cdr_str})");
			$this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]][] = $num_str;
			return TRUE;
		}
		$this->dublicates = array_slice($this->dublicates, -$this->dublicates_limit-1);
		$this->dublicates[$c[$i[0]]][$c[$i[1]]][$c[$i[2]]][$c[$i[3]]][] = $num_str;
		return FALSE;
	}

	protected function listFiles()
	{
		return glob($this->raw_dir.date($this->pattern_files, Parser::timeDay()));
	}

	protected function validFile($file_name)
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

	protected function setParams($arr)
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