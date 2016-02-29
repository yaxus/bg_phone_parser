<?php

namespace local;

defined('CONFPATH') or die('No direct script access.');

class CDRConverter_Converter
{
	protected $cdr;
	protected $val_other;
	// функции преобразований CDR
	protected $conv_func   = [
		'nums2e164',
	];
	private   $break_conv;                    // Прервать процесс конвертации CDR

	public function convert(CDRConverter_CDR $cdr)
	{
		$this->cdr = $cdr;
		if ($this->_converting())
			return $this->val;
		Log::instance()->error("Not valid string: #{$this->cdr->getNumStr()} in file: {$this->cdr->getFileName()}.");
		return FALSE;
	}


	//
	private function _converting()
	{
		//$this->base_init();
		//$this->skip_zero();
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

	protected function nums2e164()
	{
		foreach (['A', 'B'] AS $num_type)
			$this->_num2e164($num_type);
	}

	protected function _num2e164($num_type)
	{
		if ( ! in_array($num_type, ['A', 'B']))
			return FALSE;
		$this->{$num_type.'164'} = CDRConverter_CDR::num2e164($this->{$num_type});
	}

	public function __set($field, $val)
	{
		if (isset($this->{$field}))
			$this->val{$field} = $val;
		return FALSE;
	}

	public function __get($field)
	{
		if (isset($this->{$field}))
			return $this->{$field};
		return FALSE;
	}
}