<?php namespace local; defined('CONFPATH') or die('No direct script access.');

class CDRRTU8800 extends CDRConverter_Converter
{
	protected $conv_func  = array(
		'revers_nums',
		'skip_other',
		'num2e164',
	);

	protected $mtt_numbers = array(
		'78003332969',
		'88003332969',
		'78005550705',
		'88005550705',
	);

	protected function skip_other()
	{
		$a_num = $this->get('A');
		// $port_from = $this->get('port_from');
		if (substr($a_num, 0, 4) != '5555' AND    // Don`t from Rostelecom
		  ! in_array($a_num, $this->mtt_numbers)) // Don`t from MTT
		{
			$this->skip_cdr(TRUE);
		}
	}

	protected function revers_nums()
	{
		/** * * * * * * * * * * * * * * * * * * * * * * * *
		 *                      !!!                       *
		 * А и Б номера поменены местами, что бы биллинг  *
		 * мог тарифицировать вызов как исходящий         *
		 ** * * * * * * * * * * * * * * * * * * * * * * * */
		$a_num = $this->get('A');
		$this->set('A', $this->get('B'));
		$this->set('B', $a_num);
	}
}