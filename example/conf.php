<?php return [
	// process
	'processed_files' => [
		'file_name'     => 'processed_files.php', // pipe file
		'count_history' => 100,
	],
	'timezone'      => 'Europe/Moscow', // time zone
	'period_try'    => 7,               // try download days 
	'check_pipe'    => TRUE,            // skip day if is olready processed
	'log_dir'       => 'logs',
	'log_level'     => 'info',          // 'debug', 'info', 'warning', 'error', etc. (PSR-3)
	'date_frmt'     => 'Y-m-d',

	// object
	'source_id'     => 1,
	'pass_zero_dur' => TRUE,			// pass CDR where duration = 0
	'conv_dir'      => '/usr/ftpuni/converted/',
	'stream_socket' => 'tcp://0.0.0.0:9033',

	'sources' => [
		[
			'obj_cdr'       => 'local\CDRRTU',
			'raw_dir'       => '/usr/ftprtu/',
			'pattern_files' => '\r\t\uYmd\*',   // converted with date()
			'count_raw'     => [13,15],
			'delimiter'     => ';',
			'indexes'       => [
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
			],
		],
		// ... other sources
	],
];