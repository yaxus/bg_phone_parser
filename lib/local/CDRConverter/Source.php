<?php

namespace local;

defined('CONFPATH') or die('No direct script access.');

class CDRConverter_Source
{
	private $conf_params   = [
		'obj_cdr',
		'raw_dir',
		'pattern_files',
		'count_raw',
		'delimiter',
		'indexes',
	];

	protected function set_params($arr)
	{
		$src = [];
		foreach ($this->source_params as $param)
		{
			if (empty($arr[$param]))
			{
				Log::instance()->error("Source parameter #{$key_source}: {$param} is not set.");
				$this->isset_err = TRUE;
			}
			else
				$src[$param] = $arr[$param];
		}
		$this->sources[$key_source] = $src;
	}
}