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
    var $test = 1;
    var $reInvestProfit;

    public function __construct($initialSavings = 10000,
                         $savingsInCoins      = 0,
                         $defaultBuyAmount = 1000,
                         $simStartTimestamp = 1518876000,
                         $simDays= 10,
                         $outputLevel = true,
                         $reInvestProfit = true)
    {
        $this->oneDay  = 24 * 60 * 60;
        $this->init(    $initialSavings ,
                        $savingsInCoins     ,
                        $defaultBuyAmount,
                        $simStartTimestamp,
                        $simDays,
                        $outputLevel,
                        $reInvestProfit);
    }

    public function init( $initialSavings = 10000,
                    $savingsInCoins      = 0,
                    $defaultBuyAmount = 1000,
                    $simStartTimestamp = 1518876000,
                    $simDays= 10,
                    $outputLevel = true,
                    $reInvestProfit=true)
    {
        $this->initialSavings      = $initialSavings;
        $this->currentSavings      = $this->initialSavings;
        $this->savingsInCoins      = $savingsInCoins;
        $this->defaultBuyAmount    = $defaultBuyAmount;
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

    private function displayFolio()
    {
        $total =  0;

        echo "<table border='1'>";
        echo "<tr><td colspan='2'>Portpolio</td>";
        foreach($this->portfolio as $p)
        {
            echo "<tr><td>". $p['id'] . "</td><td>" .  round($p['amount']) . "</td></tr>" ;
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

    private function buyCoin($coinCode,$coinInfo, $outputLevel)
    {
        $this->portfolio[$coinCode] = $coinInfo;
        if ($this->outputLevel > 1) echo "buy " . $coinCode . "(" .  $this->defaultBuyAmount.") <br/>";
        $this->currentSavings = $this->currentSavings - $this->defaultBuyAmount;
        $this->portfolio[$coinCode]["amount"] = $this->defaultBuyAmount;
        return $this->portfolio;
    }
    private function sellCoin($coinCode,$coinInfo, $outputLevel)
    {
        unset($this->portfolio[$coinCode]);
        if ($this->outputLevel > 1) echo "sell " . $coinCode . ", changed " . $coinInfo["percent_change_24h"] .  "%, profit:".($coinInfo["percent_change_24h"]/100) * $this->defaultBuyAmount ."<br/>";
        $this->currentSavings = $this->currentSavings + (1 + ($coinInfo["percent_change_24h"]/100)) * $this->defaultBuyAmount;
        return $this->portfolio;
    }


    private function shouldBuy($topCoin)
    {
        $result = false;

        /* coin is doing good */
        if ($topCoin['percent_change_24h'] > 10)
        {
            //echo("should buy:<br/>");
            $result = true;
        }

        return $result;
    }
    private function shouldSell($topCoin, $topPosition)
    {
        $result = false;
        if  (
            /* we have a in portfolio coin which is out op top x today */
            array_key_exists($topCoin['id'], $this->portfolio)
            &&
            (
                /* coin fell out of top */
                ($topPosition > $this->portfolioMaxLength)
                ||
                /* coin is doing bad */
                ($topCoin['percent_change_24h'] < 5)
            )
        )
        {
            //echo("should sell: in portfolio:". array_key_exists($topCoin['id'],$portfolio). " , position:". $counter . " perc change:" . $topCoin['percent_change_24h'] . "<br/>");
            $result = true;
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
                SELECT coinstats.* 
                FROM coinstats.coinstats, 
                ( select  bittrexName nameActiveName
                  from    coinstats.coins_bittrex
                  where   bittrexActive=1
                  union 
                  select  hitbtcName  
                  from    coinstats.coins_hitbtc
                  where   hitbtcActive=1
                  union 
                  select  cryptopiaName 
                  from    coinstats.coins_cryptopia
                  where   cryptopiaActive=1) coins
                WHERE 24h_volume_usd > 10000000 
                AND coinstats.name = coins.nameActiveName 
                and timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))
                order by timestamp desc, percent_change_24h desc
                limit ". (round($this->portfolioMaxLength)) ."
                ) tmp1
                UNION
                select *
                from
                (
                SELECT * FROM coinstats.coinstats
                where timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))
                and " . $this->getClause($this->portfolio) . "
                order by timestamp desc, percent_change_24h desc
                limit ". round($this->portfolioMaxLength) ."
                ) tmp2
            ) tmp3
            order by timestamp desc, percent_change_24h desc";
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
        for ($t = 1; $t <= $this->simDays; $t++) {
            if ($this->outputLevel > 0) echo "<hr/>Day " . $t . "<br/>";

            $timestamp = $this->simStartTimestamp + $t * $this->oneDay;
            $topCoins = $this->getTopCoins($timestamp);

            if ($topCoins) {
                /*** sell first ****/
                $positionInTopCoins = 1;
                foreach ($topCoins as $topCoin) {
                    if ($this->shouldSell($topCoin, $positionInTopCoins)) {
                        $this->sellCoin($topCoin['id'], $this->portfolio[$topCoin['id']], $this->outputLevel);
                    }
                    $positionInTopCoins++;
                }


                $i = count($this->portfolio);
                $ready = false;
                $nrToBuy = 0;
                $toBuy = array();
                while ($i <= $this->portfolioMaxLength && !$ready) {
                    $ready = true;
                    foreach ($topCoins as $topCoin) {
                        if ($i < $this->portfolioMaxLength && !array_key_exists($topCoin['id'], $this->portfolio)) {
                            if ($this->shouldBuy($topCoin)) {
                                $toBuy[$topCoin['id']] = $topCoin;
                                $nrToBuy++;
                                $ready = false;
                                $i++;
                            }
                        }
                    }
                }

                /*** reinvest profit? ****/
                if ($nrToBuy > 0 && $this->reInvestProfit) $this->defaultBuyAmount = $this->currentSavings / count($toBuy);

                /*** then buy ****/

                foreach ($toBuy as $coin) {
                    $this->buyCoin($coin['id'], $coin, $this->outputLevel);
                }

                $portfolioVal = round($this->portFolioValue());
                if ($this->outputLevel > 0) $this->displayFolio();
                if ($this->outputLevel > 0) echo "<hr/><div style='background-color:lightblue'>tot value : " . round($this->currentSavings + $portfolioVal) . "( total growth:" . (-100 + round(100 * ($this->currentSavings + $portfolioVal) / $this->initialSavings)) . "% ) " . " (in savings:" . round($this->currentSavings) . ", in portfolio:" . $portfolioVal . ")</div>";
                if ($this->outputLevel > 0) echo "<hr/>buy Amount  " . round($this->defaultBuyAmount) . "<br/><hr/>";

            }
        }
    }
    public function showResults()
    {
        echo ", endSavings:" . round($this->currentSavings + $this->portFolioValue());
        echo "( cash:". round($this->currentSavings) .  ", portfolio:" . round($this->portFolioValue()) . " )<br/>";
    }
    public function showParams()
    {
        echo "initialSavings:" . $this->initialSavings . ", maxNrOfCoins types in portfolio:" . $this->portfolioMaxLength .", reinvest profit:". $this->reInvestProfit . ", start day:" . date( 'd/m/Y',$this->simStartTimestamp) .", nrOfDays:". $this->simDays ;
    }
}

