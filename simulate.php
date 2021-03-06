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


class simulation
{

    var $initialSavings;
    var $currentSavings;
    var $savingsInCoins;
    var $defaultBuyAmount;
    var $portfolioMaxLength;
    var $topCoins;
    var $portfolio;
    var $simStartTimestamp;
    var $simDays;
    var $oneDay;
    var $outputLevel;
    var $reInvestProfit;
    var $sellEveryNrOfDays;
    var $topCoinsPrev;
    var $buySellFixedDays;
    var $buyDay;
    var $sellDay;

    public function __construct(     $initialSavings = 10000,
                                     $savingsInCoins      = 0,
                                     $defaultBuyAmount = 1000,
                                     $simStartTimestamp = 1518876000,
                                     $simDays= 10,
                                     $outputLevel = true,
                                     $reInvestProfit = true,
                                     $sellEveryNrOfDays = 0,
                                     $buySellFixedDays = false,
                                     $buyDay = 0,
                                     $sellDay = 0)
    {
        $this->oneDay  = 24 * 60 * 60;
        $this->init(    $initialSavings ,
                        $savingsInCoins     ,
                        $defaultBuyAmount,
                        $simStartTimestamp,
                        $simDays,
                        $outputLevel,
                        $reInvestProfit,
                        $sellEveryNrOfDays,
                        $buySellFixedDays,
                        $buyDay = 0,
                        $sellDay = 0);
    }

    public function init(   $initialSavings = 10000,
                            $savingsInCoins      = 0,
                            $defaultBuyAmount = 1000,
                            $simStartTimestamp = 1518876000,
                            $simDays= 10,
                            $outputLevel = true,
                            $reInvestProfit=true,
                            $sellEveryNrOfDays = 0,
                            $buySellFixedDays  = false,
                            $buyDay = 5,
                            $sellDay = 1)
    {
        $this->initialSavings      = $initialSavings;
        $this->currentSavings      = $this->initialSavings;
        $this->savingsInCoins      = $savingsInCoins;
        $this->defaultBuyAmount    = $defaultBuyAmount;
        $this->buyDay = $buyDay;
        $this->sellDay = $sellDay;

        $this->sellEveryNrOfDays     = $sellEveryNrOfDays;
        $this->buySellFixedDays  = $buySellFixedDays;
        $this->portfolioMaxLength = round($this->currentSavings / $this->defaultBuyAmount);
        $this->topCoins = array();
        $this->portfolio = array();
        $this->simStartTimestamp = $simStartTimestamp;
        $this->simDays = $simDays;
        $this->outputLevel = $outputLevel;
        $this->reInvestProfit = $reInvestProfit;
    }
    private function getClause()
    {
        $clause = "";
        $i = count($this->portfolio);
        if ($i >0)
        {
            $clause = $clause . " coinstats.id IN (";
            foreach($this->portfolio as $p)
            {
                $clause = $clause ."'" . $p['symbol'] . "',";
            }

            $clause = $clause . "'')";
        }
        else
        {
            $clause = " 1 = 2";
        }
        return $clause;
    }

    private function displayFolio()
    {
        $total =  0;

        echo "<table border='1'>";
        echo "<tr><td colspan='2'>Portpolio</td>";
        foreach($this->portfolio as $p)
        {
            echo "<tr><td>". $p['symbol'] . "</td><td>" .  round($p['amount']) . "</td></tr>" ;
        }
        echo "</table>";
    }

    private function portFolioValue()
    {
        $total =  0;
        foreach($this->portfolio as $p)
        {
            $total = $total + $p['amount'];
        }
        return $total;
    }

