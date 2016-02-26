<?php namespace local; defined('CONFPATH') or die('No direct script access.');

abstract class CDRConverter_CDR
{
	// функции преобразований CDR
	protected $conv_func   = [
		'nums2e164',
	];

	private   $datet_frmt    = 'd.m.Y H:i:s'; // формат даты и времени в CDR для биллинга

	// "сырые" данные
	private   $delimiter     = ',';           // разделитель полей в текстовой строке
	private   $raw_min_count = 4;             // минимальное значение значений в строке
	private   $raw_count     = [];            // кол-во значений всего в строке, может быть несколько значений
	protected $raw_arr       = [];            // массив всех значений из строки

	// преобразованные данные
	private   $fields        = [              // ассоциативные наименования полей
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
	private   $val           = [];            // поля со значениями для преобразования
	private   $val_index     = [];            // сопоставление полей индексам в строке
	protected $val_ext       = [];            // другие поля для внутренних преобразований номеров

	private   $time;                          // время записи CDR

	private   $file_name    = 'unknown file';
	private   $file_num_str = 0;

	//protected $international;               // МН вызов
	protected $redir_status;                  // перенаправленый вызов
	private   $skip_zero_dur;                 // Пропускать с нулевой длительностью
	private   $skip_cdr;                      // Пропустить запись
	private   $skip_function;                 // Функция через которую был установлен флаг "пропустить CDR"
	private   $skip_count = 0;                // Количество пропущенных CDR
	private   $break_conv;                    // Прервать процесс конвертации CDR

	protected $empty_values = [
		'none',
		'NULL',
	];
	private   $empty_val     = 0;             // нулевое значение поля

	abstract protected function redirected_nums();

	public function __construct($delimiter = NULL)
	{
		if ( ! empty($delimiter))
			$this->delimiter = $delimiter;
	}

	public function init_new_file($file_name)
	{
		Log::instance()->info("File: {$file_name} in progress...");
		$this->file_name    = $file_name;
		$this->file_num_str = 0;
		$this->skip_count   = 0;
	}

	private function init_new_cdr()
	{
		// TODO file_num_str устанавливать методом
		$this->file_num_str++;
		$this->redir_status  = FALSE;
		//$this->international = FALSE;
		$this->skip_cdr      = FALSE;
		$this->skip_function = '';
		$this->break_conv    = FALSE;
		$this->val_ext       = [];
	}

	public function convert($string)
	{
		$this->init_new_cdr();
		$load = $this->_raw_load($string);
		if ($load === TRUE AND $this->_converting())
		{
			if ($this->skip_cdr === TRUE)
			{
				$this->skip_count++;
				$str_cdr = implode(';', $this->val);
				Log::instance()->debug("Skip from {$this->skip_function}, #{$this->file_num_str}: {$str_cdr}.");
				return FALSE;
			}
			return $this->val;
		}
		Log::instance()->error("Not valid string: #{$this->file_num_str} in file: {$this->file_name}.");
		return FALSE;
	}


	// TODO !!! Вынести в отдельный класс Converter
	private function _converting()
	{
		$this->base_init();
		$this->skip_zero();
		if ( ! empty($this->conv_func))
			foreach ($this->conv_func as $f)
			{
				$call_p = [];
				if (is_string($f))
					$call_f = [$this, $f];
				elseif (is_array($f) AND is_string($f[0]) AND is_array($f[1])) {
					$call_f = [$this, $f[0]];
					$call_p = $f[1];
				}
				else {
					Log::instance()->error("Incorrect format function parameters.");
					return FALSE;
				}

				if ( ! is_callable($call_f, TRUE)) {
					Log::instance()->error("Function: {$f} may not be called.");
					return FALSE;
				}
				$ret = call_user_func_array($call_f, $call_p);
				if ($ret === FALSE) {
					Log::instance()->error("Function: {$f} returned FALSE.");
					return FALSE;
				}
				elseif ($this->break_conv === TRUE)
					break;
			}
		return TRUE;
	}

	public function raw_get($index)
	{
		$a = $this->raw_arr;
		return ( ! empty($a[$index]) AND ! in_array($a[$index], $this->empty_values)) 
			? $a[$index]
			: $this->empty_val;
	}

	protected function set($field, $val)
	{
		if ( ! in_array($field, $this->fields))
			return FALSE;
		$this->val[$field] = $val;
		return TRUE;
	}

	public function skip_cdr($bool = NULL)
	{
		if (is_bool($bool))
		{
			$this->skip_cdr = $bool;
			if ($bool === FALSE)
				return TRUE;
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
			if (isset($bt[1]['function']) AND $bt[1]['class'])
				$this->skip_function = $bt[1]['class'].'\\'.$bt[1]['function'].'()';
		}
		else
			return $this->skip_cdr;
	}

	public function skip_count()
	{
		return $this->skip_count;
	}
	public function file_num_str()
	{
		return $this->file_num_str;
	}

	public function get($field = NULL)
	{
		if (is_null($field))
			return $this->val;
		if (isset($this->val[$field]))
			return $this->val[$field];
		return FALSE;
	}

	public function set_count_raw($count)
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

	public function set_skip_zero($skip_zero_dur)
	{
		if ($skip_zero_dur === FALSE)
			$this->skip_zero_dur = $skip_zero_dur;
		$this->skip_zero_dur = TRUE;
	}

	public function set_indexes(array $arr)
	{
		$keys = array_keys($arr);
		// var_dump(array_diff($this->fields, $keys)); exit;
		if (array_diff($this->fields, $keys) === [])
			$this->val_index = $arr;
		else
			Log::instance()->error("Indexes of string is not seted.");
	}

	public function time()
	{
		return $this->time;
	}

	public function num_str()
	{
		return $this->file_num_str;
	}

	private function _raw_load($string)
	{
		$string = trim($string);
		$mch = explode($this->delimiter, $string);
		// var_dump(count($mch), $this->raw_count); exit;
		$cnt_matches = count($mch);
		if (in_array($cnt_matches, $this->raw_count) AND ! empty($this->val_index))
		{
			$this->raw_arr = $mch;
			foreach ($this->fields as $field)
				$this->val[$field] = $this->raw_get($this->val_index[$field]);
			return TRUE;
		}
		Log::instance()->error(__FUNCTION__." returned FALSE.");
		return FALSE;
	}

	protected function base_init()
	{
		// TODO добавить возможность комбинирования поля с временем из нескольких полей
		$this->time            = strtotime($this->val['datet']);
		$this->val['datet']    = date($this->datet_frmt, $this->time);
		$dur                   = str_replace(',', '.', $this->val['duration']);
		$this->val['duration'] = round($dur);
		$this->val['dur_oper'] = $this->val['duration'];
		$this->val['category'] = 0;
		$this->val['coast']    = 0;
	}

	protected function skip_zero()
	{
		if ($this->skip_zero_dur === TRUE AND $this->val['duration'] == 0)
			$this->skip_cdr(TRUE);
	}

	protected function nums2e164()
	{
		foreach (['A', 'B'] AS $num_type)
			$this->_num2e164($num_type);
	}

	protected function _num2e164($num_type)
	{
		if ( ! in_array($num_type, ['A', 'B']))
			return FALSE;
		$this->val[$num_type.'164'] = static::num2e164($this->val[$num_type]);
	}

	public static function num2e164($string)
	{
		if (empty($string))
			return '';
		$string = preg_replace('/[^\d]/', '', $string);

		$len = strlen($string);
		// TODO Код города 495 вынести в конфиг
		switch ($len)
		{
			case 2: case 3: case 7:
				return '7495'.$string;
			case 10: 
				return '7'.$string;
			case 11:
				return preg_replace("/^8(\d{10})$/", "7$1", $string);
			default:
				return static::trim_810($string);
		}
	}

	public static function trim_810($string)
	{
		return (strlen($string) > 11 AND substr($string, 0, 3) === '810')
			? substr($string, 3)
			: $string;
	}

	public function __set($field, $val)
	{
		if (isset($this->val[$field]))
			$this->val[$field] = $val;
	}

	public function __get($field)
	{
		if (isset($this->val[$field]))
			return $this->val[$field];
	}
}
