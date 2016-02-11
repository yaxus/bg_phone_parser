<?php return array(
	// process
	'processed_files' => array(
		'file_name'     => 'processed_files.php', // pipe file
		'count_history' => 100,
	),
	'timezone'      => 'Europe/Moscow', // time zone
	'period_try'    => 7,               // try download days 
	'check_pipe'    => TRUE,            // skip day if is olready processed
	'log_dir'       => 'logs',
	'log_level'     => 'info',          // 'debug', 'info', 'warning', 'error', etc. (PSR-3)
	'date_frmt'     => 'Y-m-d',

	// object
	'source_id'     => 6,
	'pass_zero_dur' => TRUE,
	'conv_dir'      => '/usr/ftpuni/converted/',
	'stream_socket' => 'tcp://0.0.0.0:9033',

	'sources' => array(
		array(
			'obj_cdr'       => 'local\CDRRTU',
			'raw_dir'       => '/usr/ftprtu/',
			'pattern_files' => '\r\t\uYmd\*',   // converted with date()
			'count_raw'     => array(13,15),
			'delimiter'     => ';',
			'indexes'       => array(
				'datet'      => 9,
				'duration'   => 7,
				'A'          => 5,
				'A164'       => NULL,
				'B'          => 6,
				'B164'       => NULL,
				'port_from'  => 1,
				'port_to'    => 2,
				'category'   => NULL,
				'dur_oper'   => NULL,
				'coast'      => NULL,
			),
		),
		// ... other sources
	),
);