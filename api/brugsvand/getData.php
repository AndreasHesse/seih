<?php

require_once('../ApiBaseClass.php');

class BrugsvandDataAPI extends ApiBaseClass
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
                /*
                                $sensorNames = isset($_GET['sensorNames']) ? $_GET['sensorNames'] : '';
                                if ($sensorNames == '') {
                                        $this->renderError('Sensornames must be set');
                                }*/

                //$sensorNames = explode(',', $sensorNames);
                $sensorNames = ['wm1a_ny', 'ts2'];

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
                $bins = $this->calculateBins($startTime, $endTime, $numberOfPoints);
                $interval = abs($endTime->getTimestamp() - $startTime->getTimestamp()) / $numberOfPoints;
                $result = array(
                        'statusCode' => 200,
                        'startTime' => $startTime->format('d/m-Y H:i'),
                        'endTime' => $endTime->format('d/m-Y H:i'),
                        //'sensors' => $sensorNames,
                        'homeId' => $homeId,
                        'numberOfPoints' => $numberOfPoints,
                        'binSizeInSeconds' => $interval
                );
                $result['data'] = array();
                foreach ($sensorNames as $sensorName) {

                        $hash = $this->calculateCacheHash(array(
                                // 'dataset' => 'wm1a',
                                'startTime' => $startTime->format('U'),
                                'endTime' => $endTime->format('U'),
                                'sensorName' => $sensorName,
                                'homeId' => $homeId,
                                'numberOfPoints' => $numberOfPoints
                        ));
                        $result['hash'][$sensorName] = $hash;

                        if ($noCache == FALSE && $cachedResult = $this->findFromCache($hash)) {
                                $result['dataSource'][$sensorName] = 'cache';
                                $result['data'][$sensorName] = $cachedResult;
                        } else {
                                list($dataSource, $sensorData) = $this->getDataFromStorage($startTime, $endTime, $homeId, $sensorName, $interval);
                                $result['dataSource'][$sensorName] = $dataSource;

                                if ($numberOfPoints > 0) {
                                        $transformedData = $this->transformData($this->mapDataToBins($bins, $sensorData));
                                } else {
                                        $transformedData = $this->transformData($sensorData);
                                }
                                $result['data'][$sensorName] = $transformedData;
                                $this->writeToCache($hash, $transformedData);
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
         *
         * @param DateTime $startTime
         * @param DateTime $endTime
         * @param string   $sensorName
         * @param integer  $homeId
         * @param integer  $interval
         *
         * @return array
         */
        protected function getDataFromStorage(DateTime $startTime, DateTime $endTime, $homeId, $sensorName, $interval)
        {


                if ($interval > 86400) {
                        //Daily averages are just fine for this as the interval is bigger than a day
                        $data = $this->getDataFromMySQLDailyAverage($startTime, $endTime, $sensorName, $homeId);
                        //$this->writeToCache($hash, $data);
                        return array('dailyAverages', $data);
                }

                if ($interval > 3600) {
                        $data = $this->getDataFromMySQLHourlyAverage($startTime, $endTime, $sensorName, $homeId);
                        //$this->writeToCache($hash, $data);
                        return array('hourlyAverages', $data);
                }
                $data = $this->getDataFromFullMongoDataset($startTime, $endTime, $sensorName, $homeId);
                //$this->writeToCache($hash, $data);
                return array('rawDataSet', $data);

        }

        /**
         * Return data from hourly aggregated data.
         *
         * @param DateTime $startTime
         * @param DateTime $endTime
         * @param string   $sensorName
         * @param integer  $homeId
         *
         * @return array
         */
        protected function getDataFromMySQLHourlyAverage(DateTime $startTime, DateTime $endTime, $sensorName, $homeId)
        {
                $query = sprintf('SELECT UNIX_TIMESTAMP(date) as timestamp, averageValue from hourly WHERE homeid = %s and sensorName = "%s" AND date > "%s" AND date < "%s" ORDER BY date ASC', $homeId, $sensorName, $startTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s'));
                $data = array();
                foreach ($this->dbHandle->query($query) as $row) {
                        $data[$row['timestamp']] = $row['averageValue'];
                }
                return $data;
        }

        /**
         * Return data from daily aggregated data.
         *
         * @param DateTime $startTime
         * @param DateTime $endTime
         * @param string   $sensorName
         * @param integer  $homeId
         *
         * @return array
         */
        protected function getDataFromMySQLDailyAverage(DateTime $startTime, DateTime $endTime, $sensorName, $homeId)
        {
                $query = sprintf('SELECT UNIX_TIMESTAMP(date) as timestamp, averageValue from daily WHERE homeid = %s and sensorName = "%s" AND date > "%s" AND date < "%s" ORDER BY date ASC', $homeId, $sensorName, $startTime->format('Y-m-d H:i:s'), $endTime->format('Y-m-d H:i:s'));
                $data = array();
                foreach ($this->dbHandle->query($query) as $row) {
                        $data[$row['timestamp']] = $row['averageValue'];
                }
                return $data;
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
        protected function getDataFromFullMongoDataset(DateTime $startTime, DateTime $endTime, $sensorName, $homeId)
        {
                $this->initMongoConnection();
                $db = $this->mongoHandle->selectDB($this->configuration['mongo']['database']);

                $query = array(
                        'homeId' => $homeId,
                        'sensor' => $sensorName,
                        'date' => array(
                                '$gte' => new MongoDate($startTime->format('U')),//new MongoDate(strtotime("2013-07-28 00:00:00")), //
                                '$lt' => new MongoDate($endTime->format('U')),//new MongoDate(strtotime("2013-07-30 00:00:00")),//
                        )
                );
                $res = $db->passiv->find($query);
                $result = array();
                foreach ($res as $row) {
                        $result[$row['date']->sec] = $row['val'];
                }
                return $result;
        }
}

$API = new BrugsvandDataAPI();
$API->render();



