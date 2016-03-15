<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class CDRFlow
{
	private   $converter;                     // Объект конвертера

	private   $datet_frmt    = 'd.m.Y H:i:s'; // формат даты и времени в CDR для биллинга

	// "сырые" данные
	private   $raw_delimiter = ',';           // разделитель полей в "сырой" текстовой строке
	private   $raw_min_count = 4;             // минимальное значение значений в строке
	private   $raw_count     = [];            // допестимое кол-во значений в строке, может быть несколько значений
	protected $raw_arr       = [];            // массив всех значений из строки

	// преобразованные данные
	private   $val_time;                      // время записи CDR в unix формате
	private   $val           = [];            // поля со значениями для преобразования
	private   $val_delimiter = "\t";          // разделитель полей в строке для биллинга
	private   $val_indexes   = [];            // сопоставление полей индексам в строке
	//protected $val_other     = [];            // другие поля для внутренних преобразований номеров
	private   $val_fields    = [              // ассоциативные наименования полей
		'datet',                              //   01. дата и время начала звонка (dd.MM.yyyy HH:mm:ss)
		'duration',                           //   02. длительность звонка (секунды)
		'A',                                  //   03. # A
		'A164',                               //   04. # A (E.164), далее #A164
		'B',                                  //   05. # B
		'B164',                               //   06. # B (E.164), далее #B164
		'port_from',                          //   07. port_from
		'port_to',                            //   08. port_to
		'category',                           //   09. категория звонка
		'dur_oper',                           //   10. время соединения (секунды)
		'coast',                              //   11. стоимость вызова
	];

	private   $file_name     = 'unknown file';
	private   $num_str       = 0;

	protected $is_international;              // МН вызов
	protected $is_redirected;                 // перенаправленый вызов
	private   $is_skipped;                    // Пропустить запись
	private   $skip_function;                 // Функция через которую был установлен флаг "пропустить CDR"
	private   $skip_zero_dur;                 // Пропускать с нулевой длительностью
	// TODO -> Source
	//private   $skip_count = 0;                // Количество пропущенных CDR


	protected $empty_values = [
		'none',
		'NULL',
	];
	private   $empty_val     = 0;             // нулевое значение поля

	//abstract protected function redirected_nums();

	// TODO через конфиг
	public function __construct(array $conf)
	{
		$this->skip_zero_dur = Cnf::get('skip_zero_dur');
		if (empty($conf))
			return FALSE;
		$this->raw_delimiter = $conf['delimiter'];
		$this->val_indexes   = $conf['indexes'];
		$this->setCountRaw($conf['raw_count']);
		// TODO валидация
		$this->setConverter(new $conf['obj_converter']);
		//var_dump($this->raw_count); exit;
	}

	public function setConverter(Converter $converter)
	{
		$this->converter = $converter;
	}

	public function setFile($file_name)
	{
		Log::instance()->info("File: {$file_name} in progress...");
		$this->file_name    = $file_name;
		$this->file_num_str = 0;
		$this->skip_count   = 0;
	}

	public function setNumStr($num)
	{
		$this->num_str = $num;
		return TRUE;
	}

	public function setIndexes(array $arr)
	{
		$keys = array_keys($arr);
		// var_dump(array_diff($this->val_fields, $keys)); exit;
		if (array_diff($this->val_fields, $keys) === [])
			$this->val_indexes = $arr;
		else
			Log::instance()->error("Indexes of string is not seted.");
	}

	public function conv($row)
	{
		$this->init_new_cdr();
		// TODO залогировать
		if ( ! $this->_rawLoad($row) OR ! $this->converter instanceof Converter)
			return FALSE;
		$this->base_init();
		return $this->converter->convert($this);
	}

	private function init_new_cdr()
	{
		// TODO file_num_str устанавливать методом
		$this->is_redirected    =
		$this->is_international =
		$this->is_skipped       = FALSE;
		$this->skip_function    = '';
		$this->val_other        = [];
		$this->num_str          = 0;

	}

	/**
	 * Загрузка "сырых" данных в массивы $this->raw_arr и $this->val
	 *
	 * @param $string
	 *
	 * @return bool
	 */
	private function _rawLoad($string)
	{
		$string = trim($string);
		$mch = explode($this->raw_delimiter, $string);
		$cnt_matches = count($mch);
		if (in_array($cnt_matches, $this->raw_count) AND ! empty($this->val_indexes))
		{
			$this->raw_arr = $mch;
			foreach ($this->val_fields as $field)
				$this->val[$field] = $this->getRaw($this->val_indexes[$field]);
			return TRUE;
		}
		Log::instance()->error(__FUNCTION__." returned FALSE.");
		return FALSE;
	}

	/**
	 * Установка флага "Пропустить CDR"
	 *
	 * @param null $bool
	 *
	 * @return bool
	 */
	public function isSkipped($bool = NULL)
	{
		if (is_bool($bool))
		{
			$this->is_skipped = $bool;
			if ($bool === FALSE)
				return TRUE;
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
			if (isset($bt[1]['function']) AND $bt[1]['class'])
				$this->skip_function = $bt[1]['class'].'\\'.$bt[1]['function'].'()';
		}
		else
			return $this->is_skipped;
	}


	/**
	 * Установка флага "Перенаправленный вызов"
	 *
	 * @param null $bool
	 *
	 * @return bool
	 */
	public function isRedirected($bool = NULL)
	{
		if (is_bool($bool))
			$this->is_redirected = $bool;
		return $this->is_redirected;
	}

	/**
	 * Установка флага "Международный вызов"
	 *
	 * @param null $bool
	 *
	 * @return bool
	 */
	public function isInternational($bool = NULL)
	{
		if (is_bool($bool))
			$this->is_international = $bool;
		return $this->is_international;
	}

	/**
	 * Вернуть значение обработанного поля или все значения
	 *
	 * @param null $field
	 *
	 * @return mixed
	 */
	public function get($field = NULL)
	{
		if (is_null($field))
			return $this->val;
		if (isset($this->val[$field]))
			return $this->val[$field];
		return FALSE;
	}
	public function getAsString()
	{
		return implode($this->val_delimiter, $this->val);
	}

	public function getFileName()
	{
		return $this->file_name;
	}

	public function getNumStr()
	{
		return $this->num_str;
	}

	public function getSkipFunction()
	{
		return $this->skip_function;
	}

	/**
	 * Время записи CDR в Unix формате
	 * @return int
	 */
	public function getTime()
	{
		return $this->val_time;
	}

	protected function setCountRaw($count)
	{
		if (is_array($count))
		{
			foreach ($count as $cnt)
				if ($cnt < $this->raw_min_count)
					return FALSE;
			$this->raw_count = $count;
		}
		elseif (is_int($count))
			if ($count < $this->raw_min_count)
				return FALSE;
			else
				$this->raw_count = [$count];
		return FALSE;
	}

	protected function base_init()
	{
		// TODO добавить возможность комбинирования поля с временем из нескольких полей
		$this->val_time        = strtotime($this->val['datet']);
		$this->val['datet']    = date($this->datet_frmt, $this->val_time);
		$dur                   = str_replace(',', '.', $this->val['duration']);
		$this->val['duration'] = round($dur);
		$this->val['dur_oper'] = $this->val['duration'];
		$this->val['category'] = 0;
		$this->val['coast']    = 0;
	}

	protected function skip_zero()
	{
		if ($this->skip_zero_dur === TRUE AND $this->val['duration'] == 0)
			$this->isSkipped(TRUE);
	}





	// complete

	public function getRaw($index)
	{
		$a = $this->raw_arr;
		return ( ! empty($a[$index]) AND ! in_array($a[$index], $this->empty_values))
			? $a[$index]
			: $this->empty_val;
	}

	public function __set($field, $val)
	{
		if (isset($this->val[$field]))
			$this->val[$field] = $val;
		return NULL;
	}

	public function __get($field)
	{
		if (isset($this->val[$field]))
			return $this->val[$field];
		return NULL;
	}

	public function __isset	($field)
	{
		return isset($this->val[$field]);
	}


}
