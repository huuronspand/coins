<?php
echo $_SERVER['SERVER_NAME'];die;
$_GLOBALS['dbLocation'] =  '35.205.178.111';
$_GLOBALS['db'] = 'coinstats';
$_GLOBALS['dbUser'] = 'coins';
//$_GLOBALS['dbPass'] = 'N-Ho9CDhRGMUS4345';
$_GLOBALS['dbPass'] = 'qewdqwfe44fwwe4ffw4efw4';

error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}

$savings = 50000;
$savingsInCoins = 0;
$defaultBuyAmount = 5000;
$portfolioMaxLength = $savings / $defaultBuyAmount;
$topCoins = array();
$portfolio = array();
$simStartTimestamp = 1518876000;
$simDays = 10;
$oneDay = 24*60*60;

function buyCoin($coinCode,$coinInfo)
{
    global $portfolio,$savings,$savingsInCoins,$defaultBuyAmount;
    $portfolio[$coinCode] = $coinInfo;
    echo "buy " . $coinCode . "<br/>";
    $savings = $savings - $defaultBuyAmount;
    return $portfolio;
}
function sellCoin($coinCode,$coinInfo)
{
    global $portfolio,$savings,$savingsInCoins,$defaultBuyAmount;
    /* out of top 10, more than 100 rise ......? */
    unset($portfolio[$coinCode]);
    echo "sell " . $coinCode . ", changed " . $coinInfo["percent_change_24h"] .  "%, profit:".($coinInfo["percent_change_24h"]/100) * $defaultBuyAmount ."<br/>";
    $savings = $savings + (1 + ($coinInfo["percent_change_24h"]/100)) * $defaultBuyAmount;
    return $portfolio;
}

function getTopCoins($timestamp)
{
    global $db, $oneDay;
    $sql = "SELECT * FROM coinstats.coinstats
            WHERE market_cap_usd > 100000000
            and timestamp between unix_timestamp(Date(from_unixtime(".$timestamp."))) 
                            and  unix_timestamp(Date(from_unixtime(".($timestamp + $oneDay).")))
                            and  percent_change_24h > 0
            order by timestamp desc, percent_change_24h desc
            limit 20";
    $result = $db->query($sql);
    $topCoins = array();
    if($result !== false) {
        foreach($result as $row)
        {
            $topCoins[$row['id']] = $row;
        }
    }

    return $topCoins;
}







for ($t = 0;  $t < $simDays; $t++)
{

    echo "<hr/>Day " . $t . "<br/>";


    $timestamp = $simStartTimestamp + $t * $oneDay;
    $topCoins = getTopCoins($timestamp);


    if ($topCoins)
    {
        /*** sell first ****/
        $counter = 1;
        foreach ($topCoins as $topCoin)
        {
            if (
                    (array_key_exists($topCoin['id'], $portfolio) && $counter > $portfolioMaxLength) /* we have the coin and out op top x today */
                    ||
                    ($topCoin['percent_change_24h'] < 0) /* coin is doing bad */
               )
            {
                sellCoin($topCoin['id'],$portfolio[$topCoin['id']] );
            }
            $counter++;
        }
        /*** then buy ****/

        $i = count($portfolio);
        $ready = false;
        while ($i < $portfolioMaxLength && !$ready)
        {

            $ready = true;
            foreach ($topCoins as $topCoin)
            {
                if ($i < $portfolioMaxLength  && !array_key_exists($topCoin['id'],$portfolio))
                {
                    buyCoin($topCoin['id'],$topCoin);
                    $ready = false;
                }
                $i = count($portfolio);
            }
        }
    }
    echo "<hr/>saving:" . $savings . ", in portfolio:" . count($portfolio) * $defaultBuyAmount;
}




