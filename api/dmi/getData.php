<?php

require_once('../ApiBaseClass.php');

/**
 * Class DMIDataAPI
 *
 * ex. http://seih.local/seih/api/dmi/getData.php?startTimestamp=1389744000&endTimestamp=1390089600&stationId=06102&sensorNames=te,dp&numberOfPoints=10
 */
class DMIDataAPI extends ApiBaseClass
{

		/**
		 *
		 */
		public function render()
		{

				$stationId = $_GET['stationId'];
				if ($stationId == '') {
						$this->renderError('stationId must be set');
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
				$result = array(
						'statusCode' => 200,
						'startTime' => $startTime->format('d/m-Y H:i'),
						'endTime' => $endTime->format('d/m-Y H:i'),
						'metrics' => $metricNames,
						'stationId' => $stationId,
						'numberOfPoints' => $numberOfPoints,
						'ip' => $_SERVER['SERVER_ADDR'],
				);

				foreach ($metricNames as $metricName) {

						$hash = $this->calculateCacheHash(array(
								'dataset' => 'dmi',
								'startTime' => $startTime->format('U'),
								'endTime' => $endTime->format('U'),
								'metricName' => $metricName,
								'stationId' => $stationId,
								'numberOfPoints' => $numberOfPoints
						));
						if ($noCache == FALSE && $cachedResult = $this->findFromCache($hash)) {
								$result['dataSource'][$metricName] = 'cache';
								$result['data'][$metricName] = $cachedResult;
						} else {
								$sensorData = $this->getDataFromFullMongoDataset($startTime, $endTime, $stationId, $metricName);
								if ($numberOfPoints > 0) {
										$transformedData = $this->renormalizeTimestampKeysToMilliseconds($this->mapDataToBins($bins, $sensorData));
								} else {
										$transformedData = $this->renormalizeTimestampKeysToMilliseconds($sensorData);
								}
								$result['data'][$metricName] = $transformedData;
								$result['dataSource'][$metricName] = 'rawDataSet';
								$this->writeToCache($hash, $transformedData);
						}
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
		 * @param string   $stationId
		 * @param string   $metricName
		 *
		 * @return array
		 */
		protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $stationId, $metricName)
		{
				$this->initMongoConnection();
				$db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

				$query = array(
						'st' => (string)$stationId,
						'date' => array(
								'$gte' => new MongoDate($startTime->format('U')),
								'$lt' => new MongoDate($endTime->format('U')),
						)
				);
				#echo json_encode($query);die;
				$res = $db->selectCollection('dmi')->find($query);
				#var_dump($res);

				$result = array();
				foreach ($res as $row) {
						if ($metricName == 'te') {
								$value = $row['tp'] == '-' ? -1 * floatval($row['te']) : floatval($row['te']);
						} else {
								$value = $row[$metricName];
						}
						$result[$row['date']->sec] = $value;
				}
				return $result;
		}
}

$API = new DMIDataAPI();
$API->render();