<?php

$conf = array(
		'mongo' => array(
				'hostname' => 'mongodb://localhost:27017',
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
		)
);

return $conf;