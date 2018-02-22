<?php
$server = $_SERVER['SERVER_NAME'];
require "/var/www/coins/config.php";


$ch = curl_init();
curl_setopt($ch,    CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
curl_setopt($ch, CURLOPT_URL, "https://bittrex.com/api/v1.1/public/getcurrencies");
$data = curl_exec($ch);
curl_close($ch);

$coindata = json_decode($data);
error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}

foreach ($coindata[result] as $coin)
{
    $coina = (array)$coin;
    $statement = $db->prepare("INSERT IGNORE INTO coins(name,symbol,bittrex)
        VALUES(?,?,?)");

    try {
        echo "-";
        $statement->execute(array($coin->name,$coin->symbol,1));
    } catch (Exception $e) {
        echo 'Insert into coins not working: ',  $e->getMessage(), "\n";
    }
}