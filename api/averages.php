<?php


$homeId = intval($_GET['homeId']);
if ($homeId === 0) {
	renderError('HomeID must be set');
}

$sensorName = ($_GET['sensorName']);
if ($sensorName == '') {
	renderError('Sensorname must be set');
}

$startHour = intval($_GET['startHour']);
$endHour = intval($_GET['endHour']);

if($startHour > 24 || $endHour > 24 || $startHour < 0 || $endHour < 0) {
	renderError('Start and end hour must be between 0 and 24');
}

$startTimestamp = intval($_GET['startTimestamp']);
$endTimestamp = intval($_GET['endTimestamp']);

if($startTimestamp === 0 || $endTimestamp === 0) {
	renderError('Start or stop timestamp not correctly set');
}

$configuration = require_once('../conf/settings.php');

$startTime = DateTime::createFromFormat('U', $startTimestamp);
$endTime = DateTime::createFromFormat('U', $endTimestamp);
header('Content-type: application/json');

//explain  select avg(averageValue) from hourly where homeId=35600 and sensorName="z1t" AND (hour >= 22 OR hour < 6) ORDER BY date;
$hourConstraint = '1=1';
if ( $startHour <= $endHour) {
	$hourConstraint = sprintf('hour >= %d AND hour < %d', $startHour, $endHour);
} else {
	$hourConstraint = sprintf('(hour >= %d OR hour < %d)', $startHour, $endHour);
}
try {
	$dbh = new PDO(sprintf("mysql:host=%s;dbname=%s", $configuration['mysql']['hostname'], $configuration['mysql']['database']), $configuration['mysql']['username'], $configuration['mysql']['password']);
	$sql = sprintf('SELECT AVG(averageValue) as average, STDDEV(averageValue) as stdDeviation from hourly where homeId=%d and sensorName="%s" AND %s AND date > "%s" AND date < "%s"', $homeId, $sensorName, $hourConstraint, $startTime->format('Y-m-d H:i'), $endTime->format('Y-m-d H:i'));
	$result = array(
		'startHour' => $startHour,
		'endHour' => $endHour,
		'startTime' => $startTime->format('d/m-Y H:i'),
		'endTime' => $endTime->format('d/m-Y H:i'),
		'sensorName' => $sensorName,
		'homeId' => $homeId
	);
	foreach ($dbh->query($sql) as $row) {
		$result['average'] = floatval($row['average']);
		$result['standardDeviation'] = floatval($row['stdDeviation']);
	}
	print json_encode($result);
} catch (Exception $e) {
	renderError('Unable to connect to database');
}

function renderError($message) {
	print "Error: " . $message;
	exit();
}