<?php namespace local;
use local\CDRConverter\Converter;
use local\CDRConverter\Log;
use local\CDRConverter\Cnf;

defined('CONFPATH') or die('No direct script access.');

abstract class ConvUNIAbstract extends Converter
{
	protected $delim_nums = '&';
	protected $dn_in_a164 = TRUE;
	protected $port_pref  = '?';
	protected $port2index = [];

	protected $trunk_indexes = [];

	protected $a_num2port = [
		'74957300242' => 12,
		'74957774547' => 12,
		'74995766554' => 13,
		'74995766555' => 13,
		'74995766556' => 13,
		'74995766559' => 13,
	];

	public function __construct()
	{
		$this->set_trunk_indexes();
	}

	protected function set_trunk_indexes()
	{
		$arr = Cnf::get('trunk_indexes');
		if (! empty($arr))
			$this->trunk_indexes = $arr;
		else
			Log::instance()->error("Trunk indexes is empty.");
	}

	protected function port2str()
	{
		foreach (['port_from', 'port_to'] AS $port_type)
			$this->set_port($port_type, $this->cdr->{$port_type});
	}

	protected function set_port($port_type, $port_val)
	{
		$tp = $this->port_pref;
		//var_dump($this->term_alias, $port_val); exit;
		if (isset($this->port2index[$port_val]))
		{
			$key = $this->port2index[$port_val];
			$this->cdr->$port_type = $this->trunk_indexes[$key];
			$this->val_other[$port_type.'_key'] = $key;
			$this->val_other[$port_type.'_pref'] = $tp.str_pad($key, 2, '0', STR_PAD_LEFT);
		}
		else
		{
			$this->cdr->$port_type = '_Undef';
			$this->val_other[$port_type.'_key'] = 0;
			$this->val_other[$port_type.'_pref'] = $tp.'__';
		}
	}

	protected function port_is_outer($port_type)
	{
		$port_key = $this->val_other[$port_type.'_key'];
		if ($port_key > 0 AND $port_key < 10)
			return TRUE;
		return FALSE;
	}

	protected function skip_zero_dur()
	{
		// TODO описать процедуру
	}

	protected function skip_inner_calls()
	{
		if ( ! $this->port_is_outer('port_from') AND ! $this->port_is_outer('port_to'))
			$this->cdr->isSkipped(TRUE);
	}

	protected function combination_nums()
	{
		$a_num = [$this->val_other['port_from_pref'], $this->cdr->A];
		if ( ! empty($this->val_other['num_dn']))
		{
			$a_num[] = $this->val_other['num_dn'];
			if ($this->dn_in_a164)
				$this->cdr->A164 = implode($this->delim_nums, [$this->cdr->A164, $this->val_other['num_dn']]);
		}
		$b_num = [$this->val_other['port_to_pref'], $this->cdr->B];
		$this->cdr->A = implode($this->delim_nums, $a_num);
		$this->cdr->B = implode($this->delim_nums, $b_num);
	}
}