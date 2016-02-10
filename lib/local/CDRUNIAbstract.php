<?php namespace local; defined('CONFPATH') or die('No direct script access.');

abstract class CDRUNIAbstract extends CDRConverter_CDR
{
	protected $delim_nums = '&';
	protected $dn_in_a164 = TRUE;
	protected $port_pref  = '?';

	protected $trunk_indexes = array();

	protected $a_num2port = array(
		'74957300242' => 12,
		'74957774547' => 12,
		'74995766554' => 13,
		'74995766555' => 13,
		'74995766556' => 13,
		'74995766559' => 13,
	);

	public function set_trunk_indexes(array $arr)
	{
		if (! empty($arr))
			$this->trunk_indexes = $arr;
		else
			Log::instance()->error("Trunk indexes is empty.");
	}

	protected function port2str()
	{
		foreach (array('port_from', 'port_to') AS $port_type)
		{
			$port_val = $this->get($port_type);
			$this->set_port($port_type, $port_val);
		}
	}

	protected function set_port($port_type, $port_val)
	{
		$tp = $this->port_pref;
		if (isset($this->term_alias[$port_val]))
		{
			$key = $this->term_alias[$port_val];
			$this->set($port_type, $this->trunk_indexes[$key]);
			$this->val_ext[$port_type.'_key'] = $key;
			$this->val_ext[$port_type.'_pref'] = $tp.str_pad($key, 2, '0', STR_PAD_LEFT);
		}
		else
		{
			$this->set($port_type, '_Undef');
			$this->val_ext[$port_type.'_key'] = 0;
			$this->val_ext[$port_type.'_pref'] = $tp.'__';
		}
	}

	protected function port_is_outer($port_type)
	{
		$port_key = $this->val_ext[$port_type.'_key'];
		if ($port_key > 0 AND $port_key < 10)
			return TRUE;
		return FALSE;
	}

	protected function skip_inner_calls()
	{

		if ( ! $this->port_is_outer('port_from') AND ! $this->port_is_outer('port_to'))
		{
			// $a_num = $this->_num2e164($this->raw_arr[11]); // Исходящий А-номер (НЕ поле для биллинга)
			// if (preg_match("/5766555/", $a_num))
			// {
			// 	var_dump($this->val_ext); echo "\n", $a_num, "\n"; exit;
			// }
			
			$this->skip_cdr(TRUE);
		}
	}

	protected function combination_nums()
	{
		$a_num = array($this->val_ext['port_from_pref'], $this->get('A'));
		if ( ! empty($this->val_ext['num_dn']))
		{
			// if ($this->get('A164') == '4957774547' AND $this->val_ext['num_dn'] == '88888')
			// if ($this->val_ext['num_dn'] == '88888')
			// {
			// 	var_dump($this->get(), $this->val_ext);
			// 	$this->i++;
			// 	if ($this->i > 3)
			// 		exit;
			// }


			$a_num[] = $this->val_ext['num_dn'];
			if ($this->dn_in_a164)
				$this->set('A164', implode($this->delim_nums, array($this->get('A164'), $this->val_ext['num_dn'])));
		}
		$b_num = array($this->val_ext['port_to_pref'], $this->get('B'));
		$this->set('A', implode($this->delim_nums, $a_num));
		$this->set('B', implode($this->delim_nums, $b_num));
	}
}