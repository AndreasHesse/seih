<?php

require_once('../ApiBaseClass.php');

/**
 * Class FjernvarmeDataAPI
 *
 */
class FjernvarmeDataAPI extends ApiBaseClass
{

	/**
	 *
	 */
	public function render()
	{

		$homeId = $this->getHomeId();
		if ($homeId === 0) {
			$this->renderError('HomeID must be set');
		}

		$metricNames = isset($_GET['metricNames']) ? $_GET['metricNames'] : '';
		if ($metricNames == '') {
			$this->renderError('Metricnames must be set');
		}

		$metricNames = explode(',', $metricNames);

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
		$bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);
		$interval = round(abs((($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints) / 3600));

		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y H:i'),
			'endTime' => $endTime->format('d/m-Y H:i'),
			'metrics' => $metricNames,
			'homeId' => $homeId,
			'numberOfPoints' => $numberOfPoints,
			'interval' => (int)$interval
		);
		$result['data'] = array();
		#var_dump($result);

		foreach ($metricNames as $metricName) {
			/*
						$hash = $this->calculateCacheHash(array(
							'dataset' => 'fjernvarme',
							'startTime' => $startTime->format('U'),
							'endTime' => $endTime->format('U'),
							'metricName' => $metricName,
							'homeId' => $homeId,
							'numberOfPoints' => $numberOfPoints
						));*/

			$sensorData = $this->getDataFromFullMongoDataset($startTime, $endTime, $homeId, $metricName, $interval);
			if ($numberOfPoints > 0) {
				$transformedData = $this->renormalizeTimestampKeysToMilliseconds($this->mapDataToBins($bins, $sensorData));
			} else {
				$transformedData = $this->renormalizeTimestampKeysToMilliseconds($sensorData);
			}
			$result['data'][$metricName] = $transformedData;
			$result['dataSource'][$metricName] = 'rawDataSet';

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
	 * @param string   $metricName
	 *
	 * @return array
	 */
	protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $homeid, $metricName, $interval)
	{
		$this->initMongoConnection();
		$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

		$query = array(
			'homeid' => intval($homeid),
			'date' => array(
				'$gte' => new MongoDate($startTime->format('U')),
				'$lt' => new MongoDate($endTime->format('U')),
			)
		);
		$res = $db->fjernvarme->find($query, array(
			'_id' => false,
			'homeid' => false,
		));
		$result = array();
		#var_dump(iterator_to_array($res));die;

		foreach ($res as $counter => $row) {
			if ($counter && $counter % $interval == 0) {
				$value = $row[$metricName];
				if (is_string($value)) {

					$value = str_replace(',', '.', $value);
				}
				$result[$row['date']->sec] = floatval($value);
			}
		}
		return array_unique($result, SORT_REGULAR);
	}

}

$API = new FjernvarmeDataAPI();
$API->render();