$outputLevel = 0;
$startTimestamp = 1518876000;
$nrOfDays = 10;
$startSaving = 0;

echo "Options: <br/>
outputlevel=0/1/2/3 (default 0) <br/>
startdate=YYYYMMDD (default 20180217)<br/>>
nrofdays={number} (default 10)<hr/> <br/><br/>";

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
        if ($nrOfDays > 100) $nrOfDays = 100;
    }
}

$sim = new simulation();
/*

$sim->init(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, true);
$sim->run();
$sim->showParams();
$sim->showResults();
$sim = new simulation();
$sim->init(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, false);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 2000, $startTimestamp, $nrOfDays, $outputLevel, true);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 3333, $startTimestamp, $nrOfDays, $outputLevel, true);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 5000, $startTimestamp, $nrOfDays, $outputLevel, true);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 10000, $startTimestamp, $nrOfDays, $outputLevel, true);
$sim->run();
$sim->showParams();
$sim->showResults();


$sim->init(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel, false);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 200, $startTimestamp, $nrOfDays, $outputLevel, true);
$sim->run();
$sim->showParams();
$sim->showResults();
*/
echo "<hr>Deze zijn wel save makkelijk bij te houden en rewarding<br>";
$sim->init(10000, $startSaving, 3333, $startTimestamp, $nrOfDays, $outputLevel, false);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 5000, $startTimestamp, $nrOfDays, $outputLevel, false);
$sim->run();
$sim->showParams();
$sim->showResults();

$sim->init(10000, $startSaving, 10000, $startTimestamp, $nrOfDays, $outputLevel, false);
$sim->run();
$sim->showParams();
$sim->showResults();
