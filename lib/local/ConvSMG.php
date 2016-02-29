<?php namespace local; defined('CONFPATH') or die('No direct script access.');

class CDRSMG extends ConvUNIAbstract
{
	protected $port_pref  = 's';

	protected $conv_func  = [
		'port2str',
		'skip_inner_calls',
		'skip_pref_1xxx',   // такой номерной емкости нет, стоит автоответчик beeline, используется для внутренних целей, до 31.10.2015
		// 'substitution',
		'redirected_nums',
		'dn_numbers',
		'check_international',
		'nums2e164',
		'a_num2port',
		'combination_nums',
	];

	protected $term_alias = [
		// Operators
		'Orange'   => 1,
		'Beeline'  => 2,
		'BeeLine'  => 2,
		'Билайн'   => 2,
		'MTC'      => 3, // Латиницей?!?!?!
		'МТС'      => 3, // Кириллицей
		'MTS'      => 3,
		'MTT'      => 4,
		'Macomnet' => 5,
		// Contracts
		'RN'        => 10,
		'RN-Inform' => 10,
		'РН-Информ' => 10,
		'RCN'       => 11,
		'PCC'       => 11,
	];

	protected function skip_pref_1xxx()
	{
		if ($this->time() > mktime(0,0,0,10,31,2015))
			return TRUE;
		$a_num = $this->B;
		if (preg_match("/^1[^0]\d{8}$/", $a_num))
			$this->is_skipped(TRUE);
	}

	protected function redirected_nums()
	{
		$redir = $this->getRaw(13);
		if (empty($redir))
			return TRUE;
		$this->redir_status = TRUE;
		// Исходящий переадресующий номер
		$this->A = $redir;
	}

	protected function dn_numbers()
	{
		// $this->raw_arr[8] - номер вызывающего абонента на входе;
		if ($this->redir_status === TRUE)
			return TRUE;
		$a_num_in = static::num2e164($this->raw_arr[8]);
		
		if (preg_match("/^\d?(49[589]\d{7})(\d+)$/", $a_num_in, $mch))
		{
			$this->A = $mch[1];
			$this->val_other['num_dn'] = $mch[2];
		}
		elseif (preg_match("/^\d{4,6}$/", $a_num_in))
			$this->val_other['num_dn'] = $a_num_in;
	}

	protected function check_international()
	{
		$b_num_in = $this->getRaw(17); // Входящий B номер
		if (substr($b_num_in, 0, 3) == '810')
			$this->international = TRUE;
	}

	//protected function num2e164_2()
	//{
	//	$this->A164 = $this->_num2e164($this->A);
	//	$this->B164 = ($this->international !== TRUE)
	//		? $this->_num2e164($this->val['B'])
	//		: $this->val['B'];
	//}

	protected function a_num2port()
	{
		$a_num = $this->A164;
		if ( ! empty($this->a_num2port[$a_num]) AND ! $this->port_is_outer('port_from'))
		{
			// меняем port_from для учета вызова в биллинге по транку клиента
			$this->port_from = $this->trunk_indexes[$this->a_num2port[$a_num]];
		}
	}
}