<?php namespace local; defined('CONFPATH') or die('No direct script access.');

abstract class CDRConverter_CDR
{
	// функции преобразований CDR
	protected $conv_func   = array(
		'num2e164',
	);

	private   $delimiter     = ',';           // разделитель полей в текстовой строке
	private   $datet_frmt    = 'd.m.Y H:i:s'; // формат даты и времени

	// "сырые" данные
	private   $raw_min_count = 4;             // минимальное значение значений в строке
	private   $raw_count     = array();       // кол-во значений всего в строке, может быть несколько значений
	protected $raw_arr       = array();       // массив всех значений из строки

	// преобразованные данные
	private   $fields        = array(         // ассоциативные наименования полей
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
	);
	private   $val           = array();       // поля со значениями для преобразования
	private   $val_index     = array();       // сопоставление полей индексам в строке
	protected $val_ext       = array();       // другие поля для внутренних преобразований номеров

	private   $time;                          // время записи CDR

	private   $file_name    = 'unknown file';
	private   $file_num_str = 0;

	protected $international;                 // МН вызов
	protected $redir_status;                  // перенаправленый вызов
	private   $skip_cdr;                      // Пропустить запись
	private   $break_conv;                    // Прервать процесс конвертации CDR

	protected $empty_values = array(
		'none',
		'NULL',
	);
	private   $empty_val     = 0;             // нулевое значение поля

	abstract protected function redirected_nums();

	public function __construct($delimiter = NULL)
	{
		if ( ! empty($delimiter))
			$this->delimiter = $delimiter;
	}

	private function init_new_cdr()
	{
		$this->file_num_str++;
		$this->redir_status  = FALSE;
		$this->international = FALSE;
		$this->skip_cdr      = FALSE;
		$this->break_conv    = FALSE;
		$this->val_ext       = array();
	}

	public function convert($string)
	{
		$this->init_new_cdr();
		$load = $this->_raw_load($string);
		if ($load === TRUE AND $this->_converting())
		{
			if ($this->skip_cdr === TRUE)
				return TRUE;
			return $this->val;
		}
		Log::instance()->error("Not valid string: #{$this->file_num_str} in file: {$this->file_name}.");
		return FALSE;
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
			$this->skip_cdr = $bool;
		else
			return $this->skip_cdr;
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
				$this->raw_count = array($count);
		return FALSE;
	}

	public function set_indexes(array $arr)
	{
		$keys = array_keys($arr);
		// var_dump(array_diff($this->fields, $keys)); exit;
		if (array_diff($this->fields, $keys) === array())
			$this->val_index = $arr;
		else
			Log::instance()->error("Indexes of string is not seted.");
	}

	public function init_new_file($file_name)
	{
		$this->file_name    = $file_name;
		$this->file_num_str = 0;
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

	private function _converting()
	{
		$this->conv_base();
		if ( ! empty($this->conv_func))
			// var_dump($this->val); exit;
			foreach ($this->conv_func as $f)
			{
				if ($this->break_conv === TRUE)
					break;
				if ($this->$f() === FALSE)
				{
					Log::instance()->error("Function: {$f} returned FALSE.");
					return FALSE;
				}
			}
		return TRUE;
	}


	protected function conv_base()
	{
		$this->time            = strtotime($this->val['datet']);
		$this->val['datet']    = date($this->datet_frmt, $this->time);
		$duration = str_replace(',', '.', $this->val['duration']);
		$this->val['duration'] = round($duration);
		// $this->val['A']        = (empty($this->val['A'])) ? 0 : $this->val['A'];
		// $this->val['B']        = (empty($this->val['B'])) ? 0 : $this->val['B'];
		$this->val['dur_oper'] = $this->val['duration'];
		$this->val['category'] = 0;
		$this->val['coast']    = 0;
	}

	protected function num2e164()
	{
		$this->val['A164'] = $this->_num2e164($this->val['A']);
		$this->val['B164'] = ($this->international !== TRUE) 
			? $this->_num2e164($this->val['B'])
			: $this->val['B'];
		// foreach (array('A', 'B') AS $num_type)
		// 	$this->val[$num_type.'164'] = $this->_num2e164($this->val[$num_type]);
	}	

	protected function _num2e164($string)
	{
		$string = preg_replace('/[^\d]/', '', $string);

		$len = strlen($string);
		switch ($len)
		{
			case 2:
			case 3:
			case 7:
				return '7495'.$string; 
			case 10: 
				return '7'.$string;
			case 11:
				return preg_replace("/^8(\d{10})$/", "7$1", $string);
			default:
				if ($len > 11) 
					return (substr($string, 0, 3) === '810') ? substr($string, 3) : $string;
				else
					return $string;
		}
	}
}
