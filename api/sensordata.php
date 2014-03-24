<?php



class SensorDataAPI {

	/**
	 *
	 */
	public function __construct() {
		$this->configuration = require_once('../conf/settings.php');
	}

	/**
	 *
	 */
	public function render() {
		$homeId = intval($_GET['homeId']);
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

		$numberOfPoints = 10;
		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);


		$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);

		$result = array(
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'sensors' => $sensorNames,
			'homeId' => $homeId,
			//'test' => $this->findClosestValue(1380582001, $sensorData),
			//'bins' => $bins,
			//'data' => $sensorData,
			'numberOfPoints' => $numberOfPoints,
		);
		$result['data'] = array();
		foreach ($sensorNames as $sensorName) {
			$sensorData = $this->getDataFromStorage($startTime, $endTime, array($sensorName), array($homeId));
			$result['data'][$sensorName] = $this->transformData($this->mapDataToBins($bins, $sensorData));
		}
		header('Content-type: application/json');
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
	 * Map one dataset ($data) into a certain binsize. Will use the nearest neighbour principle to find the value
	 * of the dataset in the points specified bin $bins. Return an array where the keys are the bins, and the value the
	 * value approximated in that point
	 *
	 * @param array $bins
	 * @param array $data
	 * @return array
	 */
	protected function mapDataToBins($bins, $sensorData) {
		$data = array();
		foreach ($bins as $binValue) {
			$data[$binValue] = $this->findClosestValue($binValue, $sensorData);
		}
		return $data;
	}

	/**
	 * Given a dataset and a desired point, return the value in that point by finding the closest point available.
	 * Uses a simple nearest-neighbour method
	 *
	 * @param $value
	 * @param $data
	 */
	protected function findClosestValue($evaluationPoint, $data) {
			// I think this is not the ideal way, but it works
		$closestValue = 0;
		$minDistance = 10000;
		foreach ($data as $key => $dataValue) {
			$distance = abs($key - $evaluationPoint);
			if ($distance < $minDistance) {
				$minDistance = $distance;
				$closestValue = $dataValue;
			}
		}
		return $closestValue;
	}

	/**
	 * Calculate qually distanced bins
	 *
	 * Returns an array of equally distributed "bins" from starttime to endtime with
	 * the desired number of bins.
	 *
	 * Returns an array where each value is the first second of each bin.
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param integer $numberOfBins
	 */
	protected function calculateBins(DateTime $startTime, DateTime $endTime, $numberOfBins) {
		$startSecond = intval($startTime->format('U'));
		$endSecond = intval($endTime->format('U'));
		$intervalInSeconds = abs($endSecond - $startSecond);
		$binSizeInSeconds = $intervalInSeconds / $numberOfBins;
		$bins = array();
		for ($i = 0; $i < $numberOfBins; $i++) {
			$bins[] = $startSecond + $i * $binSizeInSeconds;
		}
		return $bins;
	}

	/**
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param array $sensorNames
	 * @param array $homeIds
	 * @return array
	 */
	protected function getDataFromStorage(DateTime $startTime, DateTime $endTime, $sensorNames, $homeIds) {

		$dbh = new PDO(sprintf("mysql:host=%s;dbname=%s", $this->configuration['mysql']['hostname'], $this->configuration['mysql']['database']), $this->configuration['mysql']['username'], $this->configuration['mysql']['password']);
		foreach ($sensorNames as $key => $sensorName) {
			$sensorNames[$key] = sprintf('"%s"', $sensorName);
		}
		$query = sprintf('SELECT UNIX_TIMESTAMP(date) as timestamp, averageValue from hourly WHERE homeid IN (%s) and sensorName IN (%s) AND date > "%s" AND date < "%s" ORDER BY date ASC', implode(',', $homeIds), implode(',', $sensorNames), $startTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s'));
		$data = array();
		foreach ($dbh->query($query) as $row) {
			$data[$row['timestamp']] = $row['averageValue'];
		}
		return $data;
	}

	protected function renderError($message) {
		print "Error: " . $message;
		exit();
	}
}

$API = new SensorDataAPI();
$API->render();



