<?php

require_once('../ApiBaseClass.php');

class AverageAPI extends ApiBaseClass {

	public function render() {
		$homeId = $this->getHomeId();
		if ($homeId === 0) {
			$this->renderError('HomeID must be set');
		}

		$sensorName = ($_GET['sensorName']);
		if ($sensorName == '') {
			$this->renderError('Sensorname must be set');
		}

		$startHour = intval($_GET['startHour']);
		$endHour = intval($_GET['endHour']);

		if($startHour > 24 || $endHour > 24 || $startHour < 0 || $endHour < 0) {
			$this->renderError('Start and end hour must be between 0 and 24');
		}

		$startTimestamp = intval($_GET['startTimestamp']);
		$endTimestamp = intval($_GET['endTimestamp']);

		if($startTimestamp === 0 || $endTimestamp === 0) {
			$this->renderError('Start or stop timestamp not correctly set');
		}

		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);

		$hourConstraint = '1=1';
		if ( $startHour <= $endHour) {
			$hourConstraint = sprintf('hour >= %d AND hour < %d', $startHour, $endHour);
		} else {
			$hourConstraint = sprintf('(hour >= %d OR hour < %d)', $startHour, $endHour);
		}
		try {

			$query = sprintf('SELECT AVG(averageValue) as average, STDDEV(averageValue) as stdDeviation from hourly where homeId=%d and sensorName="%s" AND %s AND date > "%s" AND date < "%s"', $homeId, $sensorName, $hourConstraint, $startTime->format('Y-m-d H:i'), $endTime->format('Y-m-d H:i'));
			$result = array(
				'statusCode' => 200,
				'startHour' => $startHour,
				'endHour' => $endHour,
				'startTime' => $startTime->format('d/m-Y H:i'),
				'endTime' => $endTime->format('d/m-Y H:i'),
				'sensorName' => $sensorName,
				'homeId' => $homeId
			);
			foreach ($this->dbHandle->query($query) as $row) {
				$result['average'] = floatval($row['average']);
				$result['standardDeviation'] = floatval($row['stdDeviation']);
			}
			print json_encode($result);
		} catch (Exception $e) {
			$this->renderError('Unable to connect to database');
		}


	}
}

$API = new AverageAPI();
$API->render();