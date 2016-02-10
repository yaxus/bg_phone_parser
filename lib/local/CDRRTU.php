<?php namespace local; defined('CONFPATH') or die('No direct script access.');

class CDRRTU extends CDRUNIAbstract
{
	protected $port_pref = 'r';

	protected $conv_func  = array(
		'port2str',
		'skip_inner_calls',
		'substitution',
		'redirected_nums',  // если есть доп. поля с переадресующим номером, подставляем их
		'dn_numbers',
		'trim_B_88_89',     // обрезаем все номера до 11 символов
		// 'trim_A_49',        // обрезаем лишнее
		'num2e164',
		'a_num2port',
		'not_skip_cdr_int_nums', // Не пропускать (skip) CDR с внутренними номерами. Временно, до 23.10.2015
		'combination_nums', // запись номера с префиксом оператора с которого пришев вызов
	);

	protected $term_alias = array(
		// Outer
		'80.75.130.132'  => 4,  // calls not found 2014-11-18
		'80.75.130.143'  => 4,  // calls started from 2014-11-24
		'80.75.130.154'  => 4,  // use
		'195.128.80.164' => 5,  // use
		// Inner
		'10.23.0.235'    => 15, // AS5350XM
		'192.168.118.16' => 15, // use from 2015-01-15
		'192.168.118.17' => 15, // not use from 2015-01-14 (switch to SMG 2016)
		'217.22.160.40'  => 14, // NefteGarant
	);

	protected function substitution()
	{
		// Подмена А номера
		$a_num = $this->get('A');
		if (empty($a_num))
			$this->set('A', 0);
		// ЧР-Информ, ДальСатКом и ГПНШ
		if (preg_match("/^(?>11|15|19)\d{4}/", $a_num))
			$this->set('A', ( ! empty($mch[11])) ? $mch[11] : $a_num);
		// Сайт Системс
		$this->set('A', preg_replace("/^86999(\d+)/", "$1", $a_num));
	}

	protected function redirected_nums()
	{
		$redir = $this->raw_get(14);
		if (empty($redir))
			return TRUE;
		$this->redir_status === TRUE;
		// Исходящий переадресующий номер
		if (strlen($redir) != 10)
		{
			Log::instance()->error("Redirected number: {$redir} must be 10 characters. File string number #".$this->file_num_str());
			return FALSE;
		}
		$this->set('A', $redir);
	}

	protected function dn_numbers()
	{
		if ($this->redir_status === TRUE)
			return TRUE;
		$a_num = $this->get('A');
		if (preg_match("/^\d?(49[589]\d{7})(\d+)$/", $a_num, $mch))
		{
			$this->set('A', $mch[1]);
			$this->val_ext['num_dn'] = $mch[2];
		}
		elseif (preg_match("/^\d{4,6}$/", $a_num))
		{
			$this->val_ext['num_dn'] = $a_num;
			$this->set('A', $this->_num2e164($this->raw_arr[11])); // Исходящий А-номер (НЕ поле для биллинга)
		}

	}

	protected function trim_B_88_89()
	{
		$b_num = $this->get('B');
		if (strlen($b_num) > 11 AND (substr($b_num, 0, 2) == '88' OR substr($b_num, 0, 2) == '89'))
			$this->set('B', substr($b_num, 0, 11));
	}

	// Номера сравниваются с полем "Исходящий А-номер" (не для биллинга)
	// Сделано для номеров, к которым добавлен внутренний номер
	protected function a_num2port()
	{
		if ( ! preg_match("/^\d?(49[589]\d{7})\d*$/", $this->raw_arr[11], $mch)) // Исходящий А-номер (НЕ поле для биллинга)
			return TRUE;
		$a_num = '7'.$mch[1];
		if ( ! empty($this->a_num2port[$a_num]))
			$this->set('port_from', $this->trunk_indexes[$this->a_num2port[$a_num]]);
	}

	protected function not_skip_cdr_int_nums()
	{
		if ($this->time() > mktime(0,0,0,10,23,2015))
			return TRUE;
		if (in_array($this->get('port_from'), array('@Avtosnabjenets', '@Neftegarant')))
			$this->skip_cdr(FALSE);
		// $a_num = $this->_num2e164($this->raw_arr[11]); // Исходящий А-номер (НЕ поле для биллинга)
		// if (preg_match("/5766555/", $a_num))
		// {
		// 	var_dump($this->val_ext, $this->get()); echo "\n", $a_num, "\n"; exit;
		// }

	}

}