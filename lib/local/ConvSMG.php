<?php namespace local; defined('CONFPATH') or die('No direct script access.');

class ConvSMG extends ConvUNIAbstract
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
		//['_num2e164',['A']],
		//['_num2e164',['B']],
		'nums2e164',
		'a_num2port',
		'combination_nums',
	];

	protected $port2index = [
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
		if ($this->cdr->getTime() > mktime(0,0,0,10,31,2015))
			return TRUE;
		$a_num = $this->B;
		if (preg_match("/^1[^0]\d{8}$/", $a_num))
			$this->cdr->isSkipped(TRUE);
	}

	protected function redirected_nums()
	{
		$redir_num = $this->cdr->getRaw(13);
		if (empty($redir_num))
			return TRUE;
		$this->cdr->isRedirected(TRUE);
		// Исходящий переадресующий номер
		$this->cdr->A = $redir_num;
	}

	protected function dn_numbers()
	{
		// $this->raw_arr[8] - номер вызывающего абонента на входе;
		if ($this->cdr->isRedirected() === TRUE)
			return TRUE;
		$a_num_in = self::num2e164($this->cdr->getRaw(8));
		
		if (preg_match("/^\d?(49[589]\d{7})(\d+)$/", $a_num_in, $mch))
		{
			$this->cdr->A = $mch[1];
			$this->val_other['num_dn'] = $mch[2];
		}
		elseif (preg_match("/^\d{4,6}$/", $a_num_in))
			$this->val_other['num_dn'] = $a_num_in;
	}

	protected function check_international()
	{
		$b_num_in = $this->cdr->getRaw(17); // Входящий B номер
		if (substr($b_num_in, 0, 3) == '810')
			$this->cdr->isInternational(TRUE);
	}

	protected function a_num2port()
	{
		$a_num = $this->cdr->A164;
		if ( ! empty($this->a_num2port[$a_num]) AND ! $this->port_is_outer('port_from'))
		{
			// меняем port_from для учета вызова в биллинге по транку клиента
			$this->cdr->port_from = $this->trunk_indexes[$this->a_num2port[$a_num]];
		}
	}
}