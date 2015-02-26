<?php

require_once('../ApiBaseClass.php');

class BrugsvandDataAPI extends ApiBaseClass
{

	/**
	 *
	 */
	public function render()
	{
		/*$homeId = $this->getHomeId();
		if ($homeId === 0) {
			$this->renderError('HomeID must be set');
		}*/

		$aftageNr = isset($_GET['aftagenr']) ? $_GET['aftagenr'] : '';
		if ($aftageNr == '') {
			$this->renderError('Aftagenr must be set');
		}

		$startTimestamp = intval($_GET['startTimestamp']);
		$endTimestamp = intval($_GET['endTimestamp']);

		if ($startTimestamp === 0 || $endTimestamp === 0) {
			$this->renderError('Start or stop timestamp not correctly set');
		}

		$numberOfPoints = intval($_GET['numberOfPoints']);
		if ($numberOfPoints === 0) {
			$this->renderError('Number of points must be specified');
		}

		$noCache = (isset($_GET['noCache']) && intval($_GET['noCache']) === 1) ? TRUE : FALSE;

		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);

		$rendertimeStart = microtime(TRUE);
		//$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);
		#$interval = round(abs($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints);
		$interval = round(abs((($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints) / 3600));


		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'aftagenr' => $aftageNr,
			'numberOfPoints' => $numberOfPoints,
			'binSizeInSeconds' => $interval
		);

		$hash = $this->calculateCacheHash(array(
			'dataset' => 'naturgas',
			'startTime' => $startTime->format('U'),
			'endTime' => $endTime->format('U'),
			'aftagenr' => $aftageNr,
			'numberOfPoints' => $numberOfPoints
		));


		if ($noCache == FALSE && $this->cache->exists($hash)) {
			$result['dataSource'] = 'cache';
			$result['hash'] = $hash;
			$result['data'] = json_decode($this->cache->get($hash), true);
		} else {
			$result['dataSource'] = 'rawDataSet';
			$result['hash'] = $hash;
			$result['data'] = array();
			$readyData = $this->getDataFromFullMongoDataset($startTime, $endTime, $aftageNr, $interval);
			$this->cache->put($hash, json_encode($readyData));
			$result['data'] = $readyData;
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
	 * @param string   $aftageNr
	 *
	 * @return array
	 */
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $aftageNr, $interval)
	{
		$this->initMongoConnection();
		$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

		$query = array(
			'aftagenr' => $aftageNr,
			'date' => array(
				'$gte' => new MongoDate($startTime->format('U')),//new MongoDate(strtotime("2013-07-28 00:00:00")), //
				'$lt' => new MongoDate($endTime->format('U')),//new MongoDate(strtotime("2013-07-30 00:00:00")),//
			)
		);
		$res = $db->energifyn->find($query
			, $this->mongoConstraint
		);
		$result = array();

		foreach ($res as $counter => $row) {
			if ($counter && $counter % $interval == 0) {
				$result[1000 * ($row['date']->sec)] = $row['val'];
			}
		}
		return $result;
	}
}

$API = new BrugsvandDataAPI();
$API->render();



