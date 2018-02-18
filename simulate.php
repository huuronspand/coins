<?php
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

$savings = 10000;
$savingsInCoins = 0;
$defaultBuyAmount = 1000;
$portfolioMaxLength = $savings / $defaultBuyAmount;
$topCoins = array();
$portfolio = array();
$simStartTimestamp = 1518876000;
$simDays = 10;
$oneDay = 24*60*60;

function buyCoin($coinCode,$coinInfo)
{
    global $portfolio;
    $portfolio[$coinCode] = $coinInfo;
    echo "buy " . $coinCode . "<br/>";
    return $portfolio;
}
function sellCoin($coinCode)
{
    global $portfolio;
    /* out of top 10, more than 100 rise ......? */
    unset($portfolio[$coinCode]);
    echo "sell " . $coinCode . "<br/>";
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
                sellCoin($topCoin['id']);
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
}




