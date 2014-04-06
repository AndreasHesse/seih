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

		$ngfHome = $this->getNgfHome();
		if ($ngfHome === 0) {
			$this->renderError('HomeID must be set');
		}

		$startTimestamp = intval($_GET['startTimestamp']);
		$endTimestamp = intval($_GET['endTimestamp']);

		if($startTimestamp === 0 || $endTimestamp === 0) {
			$this->renderError('Start or stop timestamp not correctly set');
		}

		$noCache = (isset($_GET['noCache']) && intval($_GET['noCache'])=== 1) ? TRUE : FALSE;
		$numberOfPoints = intval($_GET['numberOfPoints']);

		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);

		$rendertimeStart = microtime(TRUE);
		$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);
		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'ngfHome' => $ngfHome,
			'numberOfPoints' => $numberOfPoints,
		);
		$result['data'] = array();

		$hash = $this->calculateCacheHash(array(
			'dataset' => 'ngf',
			'startTime' => $startTime->format('U'),
			'endTime' => $endTime->format('U'),
			'ngfHome' => $ngfHome,
			'numberOfPoints' => $numberOfPoints
		));

		if ($noCache == FALSE && $cachedResult = $this->findFromCache($hash)) {
			$result['dataSource']['val'] = 'cache';
			$result['data']['val'] = $cachedResult;
		} else {
			$sensorData = $this->getDataFromFullMongoDataset($startTime, $endTime, $ngfHome);
			if ($numberOfPoints > 0) {
				$transformedData = $this->renormalizeTimestampKeysToMilliseconds($this->mapDataToBins($bins, $sensorData));
			} else {
				$transformedData = $this->renormalizeTimestampKeysToMilliseconds($sensorData);
			}
			$result['data']['val'] = $transformedData;
			$result['dataSource']['val'] = 'rawDataSet';
			$this->writeToCache($hash, $transformedData);
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
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $home) {
		$this->initMongoConnection();
		$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

		$query = array (
			'home' => intval($home),
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