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


if ($handle = opendir('./datafiles')) {

    $deleteSql = "delete from coinstats.coinstats where notes = 'histdata2'";
    $db->exec($deleteSql);
    while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..")
        {

            $handlefile = fopen('./datafiles/' . $entry, "r");
            if ($handlefile) {
                $teller = 1;
                echo "<br/><br/>FILE:" . $entry . "<br/>";
                $coinCode = str_replace(".CSV","",strtoupper($entry)) ;
                $lastPrice = null;
                while (($line = fgets($handlefile)) !== false)
                {
                    // process the line read.
                    if (($teller  > 1) /*&& ($teller < 4 ) */ )
                    {
                        //echo $line . "<br/>";
                        $data = explode(",", $line);
                        if (count($data) >= 8) {
                            $timestamp = strtotime($data[0]) + (2 * 60 * 60);
                            $volume_usd = $data[1];
                            $txCount = $data[2];
                            $marketCapUSD = $data[3];
                            $priceUSD = $data[4];
                            $exchangeVolumeUSD = $data[5];
                            $generatedCoins = $data[6];
                            $fees = $data[7];
                            if ($lastPrice)
                            {
                              $percent_change =   (($priceUSD - $lastPrice) / $lastPrice) * 100;
                            }
                            else
                            {
                                $percent_change = 0;
                            }
                            $lastPrice = $priceUSD;
                            if ($exchangeVolumeUSD > 0 && $marketCapUSD > 0) {
                                $statement = $db->prepare("
                                      INSERT IGNORE INTO coinstats(
                                                                      timestamp,
                                                                      id,
                                                                      name,
                                                                      symbol,
                                                                      price_usd,
                                                                      24h_volume_usd,
                                                                      market_cap_usd,
                                                                      percent_change_24h,
                                                                      notes
                                                                   )
                                      VALUES                       (?,
                                                                    ?,
                                                                    ?,
                                                                    ?,
                                                                    ?,
                                                                    ?,
                                                                    ?,
                                                                    ?,
                                                                    ?)");

                                try {

                                    $statement->execute(array(
                                        $timestamp,
                                        $coinCode,
                                        $coinCode,
                                        $coinCode,
                                        $priceUSD,
                                        $exchangeVolumeUSD,
                                        $marketCapUSD,
                                        $percent_change,
                                        'histdata2'));
                                } catch (Exception $e) {
                                    echo 'Insert into coinstats not working: ', $e->getMessage(), "\n";
                                }
                            }
                        }

                    }
                    else
                    {
                        if ($teller !=1)
                        {
                            break;
                        }
                        else
                        {
                            echo $line . "<br/>";
                        }
                    }
                    $teller++;
                }

                fclose($handlefile);
            } else {
                // error opening the file.
            }

        }
    }

    closedir($handle);
}

