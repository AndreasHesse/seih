<?php
/**
 * Aggretate data from the huge "passiv" collection into more manageable hourly averages.
 * Using the MongoDB Aggregation pipeline, we calculate hourly averages for all combinations
 * of homeId and sensorname, and populate this data to MySQL where we can easily query it.
 * Since the MongoDB aggregation pipeline has a limit on 16Mb for the result of a pipeline,
 * you should not handle more than approx one days worth of data.
 *
 * @author: Jan-Erik Revsbech <janerik@moc.net>
 */

$configuration = require_once(__DIR__ . '/../conf/settings.php');

try {


        $dbh = new PDO(sprintf("mysql:host=%s;dbname=%s", $configuration['mysql']['hostname'], $configuration['mysql']['database']), $configuration['mysql']['username'], $configuration['mysql']['password']);
        $m = new MongoClient($configuration['mongo']['hostname']);
        $db = $m->selectDB($configuration['mongo']['database']);
        /*
                // Dates in Mongo are stores with UTC, so we create it like that.
                $sql = 'select max(date) as maxDate from daily';
                foreach ($dbh->query($sql) as $row) {
                        $mongoFra = DateTime::createFromFormat('!Y-m-d H:i:s', $row['maxDate'], new DateTimeZone('UTC'));
                }

        //	$mongoFra = DateTime::createFromFormat('!d/m/Y H:i', '30/03/2014 00:00', new DateTimeZone('UTC'));
        //	$mongoTil = DateTime::createFromFormat('!d/m/Y H:i', '31/01/2014 00:00', new DateTimeZone('UTC'));

                $mongoTil = clone($mongoFra);
                $mongoTil->add(new DateInterval('P7D'));
        */


        $mongoFra = strtotime("2013-12-01 00:00:00");
        $mongoTil = strtotime("2014-12-01 00:00:00");

        print "Aggregating data from " . date("Y-m-d H:i:s", $mongoFra) . ' to ' . date("Y-m-d H:i:s", $mongoTil) . PHP_EOL;
        $averageAggregation = array(
                array(
                        '$match' => array(
                                'sensor' => 'wm1a_ny',
                                'date' => array(
                                        '$gte' => new MongoDate($mongoFra),
                                        '$lt' => new MongoDate($mongoTil)
                                )
                        )
                ),
                array(
                        '$project' => array(
                                'homeId' => 1,
                                'sensor' => 1,
                                'date' => 1,
                                'year' => array('$year' => '$date'),
                                'month' => array('$month' => '$date'),
                                'day' => array('$dayOfMonth' => '$date'),
                                'val' => 1
                        )
                ),
                array(
                        '$group' => array(
                                '_id' => array(
                                        'sensor' => '$sensor',
                                        'homeId' => '$homeId',
                                        'day' => '$day',
                                        'month' => '$month',
                                        'year' => '$year'
                                ),
                                'points' => array('$sum' => 1),
                                'average' => array('$avg' => '$val'),
                                'date_sample' => array('$first' => '$date')
                        )
                ),
                array(
                        '$project' => array(
                                '_id' => 0,
                                'average' => 1,
                                'points' => 1,
                                'date_sample' => 1,
                                'homeId' => '$_id.homeId',
                                'day' => '$_id.day',
                                'month' => '$_id.month',
                                'year' => '$_id.year',
                                'sensor' => '$_id.sensor',
                        )
                )
        );
        $results = $db->command(
                array('aggregate' => 'passiv', 'pipeline' => $averageAggregation),
                array('timeout' => 600 * 1000) //Timeout in milliseconds
        );

        if ($results['ok'] == 1.00) {

                foreach ($results['result'] as $res) {
                        $data = array(
                                'homeID' => $res['homeId'],
                                'sensorName' => '"' . $res['sensor'] . '"',
                                'numberOfSamples' => $res['points'],
                                'averageValue' => $res['average'],
                                'date' => sprintf('"%4d-%02d-%02d 00:00"', $res['year'], $res['month'], $res['day'])
                        );
                        $query = 'REPLACE INTO daily (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', $data) . ')';
                        echo '.';
                        /*
                         die;
                        */
                        $dbh->exec($query);
                }
        }
} catch (Exception $e) {
        print "Error: " . $e->getMessage();
}
