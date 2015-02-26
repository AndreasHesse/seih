<?php

$conf = array(
	'mongo' => array(
		'hostname' => 'mongodb://91.223.235.136:27017',
		"username" => "seih_u",
		"password" => "yzxjeumf41",
		'database' => 'seih'
	),
	'typo3_db' => array(
		'hostname' => 'localhost',
		'database' => 'seih',
		'username' => 'seih_u',
		'password' => '8zxt358eez'
	),
	'mysql' => array(
		'hostname' => 'localhost',
		'database' => 'aggregated_data',
		'username' => 'seih_u',
		'password' => '8zxt358eez'
	),
	'cache' => array(
		'ttl' => 86400,
		'cacheDir' => '/dana/data/seih.dk/docs/mithjem/cache'
	)
);

return $conf;