    private function buyCoin($coinCode,$coinInfo)
    {
        $this->portfolio[$coinCode] = $coinInfo;

        if ($this->outputLevel > 1)
        {
            echo "buy for " .  round($this->defaultBuyAmount,2). " " .  $coinCode ;
            echo  " (coin price " . round($coinInfo['price_usd'],2) . ")<br/><hr/>";
        }
        $this->currentSavings = $this->currentSavings - $this->defaultBuyAmount;
        $this->portfolio[$coinCode]["amount"] = $this->defaultBuyAmount;
        return $this->portfolio;
    }
    private function sellCoin($topCoin,$coinInfo)
    {

        $change=  ($topCoin['price_usd']  - $this->portfolio[$topCoin['symbol']]['price_usd'] ) /  $this->portfolio[$topCoin['symbol']]['price_usd'];

        if ($this->outputLevel > 1)
        {
            echo "sell " . $topCoin['symbol'] . " in portfolio:".  ($coinInfo['amount']).  "  now worth:". round((1+$change) * $coinInfo['amount'],2);

            echo  "( original price coin:" . round($this->portfolio[$topCoin['symbol']]['price_usd'],2) .
                " now:" .
                round($topCoin['price_usd'],2) . ")  changed:" . round($change * 100,2) . "<br/><hr/>";
        }
        $this->currentSavings = $this->currentSavings + (1 + $change) * $coinInfo['amount'];
        unset($this->portfolio[$topCoin['symbol']]);
        return $this->portfolio;
    }

