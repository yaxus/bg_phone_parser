<?php

namespace local;

use local\CDRConverter\Parser;
use local\CDRConverter\Cnf;
use local\CDRConverter\Log;

/**
 * Инициирование процесса обработки CDR из коммандной строки.
 * Файл должен быть подключен с помощью конструкции include/require.
 *
 * @link       https://github.com/yaxus/bg_phone_parser
 * @author     Yakushev Ilya <m@il3.ru>
 */

// load environment
define('DOCROOT', realpath(dirname($_SERVER['SCRIPT_NAME'])).DIRECTORY_SEPARATOR);
define('CONFPATH', DOCROOT.'conf.php');
define('LIBROOT', realpath(DOCROOT.'..').DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR);
require_once LIBROOT.'Autoloader.php';

if (realpath($_SERVER['SCRIPT_NAME']) === realpath(__FILE__))
{
	echo 'include '.realpath($_SERVER['SCRIPT_NAME']).' from another file'.PHP_EOL;
	echo "example: <?php require '{$_SERVER['SCRIPT_NAME']}';".PHP_EOL;
	return;
}
elseif ( ! file_exists(CONFPATH))
{
	echo 'create file '.CONFPATH.' and set it up'.PHP_EOL;
	return;
}

// Set Config object
Cnf::init(CONFPATH);

// Set Log object
// Katzgrau\KLogger (PSR-3)
Log::init(DOCROOT.Cnf::get('log_dir'), Cnf::get('log_level'));

// Set Timezone
$tz = Cnf::get('timezone');
if ( ! empty($tz))
	date_default_timezone_set(Cnf::get('timezone'));
else
{
	echo 'set the timezone at the config file: '.CONFPATH.PHP_EOL;
	return;
}

$o          = getopt('y:m:d:c:t::h::');
$time_today = mktime(0,0,0);

// execute from cmd
if (isset($o['h']))
{   // help
	echo implode(PHP_EOL, [
		'use:',
		'<-y YYYY> - Year;',
		'<-m MM>   - Month;',
		'[-d DD]   - Day;',
		'[-c CC]   - Count days;',
		'[-t]      - Today (if isset, other values not used);',
		'[-h]      - This help.',
	]).PHP_EOL;
	return;
}
elseif(isset($o['t']))
{   // today
	$parser = new Parser(Cnf::get());
	$parser->treat(date(Cnf::get('date_frmt'), $time_today));
	return;
}
elseif ( ! empty($o['y']) AND ! empty($o['m']))
{   // set period or date
	$check_pipe = FALSE;
	$day        = ( ! empty($o['d'])) ? $o['d'] : 1;
	$time_start = mktime(0,0,0,$o['m'],$day,$o['y']);
	if ($time_start >= $time_today)
		return;
	$period_try = ( ! empty($o['c'])) 
		? $o['c']
		: (( ! empty($o['d'])) 
			? 1
			: date('t', $time_start));
	if (($time_start + $period_try*86400) >= $time_today)
		$period_try = ($time_today - $time_start) / 86400;
}
// auto execute
else
{
	$check_pipe = Cnf::get('check_pipe');
	$period_try = Cnf::get('period_try');
	$time_start = $time_today-$period_try*86400;
}

$y = date('Y', $time_start);
$m = date('n', $time_start);
$d = date('j', $time_start)+$period_try;

// set PipeFile object
$processed_pipe = new PipeFile(DOCROOT.Cnf::get('processed_files')['file_name']);
$processed_pipe->set_quantity_rows(Cnf::get('processed_files')['count_history']);
$pipe_data = $processed_pipe->get_data();

for ($i = $period_try; $i >= 1; $i--)
{
	$date   = date('Y-m-d', mktime(0,0,0, $m, $d-$i, $y));
	$parser = new Parser((array) Cnf::get());
	if (isset($check_pipe) AND $check_pipe != FALSE AND in_array($date, $pipe_data))
		continue;
	if ($parser->treat($date))
		$processed_pipe->add_data($date);
}