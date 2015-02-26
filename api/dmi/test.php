<?php
error_reporting(-1);
require_once('../ApiBaseClass.php');

class SensorDataAPI extends ApiBaseClass
{

	public function render()
	{


		$query = array(
			'homeID' => 39235,
			'sensorID' => 13,
			'dateTime' => array(
				'$gte' => new MongoDate(1388361587),//new MongoDate(strtotime("2013-07-28 00:00:00")), //
				'$lt' => new MongoDate(1398981599),//new MongoDate(strtotime("2013-07-30 00:00:00")),//
			)/*
			'day' => array(
				'$gte' => 20131229,
				'$lt' => 20140401
			)*/
		);

		$constraint = array(
			'_id' => FALSE,
			'samples' => FALSE,
			'homeID' => FALSE,
			'sensorID' => FALSE,
		);

		$cursor = $this->passivCollection
			->find($query, $constraint);

		foreach ($cursor as $row) {
			#var_dump($row);
			$result[$row['dateTime']->sec] = $row['avgValue'];
		}
		echo json_encode($result);
		#return $result;
	}
}

$API = new SensorDataAPI();
$API->render();



