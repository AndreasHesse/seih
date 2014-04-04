<?php


abstract class ApiBaseClass {

	/**
	 * @var array|mixed
	 */
	protected $configuration = array();

	/**
	 * @var PDO
	 */
	protected $dbHandle;

	/**
	 * @var MongoClient
	 */
	protected $mongoHandle;

	/**
	 * @var array
	 */
	protected $knownIpAddresses = array('127.0.0.1', '109.202.148.90');

	/**
	 *
	 */
	public function __construct() {
		$this->configuration = require_once('../../conf/settings.php');
		$this->initDatabaseConnection();
		header('Content-type: application/json');
	}

	/**
	 * Init session data based on the session ID. Code is copied from lib.php in Seih main project.
	 */
	public function getHomeIdFromDbAndSession() {
		return intval($this->getFeUsersValuesFromDbAndSession('homeid'));
	}

	/**
	 * @return mixed
	 */
	public function getNgnHomeFromDbAndSession() {
		return intval($this->getFeUsersValuesFromDbAndSession('ng_maalested'));
	}

	/**
	 * @param $field The fieldname in the fe_users db to return;
	 * @return mixed
	 */
	protected function getFeUsersValuesFromDbAndSession($field) {
		session_start();
		$typo3Db = new PDO(sprintf("mysql:host=%s;dbname=%s", $this->configuration['typo3_db']['hostname'], $this->configuration['typo3_db']['database']), $this->configuration['typo3_db']['username'], $this->configuration['typo3_db']['password']);
		$id = intval($_SESSION['seih_loggedin']);
		$frontendUserSql = sprintf('SELECT %s FROM fe_users WHERE tilmeldingsid=%d', $field, $id);

		$res = $typo3Db->query($frontendUserSql);
		$user = $res->fetch(PDO::FETCH_ASSOC);
		if ($user) {
			return $user[$field];
		}
		return;
	}

	/**
	 * Find the needed homeID. If IP is known, tage from GET variable, otherwise look into session.
	 *
	 * Returns 0 if no user was found.
	 *
	 * @return integer
	 */
	public function getHomeId() {
		if (intval($_GET['homeId']) > 0 && in_array($_SERVER['REMOTE_ADDR'], $this->knownIpAddresses)) {
			return intval($_GET['homeId']);
		} else {
			return $this->getHomeIdFromDbAndSession();
		}
	}

	/**
	 *
	 */
	public function getNgfHome() {
		if (intval($_GET['ngf_home']) > 0 && in_array($_SERVER['REMOTE_ADDR'], $this->knownIpAddresses)) {
			return intval($_GET['ngf_home']);
		} else {
			return $this->getNgnHomeFromDbAndSession();
		}
	}



	/**
	 *
	 */
	public function initMongoConnection() {
		if ($this->mongoHandle === NULL) {
			$this->mongoHandle = new MongoClient($this->configuration['mongo']['hostname']);

		}
	}

	/**
	 *
	 */
	public function initDatabaseConnection() {
		if ($this->dbHandle === NULL) {
			$this->dbHandle = new PDO(sprintf("mysql:host=%s;dbname=%s", $this->configuration['mysql']['hostname'], $this->configuration['mysql']['database']), $this->configuration['mysql']['username'], $this->configuration['mysql']['password']);
		}
	}

	/**
	 * @param $message
	 */
	protected function renderError($message, $statusCode = 500) {
		header(sprintf('HTTP/1.0 %d %s', $statusCode, $message));
		$data = array(
			'statusCode' => $statusCode,
			'errorMessage' => $message
		);
		print json_encode($data);
		exit();
	}

	/**
	 * Calculate equally distanced bins
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
			$data[$binValue] = $this->interpolate($binValue, $sensorData);
		}
		return $data;
	}

	/**
	 * Given a evaluation point and a discrete (with ordered keys) dataset,
	 * return the approximate value at the binValue. If the dataset is numeric, it uses
	 * simple interpolation, otherwise, it chooses the nearest neighbour. It checks the first element of $sensorData
	 *
	 * @param $evaluationPoint
	 * @param $sensorData
	 */
	protected function evaluateAtPoint($evaluationPoint, $sensorData) {
		if (count($sensorData) == 0) {
			return 0;
		}
		if (is_numeric(current($sensorData))) {
			return $this->interpolate($evaluationPoint, $sensorData);
		}
		return $this->evaluateAtNearestNeighbour($evaluationPoint, $sensorData);
	}

	/**
	 * @param $binValue
	 * @param $sensorData
	 */
	protected function evaluateAtNearestNeighbour($evaluationPoint, $sensorData) {
		$neighbours = $this->findNeighbours($evaluationPoint, array_keys($data));
		if (count($neighbours) !== 2) {
			// Return 0 since, we have no points to actually interpolate
			return 0;
		}
		$distanceOne = abs($neighbours[0] - $evaluationPoint);
		$distanceTwo = abs($neighbours[1] - $evaluationPoint);
		return $distanceOne <= $distanceTwo ? $neighbours[0] : $neighbours[1];
	}

	/**
	 * Given an evaluation point and a dataset (order with keys in numerical order), interpolate the value in the evaluation-
	 * point using inverse distance weighting.
	 *
	 * @param float $evaluationPoint
	 * @param array $data Data must be ordere with eys in numerical order ascending.
	 * @return float
	 * @throws Exception
	 */
	protected function interpolate($evaluationPoint, $data) {
		$neighbours = $this->findNeighbours($evaluationPoint, array_keys($data));
		if (count($neighbours) !== 2) {
			// Return 0 since, we have no points to actually interpolate
			return 0;
		}
		$valueOne = $data[$neighbours[0]];
		$valueTwo = $data[$neighbours[1]];

		$distanceOne = abs($neighbours[0] - $evaluationPoint);
		$distanceTwo = abs($neighbours[1] - $evaluationPoint);
		$totalDistance = abs($neighbours[1] - $neighbours[0]);
		if ($totalDistance == 0) {
			return $data[$neighbours[0]];
		}
		return $valueOne * (( 1 - ($distanceOne/$totalDistance))) + $valueTwo * ( 1 - ($distanceTwo/$totalDistance));

	}

	/**
	 * Given an ordered dataset (the keys of the dataset are in numerical ascending order), find the two points to either
	 * side of the given evaluation point.
	 *
	 * @param $evaluationPoint
	 * @param $data
	 */
	protected function findNeighbours($evaluationPoint, $orderedData) {
		if (count($orderedData) === 0) {
			return array();
		}
		if ($evaluationPoint >= end($orderedData)) {
			return array(end($orderedData), end($orderedData));
		}

		$count = 0;
		foreach ($orderedData as $dataValue) {
			$distance = ($dataValue - $evaluationPoint);
			if ($distance > 0) {

				// We crossed the point, return this and previous point
				if ($count == 0) {
					$previousPoint = $orderedData[$count];
				} else {
					$previousPoint = $orderedData[$count - 1];
				}
				$next = $orderedData[$count];
				return array($previousPoint, $next);
			}
			$count++;
		}

	}

	/**
	 * Since higcharts expects timestamp to be milliseconds, we multiply each key with thousand.
	 *
	 * @param $data
	 * @return array
	 */
	protected function renormalizeTimestampKeysToMilliseconds($data) {
		$transformedData = array();
		foreach ($data as $key => $value) {
			$transformedData[$key * 1000] = $value;
		}
		return $transformedData;
	}


	/**
	 * @return mixed
	 */
	abstract function render();

}

