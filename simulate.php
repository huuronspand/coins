<?php

$savings = 10000;
$savingsInCoins = 0;
$defaultBuyAmount = 1000;
$top10 = array();
$portfolio = array();
$simStartTimestamp = 1518876000;
$simDays = 10;

function buyCoin($portfolio,$coinInfo)
{
    $portfolio[$coinInfo->coinCode] = $coinInfo;
    return $portfolio;
}
function sellCoin($portfolio,$coinCode)
{
    /* out of top 10, more than 100 rise ......? */
    unset($portfolio[$coinCode]);
    return $portfolio;
}

function getTop10($timeStamp)
{
    $top10 = array();
    return $top10;
}

$coinInfo = new stdClass();
$coinInfo->coinCode = 'BTC';
$coinInfo->amount = 100;
$coinInfo->valueInEuro = 200;
$portfolio = buyCoin($portfolio, $coinInfo);


$coinInfo = new stdClass();
$coinInfo->coinCode = 'ETH';
$coinInfo->amount = 200;
$coinInfo->valueInEuro = 300;
$portfolio = buyCoin($portfolio, $coinInfo);


echo count($portfolio);
$portfolio = sellCoin($portfolio, 'BTC' );
echo count($portfolio);
var_dump($portfolio);