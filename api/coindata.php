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


function getData($timestamp)
    {
        global $db;
        $oneDay = 24 * 60 * 60;
        $sql = "
                SELECT *
                FROM coinstats.coinstats 
                WHERE timestamp between unix_timestamp(Date(from_unixtime(" . $timestamp . "))) 
                                and  unix_timestamp(Date(from_unixtime(" . ($timestamp + $oneDay) . ")))
                order by timestamp desc, percent_change_24h desc
                limit 2000";

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

if (!isset($_GET["year"]) || !isset($_GET["month"]) ||!isset($_GET["day"]) )
{
    die('usage: ?year=2018&month=02&day=19');
}

if (!is_numeric($_GET["year"]) || !is_numeric($_GET["month"]) || !is_numeric($_GET["day"]) )
{
    die('only numeric values are allowed');
}
$timestamp = mktime( 2 , 2, 2, $_GET["month"] , $_GET["day"], $_GET["year"]);

echo "{\"success\":true,\"result\":" . json_encode(getData($timestamp)) . "}";