    private function getChange($coinCode, $timestamp,$perWeek)
    { global $db;
        if ($perWeek)
        {
            $sql = "SELECT min(percent_change_7d) as percent_change
                FROM coinstats.coinstats
                WHERE symbol = '". $coinCode."' 
                AND     notes != 'histdata' and timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . ")))
                                     AND  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))";
            if ($this->outputLevel > 2) echo('<hr/>' . nl2br($sql) . '<hr/>');
        }
        else
        {
            $sql = "SELECT min(percent_change_24h) as percent_change
                FROM coinstats.coinstats
                WHERE symbol = '". $coinCode."' 
                AND      notes != 'histdata' and timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . ")))
                                     AND  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))";
            if ($this->outputLevel > 2) echo('<hr/>' . nl2br($sql) . '<hr/>');
        }

        $result = $db->query($sql);
        if($result !== false) {

            foreach($result as $row)
            {
               $result = $row['percent_change'];
            }
        }
        return $result;


    }
    private function shouldBuy($topCoin,$timestamp,$perWeek)
    {
        $result = false;
        $change_1 = false;

        if ($this->buySellFixedDays )
        {
            if ($topCoin['dayNr'] == $this->buyDay) {$result = true;}
        }
        else
        {
            if ($perWeek)
            {
                $daysBack = 7 * $this->oneDay;
            }
            else
            {
                $daysBack = 1 * $this->oneDay;
            }
            if ($this->topCoinsPrev)
            {
                foreach ($this->topCoinsPrev as $t) {
                    if ($t['symbol'] == $topCoin['symbol'])
                    {
                        if ($perWeek)
                        {
                            $change_1 = $t['percent_change_7d'];
                        }
                        else
                        {
                            $change_1 = $t['percent_change_24h'];
                        }

                    }

                }
            }
            if (!$change_1)
            {
                $change_1 = $this->getChange($topCoin['symbol'],$timestamp - $daysBack, $perWeek);
            }

            //$change_2 = $this->getChange($topCoin['symbol'],$timestamp - 2 * $this->oneDay);
            if($perWeek)
            {
                $change = $topCoin['percent_change_7d'];
            }
            else
            {
                $change = $topCoin['percent_change_24h'];
            }

            if ( $change_1 &&  ($change > $change_1) )
            {
                $result = true;
            }
            if ($result)
            {
                if ($this->outputLevel > 1) echo("should buy:". $topCoin['symbol'] . ",thursdaySaturday:". $this->thursdaySaturday . ":" . $change. " : " . $change_1." : " /*. $change_2 */." <br/>");

            }
        }



        return $result;
    }
    private function shouldSell($topCoin, $simDay, $lastday = false)
    {
        $result = false;

        if  (array_key_exists($topCoin['symbol'], $this->portfolio))
        {
            $change = ($topCoin['price_usd'] / $this->portfolio[$topCoin['symbol']]['price_usd']);

            if ($this->buySellFixedDays)
            {
                if ($topCoin['dayNr'] == $this->sellDay) {$result = true;}
            }
            else
            {
                if
                (
                    /* sell portfolio after x days? */
                    (   $this->sellEveryNrOfDays > 0 &&
                        $simDay > 0 &&
                        ($simDay % $this->sellEveryNrOfDays == 0)
                    )
                    ||
                    (
                        $change > 1.03/////
                    )
                    ||
                    ($lastday)

                )

                {
                       $result = true;
                }
            }

        }

        if ($result)
        {
            if ($this->outputLevel > 1) echo("should sell:" . $topCoin['symbol'].",thursdaySaturday:". $this->thursdaySaturday  .  ", change:" . $change . "<br/>");

        }
        return $result;
    }

    private function getTopCoins($timestamp)
    {
        global $db;
        $sql = "select *
            FROM
            (
                select *
                FROM 
                (
                SELECT Date(from_unixtime(timestamp)) startofday,
                      max(coinstats.`timestamp`) as `timestamp`, 
                      max(coinstats.`id`) as `id`, 
                      max(coinstats.`name`) as `name`, 
                      max(coinstats.`symbol`) as `symbol`, 
                      min(coinstats.`rank`) as `rank`, 
                      avg(coinstats.`price_usd`) as `price_usd`, 
                      avg(coinstats.`price_btc`) as `price_btc`, 
                      avg(coinstats.`24h_volume_usd`) as  `24h_volume_usd`,
                      avg(coinstats.`market_cap_usd`) as `market_cap_usd`, 
                      avg(coinstats.`available_supply`) as `available_supply`, 
                      avg(coinstats.`total_supply`) as `total_supply`, 
                      avg(coinstats.`max_supply`) as `max_supply`, 
                      avg(coinstats.`percent_change_1h`) as percent_change_1h, 
                      avg(coinstats.`percent_change_24h`) as `percent_change_24h`, 
                      avg(coinstats.`percent_change_7d`) as `percent_change_7d`,
                      max(coinstats.notes)  as notes,
                      max(dayofweek(from_unixtime(coinstats.`timestamp`))) dayNr /* 1= sunday */
                FROM coinstats.coinstats, 
                ( select  bittrexSymbol symbol
                  from    coinstats.coins_bittrex
                  where   bittrexActive=1
                  union 
                  select  hitbtcSymbol  
                  from    coinstats.coins_hitbtc
                  where   hitbtcActive=1
                  union 
                  select  cryptopiaSymbol 
                  from    coinstats.coins_cryptopia
                  where   cryptopiaActive=1) coins
                WHERE 24h_volume_usd > 10000000 
                AND coinstats.symbol = coins.symbol 
                and timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))
                group by Date(from_unixtime(timestamp)), coinstats.symbol
                order by market_cap_usd
                limit ". 5*(round($this->portfolioMaxLength)) ."
                ) tmp1
                UNION
                select *
                from
                (
                SELECT Date(from_unixtime(timestamp)) startofday,
                      max(coinstats.`timestamp`) as `timestamp`, 
                      max(coinstats.`id`) as `id`, 
                      max(coinstats.`name`) as `name`, 
                      max(coinstats.`symbol`) as `symbol`, 
                      min(coinstats.`rank`) as `rank`, 
                      avg(coinstats.`price_usd`) as `price_usd`, 
                      avg(coinstats.`price_btc`) as `price_btc`, 
                      avg(coinstats.`24h_volume_usd`) as  `24h_volume_usd`,
                      avg(coinstats.`market_cap_usd`) as `market_cap_usd`, 
                      avg(coinstats.`available_supply`) as `available_supply`, 
                      avg(coinstats.`total_supply`) as `total_supply`, 
                      avg(coinstats.`max_supply`) as `max_supply`, 
                      avg(coinstats.`percent_change_1h`) as percent_change_1h, 
                      avg(coinstats.`percent_change_24h`) as `percent_change_24h`, 
                      avg(coinstats.`percent_change_7d`) as `percent_change_7d`,
                       max(coinstats.notes)  as notes,
                       max(dayofweek(from_unixtime(coinstats.`timestamp`))) dayNr
                FROM coinstats.coinstats
                where timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))
                and " . $this->getClause($this->portfolio) . "
                group by Date(from_unixtime(timestamp)) , symbol
                order by market_cap_usd
                limit ". 5*round($this->portfolioMaxLength) ."
                ) tmp2
            ) tmp3
            group by Date(from_unixtime(timestamp)), symbol
            order by market_cap_usd desc";

        if ($this->outputLevel > 2) echo('<hr/>' . nl2br($sql) . '<hr/>');
        $result = $db->query($sql);

        if($result !== false) {
            $this->topCoins = array();
            foreach($result as $row)
            {
                array_push($this->topCoins, $row);
            }
        }

        return $this->topCoins;
    }

    public function run()
    {
        $topCoins = false;
        $lasttopCoinsData = false;
        for ($t = 0; $t < $this->simDays; $t++) {


            $timestamp = $this->simStartTimestamp + $t * $this->oneDay;
            if ($topCoins)
            {
                $this->topCoinsPrev = $topCoins;
            }
            $topCoins = $this->getTopCoins($timestamp);

            if ($topCoins)
            {
                if ($this->outputLevel > 0) echo "<hr/>Day " . $t . "<br/>";
                $lasttopCoinsData = $topCoins;
                /*** sell first ****/
                $positionInTopCoins = 1;
                foreach ($topCoins as $topCoin)
                {
                    if ($this->shouldSell($topCoin,$t))
                    {
                        $this->sellCoin($topCoin, $this->portfolio[$topCoin['symbol']]);
                    }
                    $positionInTopCoins++;
                }

                $i = count($this->portfolio);
                $nrToBuy = 0;
                $toBuy = array();
                $perWeek = false;
                foreach ($topCoins as $topCoin)
                {
                    if ($topCoin['notes'] == 'xxxxx') $perWeek = true;
                    if ($i < $this->portfolioMaxLength && !array_key_exists($topCoin['symbol'], $this->portfolio)) {
                        if ($this->shouldBuy($topCoin,$timestamp,$perWeek) && $this->currentSavings > 0)
                        {
                            $toBuy[$topCoin['symbol']] = $topCoin;
                            $nrToBuy++;
                            $i++;
                        }
                    }
                }

                /*** reinvest profit? ****/

                if ($nrToBuy > 0 && $this->reInvestProfit)
                {
                    $this->defaultBuyAmount = $this->currentSavings / count($toBuy);
                }

                /*** then buy ****/

                $i = 0;
                foreach ($toBuy as $coin) {
                    if ($i < $this->portfolioMaxLength) $this->buyCoin($coin['symbol'], $coin);
                }

                $portfolioVal = round($this->portFolioValue());
                if ($this->outputLevel > 0) $this->displayFolio();
                if ($this->outputLevel > 0) echo "<hr/><div style='background-color:lightblue'>tot value : " . round($this->currentSavings + $portfolioVal) . "( total growth:" . (-100 + round(100 * ($this->currentSavings + $portfolioVal) / $this->initialSavings)) . "% ) " . " (in savings:" . round($this->currentSavings) . ", in portfolio:" . $portfolioVal . ")</div>";
                if ($this->outputLevel > 0) echo "<hr/>buy Amount  " . round($this->defaultBuyAmount) . "<br/><hr/>";
            }
        }

        /* sell everything */
        if ($lasttopCoinsData)
        {
            echo "sell everyting on last day based on last known data<br/>";
            foreach ($lasttopCoinsData as $topCoin)
            {
                if ($this->shouldSell($topCoin,$t, true))
                {

                    $this->sellCoin($topCoin, $this->portfolio[$topCoin['symbol']]);
                }
                $positionInTopCoins++;
            }
        }



    }

    public function showResults()
    {
        $result = 100* round(( $this->currentSavings + $this->portFolioValue()  )  / $this->initialSavings ,2) - 100;
        echo "<table>
                    <tr   style='text-align:left'>
                        <th width='130'>result</th>
                        <th width='130'>initialSavings</th>
                        <th width='130'>endSavings</th>
                        <th width='130'>cash</th> 
                        <th width='130'>portfolio</th> 
                        <th width='130'>maxNrOfCoins</th>
                        <th width='130'>reinvest profit</th> 
                        <th width='130'>start day</th> 
                        <th width='130'>nrOfDays</th> 
                        <th width='130'>sellEvery</th>                         
                        <th width='130'>buy-sell OnFixedDays (1=sunday)</th> 
                    </tr>
                    
                    <tr  style='text-align:left'>
                      <td style='background-color:lightblue'>". ($result < 0 ? '<font color=red>':'<font color=green>') . $result .  "%</td>
                        <td >" . $this->initialSavings . "</td>
                        <td >" . round($this->currentSavings + $this->portFolioValue()). "</td>
                         <td>". round($this->currentSavings) .  "</td>
      
                        <td>" . round($this->portFolioValue()) . "</td>
                        <td>". $this->portfolioMaxLength."</td>
                        <td>". $this->reInvestProfit . "</td>
                        <td>". date( 'd/m/Y',$this->simStartTimestamp) ."</td>
                        <td>". $this->simDays ."</td>
                        <td>". ($this->sellEveryNrOfDays > 0 ? $this->sellEveryNrOfDays : "disabled") ."</td>
                        <td>". ($this->buySellFixedDays > 0 ? $this->buyDay ." - ". $this->sellDay : "disabled") ."</td>
                    </tr>
              </table><br/>";
    }
}

