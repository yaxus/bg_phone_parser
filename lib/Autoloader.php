<?php defined('LIBROOT') or die('No direct script access.');
require LIBROOT.'vendor/SplClassLoader.php';
$localLoader = new SplClassLoader('local', LIBROOT);
$localLoader->register();
$vendorLoader = new SplClassLoader(NULL, LIBROOT.'vendor');
$vendorLoader->register();
