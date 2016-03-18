<?php defined('LIBROOT') or die('No direct script access.');

require LIBROOT.'vendor/Psr/Psr4AutoloaderClass.php';

$loader = new \Psr\Psr4AutoloaderClass;
$loader->register();
$loader->addNamespace('local',    LIBROOT.'local');
$loader->addNamespace('Psr',      LIBROOT.'vendor/Psr');
$loader->addNamespace('Katzgrau', LIBROOT.'vendor/Katzgrau');

//var_dump(spl_autoload_functions()); exit;