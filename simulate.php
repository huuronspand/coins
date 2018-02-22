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
    public function __construct($initialSavings = 10000,
                         $savingsInCoins      = 0,
                         $defaultBuyAmount = 1000,
                         $simStartTimestamp = 1518876000,
                         $simDays= 10,
                         $outputLevel = true)
    {
        $this->oneDay  = 24 * 60 * 60;
        $this->reInit(    $initialSavings ,
                        $savingsInCoins     ,
                        $defaultBuyAmount,
                        $simStartTimestamp,
                        $simDays,
                        $outputLevel);
    }

    public function reInit( $initialSavings = 10000,
                    $savingsInCoins      = 0,
                    $defaultBuyAmount = 1000,
                    $simStartTimestamp = 1518876000,
                    $simDays= 10,
                    $outputLevel = true)
    {
        $this->initialSavings      = $initialSavings;
        $this->currentSavings      = $this->initialSavings;
        $this->savingsInCoins      = $savingsInCoins;
        $this->defaultBuyAmount    = $defaultBuyAmount;
        $this->portfolioMaxLength = $this->currentSavings / $this->defaultBuyAmount;
        $this->topCoins = array();
        $this->portfolio = array();
        $this->simStartTimestamp = $simStartTimestamp;
        $this->simDays = $simDays;
        $this->outputLevel = $outputLevel;
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
        if ($outputLevel) echo "buy " . $coinCode . "(" .  $this->defaultBuyAmount.") <br/>";
        $this->currentSavings = $this->currentSavings - $this->defaultBuyAmount;
        $this->portfolio[$coinCode]["amount"] = $this->defaultBuyAmount;
        return $this->portfolio;
    }
    private function sellCoin($coinCode,$coinInfo, $outputLevel)
    {
        unset($this->portfolio[$coinCode]);
        if ($outputLevel) echo "sell " . $coinCode . ", changed " . $coinInfo["percent_change_24h"] .  "%, profit:".($coinInfo["percent_change_24h"]/100) * $this->defaultBuyAmount ."<br/>";
        $this->currentSavings = $this->currentSavings + (1 + ($coinInfo["percent_change_24h"]/100)) * $this->defaultBuyAmount;
        return $this->portfolio;
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
                ($topCoin['percent_change_24h'] < 0)
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
                SELECT * FROM coinstats.coinstats, coinstats.bittrex_coins
                WHERE 24h_volume_usd > 1000000 
                AND coinName = bittrexName 
                and timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))
                order by timestamp desc, percent_change_24h desc
                limit ". round($this->portfolioMaxLength) ."
                ) tmp1
                UNION
                select *
                from
                (
                SELECT * FROM coinstats.coinstats, coinstats.bittrex_coins
                where timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $this->oneDay) . ")))
                and " . $this->getClause($this->portfolio) . "
                 		AND coinName = bittrexName
                order by timestamp desc, percent_change_24h desc
                limit ". round($this->portfolioMaxLength) ."
                ) tmp2
            ) tmp3
            order by timestamp desc, percent_change_24h desc";

        $result = $db->query($sql);
        $topCoins = array();
        if($result !== false) {
            foreach($result as $row)
            {
                $this->topCoins[$row['id']] = $row;
            }
        }

        return $this->topCoins;
    }

    public function run()
    {
        for ($t = 1;  $t <= $this->simDays; $t++)
        {


            if ($this->outputLevel > 0) echo "<hr/>Day " . $t . "<br/>";

            $timestamp = $this->simStartTimestamp + $t * $this->oneDay;
            $topCoins = $this->getTopCoins($timestamp);

            if ($topCoins)
            {
                /*** sell first ****/
                $positionInTopCoins = 1;
                foreach ($topCoins as $topCoin)
                {
                    if ($this->shouldSell($topCoin, $positionInTopCoins) )
                    {
                        $this->sellCoin($topCoin['id'],$this->portfolio[$topCoin['id']] ,$this->outputLevel);
                    }
                    $positionInTopCoins++;
                }
                /*** then buy ****/

                $i = count($this->portfolio);
                $ready = false;
                $nrToBuy = 0;
                while ($i <= $this->portfolioMaxLength && !$ready)
                {
                    $ready = true;
                    foreach ($topCoins as $topCoin)
                    {
                        if ($i < $this->portfolioMaxLength  && !array_key_exists($topCoin['id'],$this->portfolio))
                        {
                            if ($this->currentSavings  >= $this->defaultBuyAmount)
                            {
                                $nrToBuy++;
                                $ready = false;
                                $i++;
                            }
                        }

                    }
                }
                if ($nrToBuy > 0) $defaultBuyAmount = $this->currentSavings / $nrToBuy;
                $i = count($this->portfolio);

                $ready = false;
                while ($i <= $this->portfolioMaxLength && !$ready)
                {
                    $ready = true;
                    foreach ($topCoins as $topCoin)
                    {
                        if ($i < $this->portfolioMaxLength  && !array_key_exists($topCoin['id'],$this->portfolio))
                        {
                            if ($this->currentSavings  >= $this->defaultBuyAmount)
                            {
                                $this->buyCoin($topCoin['id'],$topCoin, $this->outputLevel);
                                $ready = false;
                            }

                        }
                        $i = count($this->portfolio);
                    }
                }
            }
            $portfolioVal = round($this->portFolioValue());
            if ($this->outputLevel > 1) $this->displayFolio();
            if ($this->outputLevel > 0) echo "<hr/><div style='background-color:lightblue'>tot value : " . round($this->currentSavings + $portfolioVal)."( total growth:" .  (-100+round(100*($this->currentSavings + $portfolioVal) / $this->initialSavings) ). "% ) " . " (in savings:" . round($this->currentSavings) . ", in portfolio:" . $portfolioVal  . ")</div>";
            $defaultBuyAmountNew = $this->defaultBuyAmount + $this->currentSavings /$this->portfolioMaxLength;
            if ($this->outputLevel > 0) echo "<hr/>buy Amount from " .round($this->defaultBuyAmount) . " to ".round($defaultBuyAmountNew) . "<br/><hr/>";
            $this->defaultBuyAmount = $defaultBuyAmountNew;
        }
    }
    public function showResults()
    {
        echo "start:" . $this->initialSavings . ", end " . round($this->currentSavings + $this->portFolioValue()) . "<br/>";
    }
}

$outputLevel = 0;
$startTimestamp = 1518876000;
$nrOfDays = 10;
$startSaving = 0;

$sim = new simulation(10000, $startSaving, 1000, $startTimestamp, $nrOfDays, $outputLevel);
$sim->run();
$sim->showResults();

$sim->reInit(10000, $startSaving, 500, $startTimestamp, $nrOfDays, $outputLevel);
$sim->run();
$sim->showResults();