$outputLevel = 0;
$startTimestamp = 1518876000;
$nrOfDays = 100;
$startSaving = 0;


?>
    <head>
        <link rel="stylesheet" type="text/css" href="css/coins.css">
    </head>
<?php

echo "Options: <br/>
outputlevel=0/1/2/3 (default 0) <br/>
startdate=YYYYMMDD (default 20180217)<br/>
nrofdays={number} (default 100)<hr/> <br/><br/>";

if (isset($_GET["outputlevel"])) $outputLevel = $_GET["outputlevel"];
if (isset($_GET["startdate"]))
{ /* 20181231*/
    $ymd = $_GET["startdate"];
    if (is_numeric($ymd) || strlen($ymd) == 8 )
    {
        $year = substr($ymd,0,4);
        $month =substr($ymd,4,2);
        $day =substr($ymd,6,2);
        $startTimestamp = mktime( 2 , 2, 2, $month , $day, $year);
    }

}

if (isset($_GET["nrofdays"]))
{ /* 20181231*/
    $nr = $_GET["nrofdays"];
    if (is_numeric($nr)  )
    {
        $nrOfDays = (int)$nr;
        if ($nrOfDays > 500) $nrOfDays = 500;
    }
}

$sellEveryNrOfDays = 0; /* 0 = dont sell every x days */
if (isset($_GET["sellEveryNrOfDays"]))
{
    $nr = $_GET["sellEveryNrOfDays"];
    if (is_numeric($nr) )
    {
        $sellEveryNrOfDays = (int)$nr;
    }
}

