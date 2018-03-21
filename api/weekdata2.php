<?php
header('Access-Control-Allow-Origin: *');
$server = $_SERVER['SERVER_NAME'];
require "../../config.php";

error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}


function getData()
    {
        global $db;
        $sql = "
select symbol, sum(perc_change) perc_change
from 
(
          SELECT 
        concat(year(FROM_UNIXTIME(timestamp)),'-',WEEK(FROM_UNIXTIME(timestamp))) week,
        coinstats.symbol,
            max(DAYOFWEEK(FROM_UNIXTIME(timestamp))) dayNr,
            SUM(ROUND(percent_change_24h, 2)) perc_change,
            COUNT(*) samples,
            AVG(24h_volume_usd) avg_24h_volume_usd ,
            AVG(market_cap_usd) avg_market_cap_usd
    FROM
        coinstats
    WHERE DAYNAME(FROM_UNIXTIME(timestamp)) IN ( 'Saturday', 'Sunday', 'Monday')
    AND 24h_volume_usd > 10000000 
    AND year(FROM_UNIXTIME(timestamp)) = '2018'
    AND SYMBOL in ('BTC',
'ETH',
'XRP',
'BCH',
'LTC',
'ADA',
'XLM',
'NEO',
'EOS',
'MIOTA',
'DASH',
'XEM',
'XMR',
'TRX',
'USDT',
'ETC',
'LSK',
'BCH')
    GROUP BY concat(year(FROM_UNIXTIME(timestamp)),'-',WEEK(FROM_UNIXTIME(timestamp))) , coinstats.symbol
    ORDER BY timestamp ,avg_market_cap_usd desc , coinstats.symbol
) tmp
GROUP BY symbol
               
                ";

        $result = $db->query($sql,PDO::FETCH_ASSOC);

        if($result !== false) {
            $coins = array();
            foreach($result as $row)
            {
                $coins[] =  $row;
            }
        }

        return $coins;
    }


echo "{\"success\":true,\"result\":" . json_encode(getData()) . "}";
