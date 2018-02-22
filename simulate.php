<?php
$server = $_SERVER['SERVER_NAME'];
require "../config.php";

error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}

$initialSavings = 10000;
$currentSavings = $initialSavings;
$savingsInCoins = 0;
$defaultBuyAmount = 1000;
$portfolioMaxLength = $currentSavings / $defaultBuyAmount;
$topCoins = array();
$portfolio = array();
$simStartTimestamp = 1518876000;
$simDays = 10;
$oneDay = 24*60*60;
$debug = true;

function getClause($portfolio)
{
    $clause = "";
    $i = count($portfolio);
    if ($i >0)
    {
        $clause = $clause . " coinstats.id IN (";
        $ready = false;
        foreach($portfolio as $p)
        {
            $clause = $clause ."'" . $p['id'] . "',";
        }

        $clause = $clause . "'')";
    }
    else
    {
        $clause = " 1 = 2";
    }
   return $clause;
}

function displayFolio()
{
    $total =  0;
    global $portfolio;
    echo "<table border='1'>";
    echo "<tr><td colspan='2'>Portpolio</td>";
    foreach($portfolio as $p)
    {
        echo "<tr><td>". $p['id'] . "</td><td>" .  round($p['amount']) . "</td></tr>" ;
    }
    echo "</table>";
}

function portFolioValue()
{
     $total =  0;
     global $portfolio;
     foreach($portfolio as $p)
     {
         $total = $total + $p['amount'];
     }
    return $total;
}

function buyCoin($coinCode,$coinInfo, $debug)
{
    global $portfolio,$currentSavings,$defaultBuyAmount;
    $portfolio[$coinCode] = $coinInfo;
    if ($debug) echo "buy " . $coinCode . "(" .  $defaultBuyAmount.") <br/>";
    $currentSavings = $currentSavings - $defaultBuyAmount;
    $portfolio[$coinCode]["amount"] = $defaultBuyAmount;
    return $portfolio;
}
function sellCoin($coinCode,$coinInfo, $debug)
{
    global $portfolio,$currentSavings,$defaultBuyAmount;
    unset($portfolio[$coinCode]);
    if ($debug) echo "sell " . $coinCode . ", changed " . $coinInfo["percent_change_24h"] .  "%, profit:".($coinInfo["percent_change_24h"]/100) * $defaultBuyAmount ."<br/>";
    $currentSavings = $currentSavings + (1 + ($coinInfo["percent_change_24h"]/100)) * $defaultBuyAmount;
    return $portfolio;
}



function shouldSell($topCoin, $topPosition)
{
    global $portfolio, $portfolioMaxLength;
    $result = false;
    if  (
        /* we have a in portfolio coin which is out op top x today */
            array_key_exists($topCoin['id'], $portfolio)
            &&
            (
                /* coin fell out of top */
                ($topPosition > $portfolioMaxLength)
                ||
                /* coin is doing bad */
                ($topCoin['percent_change_24h'] < 0)
            )
        )
    {
        //cho("should sell: in portfolio:". array_key_exists($topCoin['id'],$portfolio). " , position:". $counter . " perc change:" . $topCoin['percent_change_24h'] . "<br/>");
        $result = true;
    }

  return $result;
}

function getTopCoins($timestamp)
{
    global $db, $oneDay,$portfolio ;
    $sql = "select *
            FROM
            (
                select *
                FROM 
                (
                SELECT coinstats.coinstats.* FROM coinstats.coinstats,coinstats.coins
                WHERE 24h_volume_usd > 1000000 
                and timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $oneDay) . ")))
                AND coinName = name
                order by timestamp desc, percent_change_24h desc
                limit 10
                ) tmp1
                UNION
                select *
                from
                (
                SELECT coinstats.coinstats.* FROM coinstats.coinstats, coinstats.coins
                where timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $oneDay) . ")))
                and " . getClause($portfolio) . "
                AND coinName = name
                order by timestamp desc, percent_change_24h desc
                limit 10
                ) tmp2
            ) tmp3
            order by timestamp desc, percent_change_24h desc";

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
        $positionInTopCoins = 1;
        foreach ($topCoins as $topCoin)
        {
            if (shouldSell($topCoin, $positionInTopCoins) )
            {
                sellCoin($topCoin['id'],$portfolio[$topCoin['id']] ,$debug);
            }
            $positionInTopCoins++;
        }
        /*** then buy ****/

        $i = count($portfolio);
        $ready = false;
        $nrToBuy = 0;
        while ($i <= $portfolioMaxLength && !$ready)
        {
            $ready = true;
            foreach ($topCoins as $topCoin)
            {
                if ($i < $portfolioMaxLength  && !array_key_exists($topCoin['id'],$portfolio))
                {
                    if ($currentSavings  >= $defaultBuyAmount)
                    {
                        $nrToBuy++;
                        $ready = false;
                        $i++;
                    }
                }

            }
        }
        if ($nrToBuy > 0) $defaultBuyAmount = $currentSavings / $nrToBuy;
        $i = count($portfolio);

        $ready = false;
        while ($i <= $portfolioMaxLength && !$ready)
        {
            $ready = true;
            foreach ($topCoins as $topCoin)
            {
                if ($i < $portfolioMaxLength  && !array_key_exists($topCoin['id'],$portfolio))
                {
                    if ($currentSavings  >= $defaultBuyAmount)
                    {
                        buyCoin($topCoin['id'],$topCoin, $debug);
                        $ready = false;
                    }

                }
                $i = count($portfolio);
            }
        }
    }
    $portfolioVal = round(portFolioValue());
    if ($debug) displayFolio();
    echo "<hr/><div style='background-color:lightblue'>tot value : " . round($currentSavings + $portfolioVal)."( total growth:" .  (-100+round(100*($currentSavings + $portfolioVal) / $initialSavings) ). "% ) " . " (in savings:" . round($currentSavings) . ", in portfolio:" . $portfolioVal  . ")</div>";
    $defaultBuyAmountNew = $defaultBuyAmount + $currentSavings /$portfolioMaxLength;
    if ($debug) echo "<hr/>buy Amount from " .round($defaultBuyAmount) . " to ".round($defaultBuyAmountNew) . "<br/><hr/>";
    $defaultBuyAmount = $defaultBuyAmountNew;
}




