<?php

require_once('../ApiBaseClass.php');

class SensorDataAPI extends ApiBaseClass {

	/**
	 *
	 */
	public function render() {
		$homeId = $this->getHomeId();
		if ($homeId === 0) {
			$this->renderError('HomeID must be set');
		}

		$sensorNames = ($_GET['sensorNames']);
		if ($sensorNames == '') {
			$this->renderError('Sensornames must be set');
		}

		$sensorNames = explode(',', $sensorNames);

		$startTimestamp = intval($_GET['startTimestamp']);
		$endTimestamp = intval($_GET['endTimestamp']);

		if($startTimestamp === 0 || $endTimestamp === 0) {
			$this->renderError('Start or stop timestamp not correctly set');
		}

		$numberOfPoints = intval($_GET['numberOfPoints']);

		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);

		$rendertimeStart = microtime(TRUE);
		$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);
		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'sensors' => $sensorNames,
			'homeId' => $homeId,
			'numberOfPoints' => $numberOfPoints,
		);
		$result['data'] = array();
		foreach ($sensorNames as $sensorName) {
			$sensorData = $this->getDataFromStorage($startTime, $endTime, $sensorName, $homeId);
			if ($numberOfPoints > 0) {
				$result['data'][$sensorName] = $this->transformData($this->mapDataToBins($bins, $sensorData));
			} else {
				$result['data'][$sensorName] = $this->transformData($sensorData);
			}
		}
		$rendertimeEnd = microtime(TRUE);
		$result['querytimeInSeconds'] = $rendertimeEnd - $rendertimeStart;
		print json_encode($result);
	}

	/**
	 * Since higcharts expects timestamp to be milliseconds, we multiply each key with thousand. We divide the value with
	 * 100 since, we have data in centicelcius and would like to have it in degrees celcius
	 * @param $data
	 * @return array
	 */
	protected function transformData($data) {
		$transformedData = array();
		foreach ($data as $key => $value) {
			$transformedData[$key * 1000] = $value / 100;
		}
		return $transformedData;
	}

	/**
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param string $sensorName
	 * @param integer $homeId
	 * @return array
	 */
	protected function getDataFromStorage(DateTime $startTime, DateTime $endTime, $sensorName, $homeId) {
		//@todo: Determine how to fetch the data, from MySQL or Mongo depending on the interval needed
		return $this->getDataFromFullMongoDataset($startTime, $endTime, $sensorName, $homeId);

		return $this->getDataFromMySQLHourlyAverage($startTime, $endTime, $sensorName, $homeId);
	}

	/**
	 * Return data from hourly aggregated data.
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param string $sensorName
	 * @param integer $homeId
	 * @return array
	 */
	protected function getDataFromMySQLHourlyAverage(DateTime $startTime, DateTime $endTime, $sensorName, $homeId) {
		$query = sprintf('SELECT UNIX_TIMESTAMP(date) as timestamp, averageValue from hourly WHERE homeid = %s and sensorName = "%s" AND date > "%s" AND date < "%s" ORDER BY date ASC', $homeId, $sensorName, $startTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s'));
		$data = array();
		foreach ($this->dbHandle->query($query) as $row) {
			$data[$row['timestamp']] = $row['averageValue'];
		}
		return $data;
	}

	/**
	 * Return data from full MongoDB set.
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param string $sensorName
	 * @param integer $homeId
	 * @return array
	 */
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $sensorName, $homeId) {
		$this->initMongoConnection();
		$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

		$query = array (
			'homeId' => $homeId,
			'sensor' => $sensorName,
			'date' => array(
				'$gte' => new MongoDate($startTime->format('U')),//new MongoDate(strtotime("2013-07-28 00:00:00")), //
				'$lt' => new MongoDate($endTime->format('U')),//new MongoDate(strtotime("2013-07-30 00:00:00")),//
			)
		);
		$res = $db->passiv->find($query);
		$result = array();
		foreach($res as $row) {
			$result[$row['date']->sec] = $row['val'];
		}
		return $result;
	}
}

$API = new SensorDataAPI();
$API->render();



