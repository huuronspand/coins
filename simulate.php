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
$topCoins = array();
$portfolio = array();
$simStartTimestamp = 1518876000;
$simDays = 10;

function buyCoin($coinInfo)
{
    global $portfolio;
    $portfolio[$coinInfo->coinCode] = $coinInfo;
    return $portfolio;
}
function sellCoin($coinCode)
{
    global $portfolio;
    /* out of top 10, more than 100 rise ......? */
    unset($portfolio[$coinCode]);
    return $portfolio;
}

function getTopCoins($timestamp)
{
    global $db;
    $sql = "SELECT * FROM coinstats.coinstats
            WHERE market_cap_usd > 1000000000
            and timestamp > ".$timestamp." 
            order by percent_change_24h desc
            limit 10";
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

$coinInfo = new stdClass();
$coinInfo->coinCode = 'BTC';
$coinInfo->amount = 100;
$coinInfo->valueInEuro = 200;
$portfolio = buyCoin( $coinInfo);


$coinInfo = new stdClass();
$coinInfo->coinCode = 'ETH';
$coinInfo->amount = 200;
$coinInfo->valueInEuro = 300;
$portfolio = buyCoin( $coinInfo);
$portfolio = sellCoin( 'BTC' );
$topCoins = getTopCoins(1);
var_dump($topCoins);

