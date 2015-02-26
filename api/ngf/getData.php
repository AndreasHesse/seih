<?php

require_once('../ApiBaseClass.php');

/**
 * Class NGFDataAPI
 *
 */
class NGFDataAPI extends ApiBaseClass
{

	/**
	 *
	 */
	public function render()
	{

		$ngfHome = $this->getNgfHome();
		if ($ngfHome === 0) {
			$this->renderError('HomeID must be set');
		}

		$startTimestamp = intval($_GET['startTimestamp']);
		$endTimestamp = intval($_GET['endTimestamp']);

		if ($startTimestamp === 0 || $endTimestamp === 0) {
			$this->renderError('Start or stop timestamp not correctly set');
		}

		$noCache = (isset($_GET['noCache']) && intval($_GET['noCache']) === 1) ? TRUE : FALSE;
		$numberOfPoints = intval($_GET['numberOfPoints']);

		$startTime = DateTime::createFromFormat('U', $startTimestamp);
		$endTime = DateTime::createFromFormat('U', $endTimestamp);

		$rendertimeStart = microtime(TRUE);
		#$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);


		#$interval = round(abs(($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints));
		$interval = round(abs((($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints) / 3600));

		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'ngfHome' => $ngfHome,
			'numberOfPoints' => $numberOfPoints,
			'interval' => $interval,
		);
		$result['data'] = array();

		$hash = $this->calculateCacheHash(array(
			'dataset' => 'ngf',
			'startTime' => $startTime->format('U'),
			'endTime' => $endTime->format('U'),
			'ngfHome' => $ngfHome,
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
			$readyData = $this->getDataFromFullMongoDataset($startTime, $endTime, $ngfHome, $interval);
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
	 * @param integer  $stationId
	 *
	 * @return array
	 */
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $home, $interval)
	{
		$this->initMongoConnection();
		$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

		$query = array(
			'ng_maalested' => intval($home),
			'date' => array(
				'$gte' => new MongoDate($startTime->format('U')),
				'$lt' => new MongoDate($endTime->format('U')),
			)
		);
		$res = $db->ngf->find($query
			, $this->mongoConstraint);

		/*
		echo json_encode(iterator_to_array($res));
		die;


		}*/
		foreach ($res as $counter => $row) {
			if ($counter && $counter % $interval == 0) {
				$result[1000 * ($row['date']->sec)] = $row['val'];
			}
		}
		return array_unique($result);
	}

}

$API = new NGFDataAPI();
$API->render();