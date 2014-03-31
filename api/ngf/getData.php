<?php

require_once('../ApiBaseClass.php');

/**
 * Class NGFDataAPI
 *
 */
class NGFDataAPI extends ApiBaseClass {

	/**
	 *
	 */
	public function render() {

		$homeId = $this->getHomeId();
		if ($homeId === 0) {
			$this->renderError('HomeID must be set');
		}

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
			'homeId' => $homeId,
			'numberOfPoints' => $numberOfPoints,
		);
		$result['data'] = array();


		$sensorData = $this->getDataFromFullMongoDataset($startTime, $endTime, $homeId);
		if ($numberOfPoints > 0) {
			$result['data']['val'] = $this->renormalizeTimestampKeysToMilliseconds($this->mapDataToBins($bins, $sensorData));
		} else {
			$result['data']['val'] = $this->renormalizeTimestampKeysToMilliseconds($sensorData);
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
	 * @param integer $stationId
	 * @return array
	 */
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $homeid) {
		$this->initMongoConnection();
		$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

		$query = array (
			'home' => intval($homeid),
			'date' => array(
				'$gte' => new MongoDate($startTime->format('U')),
				'$lt' => new MongoDate($endTime->format('U')),
			)
		);
		$res = $db->ngf->find($query);
		$result = array();
		foreach($res as $row) {
			$result[$row['date']->sec] = $row['val'];
		}
		return $result;
	}

}

$API = new NGFDataAPI();
$API->render();