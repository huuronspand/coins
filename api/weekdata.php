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


function getData()
    {
        global $db;
        $sql = "
                SELECT DAYOFWEEK(from_unixtime(timestamp)) dayNr, dayname(from_unixtime(timestamp)) dayName,
                round(avg(percent_change_24h),2) perc_change,
                count(*) samples
                from coinstats
                group by DAYOFWEEK(from_unixtime(timestamp)), dayname(from_unixtime(timestamp))
  ";

        $result = $db->query($sql,PDO::FETCH_ASSOC);

        if($result !== false) {
            $coins = array();
            foreach($result as $row)
            {
                $coins[] =  $row;
            }
        }

        return $coins;
    }


echo "{\"success\":true,\"result\":" . json_encode(getData()) . "}";
