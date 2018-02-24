<?php
$server = $_SERVER['SERVER_NAME'];
require "../../config.php";


$ch = curl_init();
curl_setopt($ch,    CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
curl_setopt($ch, CURLOPT_URL, "https://api.hitbtc.com/api/2/public/currency");
$data = curl_exec($ch);
curl_close($ch);
try {
    $coindata = json_decode($data);
    error_reporting(E_ALL);
    try {
        $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
        echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
    }
    echo "Start checking hitbtc coins";

    foreach ($coindata as $coin)
    {
        if($coin->delisted === "false")
            $coin->Status = 1;
        else
            $coin->Status = 0;
        $statement = $db->prepare("INSERT IGNORE INTO coins_hitbtc(hitbtcName,hitbtcSymbol,hitbtcActive)
            VALUES(?,?,?)");

        try {
            echo "-";
            $statement->execute(array($coin->fullName,$coin->id,$coin->Status));
        } catch (Exception $e) {
            echo 'Insert into coins not working: ',  $e->getMessage(), "\n";
        }
    }

} catch (Exception $e) {
    echo 'Super error: ',  $e->getMessage(), "\n";
}

?>