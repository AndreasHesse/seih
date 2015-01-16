<?php
header('Content-type: application/json');
session_start();
include "../../../fileadmin/lib/lib.php";
$indbSql = 'select dato, value,comment,refilldate,refillamount
			from `indberetninger`
			where `tilmeldingsid` = ' . $_SESSION['seih_loggedin'] . '
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