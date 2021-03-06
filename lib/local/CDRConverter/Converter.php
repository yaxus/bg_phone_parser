<?php namespace local\CDRConverter;

defined('CONFPATH') or die('No direct script access.');

class Converter
{
	protected $cdr;
	protected $val_other = [];
	// функции преобразований CDR
	protected $conv_func = [
		// Запись:
		'nums2e164',
		// аналогична записям (call_user_func_array())
		//['_num2e164',['A']],
		//['_num2e164',['B']],
	];
	private   $break_conv;                    // Прервать процесс конвертации CDR

	public function convert(CDRFlow $cdr)
	{
		$this->val_other = [];
		$this->cdr = $cdr;
		if ($this->_converting())
			return TRUE;
		Log::instance()->error("Not valid string: #{$this->cdr->getNumStr()} in file: {$this->cdr->getFileName()}.");
		return FALSE;
	}

	private function _converting()
	{
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
					Log::instance()->error("Function: {$call_f[1]} returned FALSE.");
					return FALSE;
				}
				elseif ($this->break_conv === TRUE)
					break;
			}
		return TRUE;
	}

	protected function nums2e164()
	{
		$this->_num2e164('A');
		if ($this->cdr->isInternational() !== TRUE)
		{
			$this->_num2e164('B');
		}
	}

	protected function _num2e164($num_type)
	{
		if ( ! in_array($num_type, ['A', 'B']))
			return FALSE;
		$this->cdr->{$num_type.'164'} = self::num2e164($this->cdr->{$num_type});
	}

	public static function num2e164($string)
	{
		$num = preg_replace('/[^\d]/', '', $string);
		if (empty($num))
			return $num;

		$len = strlen($num);
		// TODO Код города 495 вынести в конфиг
		switch ($len)
		{
			// Добавление "0" вначале для карты зон для объединения коротких номеров
			case 2: case 3:
				return '0'.$num;
			// Московские номера без кода города
			case 7:
				return '7495'.$num;
			// Номера без кода страны
			case 10:
				return '7'.$num;
			// Замена "8" на "7" вначале номера
			case 11:
				return preg_replace("/^8(\d{10})$/", "7$1", $num);
			// Все остальное - международные вызовы
			default:
				return self::trim_810($num);
		}
	}

	public static function trim_810($string)
	{
		return (strlen($string) > 11 AND substr($string, 0, 3) === '810')
			? substr($string, 3)
			: $string;
	}
}