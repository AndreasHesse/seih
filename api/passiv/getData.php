<?php

require_once('../ApiBaseClass.php');

class SensorDataAPI extends ApiBaseClass
{

	public function render()
	{

		$homeId = $this->getHomeId();
		if ($homeId === 0) {
			$this->renderError('HomeID must be set');
		}

		$sensorNames = isset($_GET['sensorNames']) ? $_GET['sensorNames'] : '';
		if ($sensorNames == '') {
			$this->renderError('Sensornames must be set');
		}

		$sensorNames = explode(',', $sensorNames);
		foreach ($sensorNames as $sensor) {
			$sensors[$this->sensorArray[$sensor]] = $sensor;
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

		$interval = round(abs((($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints) / 3600));

		$result = array(
			'statusCode' => 200,
			'startTime' => $startTime->format('d/m-Y'),
			'endTime' => $endTime->format('d/m-Y'),
			'sensors' => $sensors,
			'homeId' => $homeId,
			'numberOfPoints' => $numberOfPoints,
			'binSizeInSeconds' => $interval
		);


		$result['data'] = array();

		foreach ($sensorNames as $sensorName) {

			$hash = $this->calculateCacheHash(array(
				'dataset' => 'passiv',
				'startTime' => $startTime->format('U'),
				'endTime' => $endTime->format('U'),
				'sensorName' => $sensorName,
				'homeId' => $homeId,
				'numberOfPoints' => $numberOfPoints
			));

			if ($noCache == FALSE && $this->cache->exists($hash)) {
				$result['dataSource'] = 'cache';
				$result['hash'][$sensorName] = $hash;
				$result['data'][$sensorName] = json_decode($this->cache->get($hash), true);
			} else {
				$result['dataSource'] = 'rawDataSet';
				$result['data'] = array();
				$result['hash'][$sensorName] = $hash;
				$result['data'][$sensorName] = $this->getDataFromMongo($startTime, $endTime, $sensorName, $homeId, $interval);
				$this->cache->put($hash, json_encode($result['data'][$sensorName]));
			}
		}
		$rendertimeEnd = microtime(TRUE);
		$result['querytimeInSeconds'] = $rendertimeEnd - $rendertimeStart;
		print json_encode($result);
	}

	/**
	 * Since higcharts expects timestamp to be milliseconds, we multiply each key with thousand. We divide the value with
	 * 100 since, we have data in centi-celcius and would like to have it in degrees celcius
	 *
	 * @param $data
	 *
	 * @return array
	 */
	protected function transformData($data)
	{
		$transformedData = array();
		foreach ($data as $key => $value) {
			$transformedData[$key * 1000] = $value / 100;
		}
		return $transformedData;
	}


	/**
	 * Return data from full MongoDB set.
	 *
	 * @param DateTime $startTime
	 * @param DateTime $endTime
	 * @param string   $sensorName
	 * @param integer  $homeId
	 *
	 * @return array
	 */
	protected function getDataFromMongo(DateTime $startTime, DateTime $endTime, $sensorName, $homeId, $interval)
	{
		$query = array(
			'homeID' => $homeId,
			'sensorID' => $this->sensorArray[$sensorName],/*
			'day' => array(
				'$gte' => 20131229,
				'$lt' => 20140401
			)*/
			'dateTime' => array(
				'$gte' => new MongoDate($startTime->format('U')),
				'$lt' => new MongoDate($endTime->format('U'))
			)
		);

		$res = $this->passivCollection->find($query
			, $this->mongoConstraint
		);
		$result = array();

		foreach ($res as $counter => $row) {
			if ($counter && $counter % $interval == 0) {
				$result[$row['dateTime']->sec] = $row['avgValue'];
			}
		}
		return $result;
	}
}

$API = new SensorDataAPI();
$API->render();



