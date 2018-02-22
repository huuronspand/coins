<?php
$server = $_SERVER['SERVER_NAME'];
require "../../config.php";


$ch = curl_init();
curl_setopt($ch,    CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
curl_setopt($ch, CURLOPT_URL, "https://www.cryptopia.co.nz/api/GetCurrencies");
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
    echo "Start checking bittrex coins";

    foreach ($coindata->Data as $coin)
    {
    echo $coin->Name."aaa";
    die();
        $statement = $db->prepare("INSERT INTO coins (coinName,coinSymbol,cryptopia) VALUES (:name, :symbol, :cryptopia1) on duplicate key update cryptopia=:cryptopia2");

        try {
            echo "-";

            $statement->bindParam(":name", $coin->Name);
            $statement->bindParam(":symbol", $coin->Symbol);
            $statement->bindParam(":cryptopia1", 1);
            $statement->bindParam(":cryptopia2", 1);
            $statement->execute();

        } catch (Exception $e) {
            echo 'Insert into coins not working: ',  $e->getMessage(), "\n";
        }
    }

} catch (Exception $e) {
    echo 'Super error: ',  $e->getMessage(), "\n";
}

?>