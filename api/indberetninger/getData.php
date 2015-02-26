<?php
header('Content-type: application/json');
session_start();
include "../../../docs/fileadmin/lib/lib.php";

$id = isset($_GET['tilmeldingsId']) ? $_GET['tilmeldingsId'] : $_SESSION['seih_loggedin'];

$indbSql = 'select dato, value,comment,refilldate,refillamount
			from `indberetninger`
			where `tilmeldingsid` = ' .$id . '
			order by dato';
#echo $indbSql;
$returndata = $DBH->query($indbSql);
$data = $returndata->fetchAll(PDO::FETCH_ASSOC);
$return = [];
$return['statusCode'] = 200;
foreach ($data as $indberetning) {
	$return['data']['ind'][($indberetning['dato'] * 1000)] = $indberetning['value'];
	if (strlen($indberetning['refillamount'])) {
		$return['data']['refill'][($indberetning['refilldate'] * 1000)] = $indberetning['refillamount'];
	}
}
echo json_encode($return);
?>