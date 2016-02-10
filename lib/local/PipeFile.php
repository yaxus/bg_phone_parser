<?php namespace local; defined('CONFPATH') or die('No direct script access.');



class PipeFile
{
	protected $data = array();
	protected $file_name;
	protected $quantity_rows = 0;
	protected $file_writable = FALSE;
	protected $data_changed = FALSE;

	public function __construct($file_name)
	{
		if ( ! file_exists($file_name))
		{
			if ( ! @fclose(@fopen($file_name,'x')))
				Log::instance()->critical("File '{$file_name}' can not be created.");
		}
		if( ! is_writable($file_name))
		{
			Log::instance()->critical("File '{$file_name}' is not writable.");
			return FALSE;
		}
		$this->file_writable = TRUE;
		$content = file_get_contents($file_name);
		$this->file_name = $file_name;
		if ($data = unserialize($content))
			$this->data = $data;
	}

	public function set_quantity_rows($num)
	{
		$this->quantity_rows = $num;
	}

	public function add_data($data_row)
	{
		$this->data[] = $data_row;
		$this->data_changed = TRUE;
	}

	public function get_data()
	{
		return $this->data;
	}

	public function __destruct()
	{
		if ( ! $this->file_writable OR ! $this->data_changed)
			return FALSE;
		$data_slice = array_slice($this->data, -$this->quantity_rows);
		$content = serialize($data_slice);
		file_put_contents($this->file_name, $content);
	}
}