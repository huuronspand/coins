<?php

require "../../config.php";

$ch = curl_init();
curl_setopt($ch,    CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
curl_setopt($ch, CURLOPT_URL, "https://api.coinmarketcap.com/v1/ticker/?limit=0");
$data = curl_exec($ch);
curl_close($ch);

$coindata = json_decode($data);
error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}

$deleteSql = "delete
from coinstats.coinstats
where timestamp between unix_timestamp(CURDATE()) and unix_timestamp(CURDATE())+24*60*60";
$db.exec($deleteSql);

foreach ($coindata as $coin)
{
    $coina = (array)$coin;
    $coin->timestamp = mktime(date("H"), 0, 0);
    $statement = $db->prepare("INSERT IGNORE INTO coinstats(timestamp,id,name,symbol,rank,price_usd,price_btc,24h_volume_usd,market_cap_usd,available_supply,total_supply,max_supply,percent_change_1h,percent_change_24h,percent_change_7d,last_updated)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    try {
        echo "-";
        $statement->execute(array($coin->timestamp,$coin->id,$coin->name,$coin->symbol,$coin->rank,$coin->price_usd,$coin->price_btc,$coina['24h_volume_usd'],$coin->market_cap_usd,$coin->available_supply,$coin->total_supply,$coin->max_supply,$coin->percent_change_1h,$coin->percent_change_24h,$coin->percent_change_7d,$coin->last_updated));
    } catch (Exception $e) {
        echo 'Insert into coinstats not working: ',  $e->getMessage(), "\n";
    }
}

/*
$stmt = $db->query('SELECT * FROM coinstats');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

var_dump($results);
*/
?>

