<?php

require "../../config.php";
require_once ("../classes/simple_html_dom.php");



error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}




function scrapeHistDataOverview($url)
{

    $ch = curl_init();
    curl_setopt($ch,    CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);
    curl_close($ch);
    $html = new simple_html_dom();
    $html->load($data);


    foreach($html->find('li.text-center') as $li)
    {
        $a = $li->find("a",0);

       echo "'https://coinmarketcap.com" . $a->href . "',<br/>";
    }

 

}

    scrapeHistDataOverview("https://coinmarketcap.com/historical/");


?>

