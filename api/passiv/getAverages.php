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

		$noCache = (isset($_GET['noCache']) && intval($_GET['noCache'])=== 1) ? TRUE : FALSE;
		$rendertimeStart = microtime(TRUE);
		try {

			$hash = $this->calculateCacheHash(array(
				'dataset' => 'passivAverage',
				'startTime' => $startTime->format('U'),
				'endTime' => $endTime->format('U'),
				'homeID' => $homeId,
				'sensorName' => $sensorName,
				'startHour' => $startHour,
				'endHour' => $endHour
			));
			if ($noCache == FALSE && $cachedResult = $this->findFromCache($hash)) {
				$source = 'cache';
				$average = $cachedResult;
			} else {
				list($source, $average) = $this->getAverageFromMySQLHourlyAverage($homeId, $sensorName, $startTime, $endTime, $startHour, $endHour);
				$this->writeToCache($hash, $average);
			}

			$result = array(
				'statusCode' => 200,
				'startHour' => $startHour,
				'endHour' => $endHour,
				'startTime' => $startTime->format('d/m-Y H:i'),
				'endTime' => $endTime->format('d/m-Y H:i'),
				'sensorName' => $sensorName,
				'homeId' => $homeId,
				'source' => $source,
				'average' => $average
			);


			$rendertimeEnd = microtime(TRUE);
			$result['querytimeInSeconds'] = $rendertimeEnd - $rendertimeStart;

			print json_encode($result);
		} catch (Exception $e) {
			$this->renderError('Unable to connect to database');
		}
	}

	/**
	 * @param $homeId
	 * @param $sensorName
	 * @param $startTime
	 * @param $endTime
	 * @param $startHour
	 * @param $endHour
	 */
	protected function getAverageFromMySQLHourlyAverage($homeId, $sensorName, $startTime, $endTime, $startHour, $endHour) {
		$hourConstraint = '';
		if ( $startHour <= $endHour) {
			$hourConstraint = sprintf('hour >= %d AND hour < %d', $startHour, $endHour);
		} else {
			$hourConstraint = sprintf('(hour >= %d OR hour < %d)', $startHour, $endHour);
		}
		$query = sprintf('SELECT AVG(averageValue) as average from hourly where homeId=%d and sensorName="%s" AND %s AND date > "%s" AND date < "%s"', $homeId, $sensorName, $hourConstraint, $startTime->format('Y-m-d H:i'), $endTime->format('Y-m-d H:i'));
		foreach ($this->dbHandle->query($query) as $row) {
			$average = floatval($row['average']);
		}
		return array('hourlyAverage', $average);
	}

}

$API = new AverageAPI();
$API->render();