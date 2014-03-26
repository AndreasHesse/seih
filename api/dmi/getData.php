<?php

require_once('../ApiBaseClass.php');

/**
 * Class DMIDataAPI
 *
 * ex. http://seih.local/seih/api/dmi/getData.php?startTimestamp=1389744000&endTimestamp=1390089600&stationId=06102&sensorNames=te,dp&numberOfPoints=10
 */
class DMIDataAPI extends ApiBaseClass {

	/**
	 *
	 */
	public function render() {

		$stationId = ($_GET['stationId']);
		if ($stationId === '') {
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
		if ($numberOfPoints < 1) {
			$this->renderError('Number of points must be set, and be larger than 1');
		}

		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);

		$rendertimeStart = microtime(TRUE);
		$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);
		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'sensors' => $sensorNames,
			'stationId' => $stationId,
			'numberOfPoints' => $numberOfPoints,
		);

		foreach ($sensorNames as $sensorName) {
			$sensorData = $this->getDataFromFullMongoDataset($startTime, $endTime, $stationId, $sensorName);
			$result['data'][$sensorName] = $this->renormalizeTimestampKeysToMilliseconds($this->mapDataToBins($bins, $sensorData));
		}

		$rendertimeEnd = microtime(TRUE);
		$result['querytimeInSeconds'] = $rendertimeEnd - $rendertimeStart;
		print json_encode($result);
	}

	/**
	 * Return data from full MongoDB set.
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param string $stationId
	 * @param string $sensorName
	 * @return array
	 */
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $stationId, $sensorName) {
		$m = new MongoClient('zeeman');
		$db = $m->selectDB('seih');
		$query = array (
			'st' => (string)$stationId,
			'date' => array(
				'$gte' => new MongoDate($startTime->format('U')),
				'$lt' => new MongoDate($endTime->format('U')),
			)
		);
		$res = $db->dmi->find($query);
		$result = array();
		foreach($res as $row) {
			$result[$row['date']->sec] = $row[$sensorName];
		}
		return $result;
	}
}

$API = new DMIDataAPI();
$API->render();