$sim = new simulation();

/*
$sim->init(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();
$sim = new simulation();

$sim->init(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 2000, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 3333, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 5000, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 10000, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();


$sim->init(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();
*/


echo "<hr>Deze zijn wel save makkelijk bij te houden en rewarding<br>";
/*
$sim->init(10000, $startSaving, 2000, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 2000, $startTimestamp, $nrOfDays, $outputLevel, true , $sellEveryNrOfDays);
$sim->run();
$sim->showResults();


$sim->init(10000, $startSaving, 2500, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 3333, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 5000, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();


$sim->init(10000, $startSaving, 10000, $startTimestamp, $nrOfDays, $outputLevel, true, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

echo "<hr/>";
$sim->init(10000, $startSaving, 1666, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 2000, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();


$sim->init(10000, $startSaving, 2500, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();
$sim->init(10000, $startSaving, 3333, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();

$sim->init(10000, $startSaving, 5000, $startTimestamp, $nrOfDays, $outputLevel, false, $sellEveryNrOfDays);
$sim->run();
$sim->showResults();
*/

$sim->init( 10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, true,$sellEveryNrOfDays,
    true, /* buy and sell on fixed days */
    1, /* buy fixed day */
    3 /* sell fixed day */);
$sim->run();
$sim->showResults();




