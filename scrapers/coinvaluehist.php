<?php
set_time_limit ( 3600 );
require "../../config.php";
require_once ("../classes/simple_html_dom.php");



error_reporting(E_ALL);
try {
    $db = new PDO('mysql:host='.$_GLOBALS['dbLocation'].';dbname='.$_GLOBALS['db'].';charset=utf8mb4', $_GLOBALS['dbUser'], $_GLOBALS['dbPass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'Not possible to connect to mysql: ',  $e->getMessage(), "\n";
}




function scrapeHistData($url)
{
global $db;
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

    $ymd = substr($url,-9);
    $year = substr($ymd,0,4);
    $month =substr($ymd,4,2);
    $day=substr($ymd,6,2);

    $i = 1;

    $timestamp = mktime( 2 , 2, 2, $month , $day, $year);

    $delete = "DELETE FROM coinstats.coinstats WHERE notes='histdata' AND `timestamp` = " . $timestamp;

    if($db->exec($delete) != 0)
    {

        //echo "delete error";
        //die();
    }
    foreach($html->find('tr[id^="id-"]') as $row)
    {
        if ($i <=50000)
        {
            $tdCount = 0;
            foreach($row->find('td') as $td)
            {

                if ($tdCount == 1)
                { /* coinName */
                    $coinName = $td->find('a',1)->innertext;
                    //echo "coinName:"  .$coinName . "<br/>";
                }

                if ($tdCount == 2)
                { /* coinCode */
                    $coinCode = $td->innertext;
                    $coinCode = str_replace(array(" "),"",$coinCode);
                    //echo "coinCode:"  .$coinCode . "<br/>";
                }

                if ($tdCount == 3)
                { /* marketCap */
                    $marketCap = str_replace(array("$", ",","*","?"," "),"",$td->innertext);
                    //echo "marketCap:"  .$marketCap . "<br/>";
                }

                if ($tdCount == 4)
                { /* price */
                    $price = $td->find('a',0)->innertext;
                    $price = str_replace(array("$", ",","*","?"," "),"",$price);

                    //echo "price:"  .$price . "<br/>";
                }
                if ($tdCount == 5)
                { /* supply */

                    $supply = $td->find('a',0);
                    if (!$supply) $supply = $td->find('span',0);
                    $supply = $supply->innertext;
                    $supply = str_replace(array("$", ",","*","?"," "),"",$supply);
                    //echo "supply:"  .$supply . "<br/>";
                }
                if ($tdCount == 6)
                { /* volume */

                    $volume = $td->find('a',0)->innertext;
                    $volume = str_replace(array("$", ",","*","?"," "),"",$volume);
                    //echo "volume:"  .$volume . "<br/>";
                }

                if ($tdCount == 7)
                { /* perc1h */
                    $perc1h = str_replace(array("%", ",","*","?"," "),"",$td->innertext);
                    //echo "perc1h:"  .$perc1h . "<br/>";
                }

                if ($tdCount == 8)
                { /* perc24h */
                    $perc24h = str_replace(array("%", ",","*","?"," "),"",$td->innertext);
                    //echo "perc24h:"  .$perc24h . "<br/>";
                }


                if ($tdCount == 9)
                { /* perc7d */
                    $perc7d = str_replace(array("%", ",","*","?"," "),"",$td->innertext);
                    //echo "perc7d:"  .$perc7d . "<br/>";
                }

                $tdCount++;
            }
            if ($supply != "" && $volume != "LowVol" )
            {
                $sql = $db->prepare("INSERT INTO coinstats.coinstats 
            (`timestamp`,`id`,`name`,symbol, price_usd, 24h_volume_usd,market_cap_usd,total_supply,percent_change_1h, percent_change_24h, percent_change_7d, last_updated, notes)
            VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?)");

                try {
                    $sql->execute(array($timestamp,$coinCode,$coinName,$coinCode,$price,$volume,$marketCap,$supply,$perc1h, $perc24h, $perc7d,time(), 'histdata'));
                } catch (Exception $e) {
                    echo 'Insert into coins not working: ',  $e->getMessage(), "\n";
                }
            }

        }
        else
        {
            //break;
        }
        $i++;
    }
    echo $url . "(".$timestamp.") ready.<br/>";

}
$urls = array
(
        /*
    'https://coinmarketcap.com/historical/20130428/',
    'https://coinmarketcap.com/historical/20130505/',
    'https://coinmarketcap.com/historical/20130512/',
    'https://coinmarketcap.com/historical/20130519/',
    'https://coinmarketcap.com/historical/20130526/',
    'https://coinmarketcap.com/historical/20130602/',
    'https://coinmarketcap.com/historical/20130609/',
    'https://coinmarketcap.com/historical/20130616/',
    'https://coinmarketcap.com/historical/20130623/',
    'https://coinmarketcap.com/historical/20130630/',
    'https://coinmarketcap.com/historical/20130707/',
    'https://coinmarketcap.com/historical/20130714/',
    'https://coinmarketcap.com/historical/20130721/',
    'https://coinmarketcap.com/historical/20130728/',
    'https://coinmarketcap.com/historical/20130804/',
    'https://coinmarketcap.com/historical/20130811/',
    'https://coinmarketcap.com/historical/20130818/',
    'https://coinmarketcap.com/historical/20130825/',
    'https://coinmarketcap.com/historical/20130901/',
    'https://coinmarketcap.com/historical/20130908/',
    'https://coinmarketcap.com/historical/20130915/',
    'https://coinmarketcap.com/historical/20130922/',
    'https://coinmarketcap.com/historical/20130929/',
    'https://coinmarketcap.com/historical/20131006/',
    'https://coinmarketcap.com/historical/20131013/',
    'https://coinmarketcap.com/historical/20131020/',
    'https://coinmarketcap.com/historical/20131027/',
    'https://coinmarketcap.com/historical/20131103/',
    'https://coinmarketcap.com/historical/20131110/',
    'https://coinmarketcap.com/historical/20131117/',
    'https://coinmarketcap.com/historical/20131124/',
    'https://coinmarketcap.com/historical/20131201/',
    'https://coinmarketcap.com/historical/20131208/',
    'https://coinmarketcap.com/historical/20131215/',
    'https://coinmarketcap.com/historical/20131222/',
    'https://coinmarketcap.com/historical/20131229/',



    'https://coinmarketcap.com/historical/20140105/',
    'https://coinmarketcap.com/historical/20140112/',
    'https://coinmarketcap.com/historical/20140119/',
    'https://coinmarketcap.com/historical/20140126/',
    'https://coinmarketcap.com/historical/20140202/',
    'https://coinmarketcap.com/historical/20140209/',
    'https://coinmarketcap.com/historical/20140216/',
    'https://coinmarketcap.com/historical/20140223/',
    'https://coinmarketcap.com/historical/20140302/',
    'https://coinmarketcap.com/historical/20140309/',
    'https://coinmarketcap.com/historical/20140316/',
    'https://coinmarketcap.com/historical/20140323/',
    'https://coinmarketcap.com/historical/20140330/',
    'https://coinmarketcap.com/historical/20140406/',
    'https://coinmarketcap.com/historical/20140413/',
    'https://coinmarketcap.com/historical/20140420/',
    'https://coinmarketcap.com/historical/20140427/',
    'https://coinmarketcap.com/historical/20140504/',
    'https://coinmarketcap.com/historical/20140511/',
    'https://coinmarketcap.com/historical/20140518/',
    'https://coinmarketcap.com/historical/20140525/',
    'https://coinmarketcap.com/historical/20140601/',
    'https://coinmarketcap.com/historical/20140608/',
    'https://coinmarketcap.com/historical/20140615/',
    'https://coinmarketcap.com/historical/20140622/',
    'https://coinmarketcap.com/historical/20140629/',
    'https://coinmarketcap.com/historical/20140706/',
    'https://coinmarketcap.com/historical/20140713/',
    'https://coinmarketcap.com/historical/20140720/',
    'https://coinmarketcap.com/historical/20140727/',
    'https://coinmarketcap.com/historical/20140803/',
    'https://coinmarketcap.com/historical/20140810/',
    'https://coinmarketcap.com/historical/20140817/',
    'https://coinmarketcap.com/historical/20140824/',
    'https://coinmarketcap.com/historical/20140831/',
    'https://coinmarketcap.com/historical/20140907/',
    'https://coinmarketcap.com/historical/20140914/',
    'https://coinmarketcap.com/historical/20140921/',
    'https://coinmarketcap.com/historical/20140928/',
    'https://coinmarketcap.com/historical/20141005/',
    'https://coinmarketcap.com/historical/20141012/',
    'https://coinmarketcap.com/historical/20141019/',
    'https://coinmarketcap.com/historical/20141026/',
    'https://coinmarketcap.com/historical/20141102/',
    'https://coinmarketcap.com/historical/20141109/',
    'https://coinmarketcap.com/historical/20141116/',
    'https://coinmarketcap.com/historical/20141123/',
    'https://coinmarketcap.com/historical/20141130/',
    'https://coinmarketcap.com/historical/20141207/',
    'https://coinmarketcap.com/historical/20141214/',
    'https://coinmarketcap.com/historical/20141221/',
    'https://coinmarketcap.com/historical/20141228/',
    'https://coinmarketcap.com/historical/20150104/',
    'https://coinmarketcap.com/historical/20150111/',
    'https://coinmarketcap.com/historical/20150118/',
    'https://coinmarketcap.com/historical/20150125/',
    'https://coinmarketcap.com/historical/20150201/',
    'https://coinmarketcap.com/historical/20150208/',
    'https://coinmarketcap.com/historical/20150215/',
    'https://coinmarketcap.com/historical/20150222/',
    'https://coinmarketcap.com/historical/20150301/',
    'https://coinmarketcap.com/historical/20150308/',
    'https://coinmarketcap.com/historical/20150315/',
    'https://coinmarketcap.com/historical/20150322/',
    'https://coinmarketcap.com/historical/20150329/',
    'https://coinmarketcap.com/historical/20150405/',
    'https://coinmarketcap.com/historical/20150412/',
    'https://coinmarketcap.com/historical/20150419/',
    'https://coinmarketcap.com/historical/20150426/',
    'https://coinmarketcap.com/historical/20150503/',
    'https://coinmarketcap.com/historical/20150510/',
    'https://coinmarketcap.com/historical/20150517/',
    'https://coinmarketcap.com/historical/20150524/',
    'https://coinmarketcap.com/historical/20150531/',
    'https://coinmarketcap.com/historical/20150607/',
    'https://coinmarketcap.com/historical/20150614/',
    'https://coinmarketcap.com/historical/20150621/',
    'https://coinmarketcap.com/historical/20150628/',
    'https://coinmarketcap.com/historical/20150705/',
    'https://coinmarketcap.com/historical/20150712/',
    'https://coinmarketcap.com/historical/20150719/',
    'https://coinmarketcap.com/historical/20150726/',
    'https://coinmarketcap.com/historical/20150802/',
    'https://coinmarketcap.com/historical/20150809/',
    'https://coinmarketcap.com/historical/20150816/',
    'https://coinmarketcap.com/historical/20150823/',
    'https://coinmarketcap.com/historical/20150830/',
    'https://coinmarketcap.com/historical/20150906/',
    'https://coinmarketcap.com/historical/20150913/',
    'https://coinmarketcap.com/historical/20150920/',
    'https://coinmarketcap.com/historical/20150927/',
    'https://coinmarketcap.com/historical/20151004/',
    'https://coinmarketcap.com/historical/20151011/',
    'https://coinmarketcap.com/historical/20151018/',
    'https://coinmarketcap.com/historical/20151025/',
    'https://coinmarketcap.com/historical/20151101/',
    'https://coinmarketcap.com/historical/20151108/',
    'https://coinmarketcap.com/historical/20151115/',
    'https://coinmarketcap.com/historical/20151122/',
    'https://coinmarketcap.com/historical/20151129/',
    'https://coinmarketcap.com/historical/20151206/',
    'https://coinmarketcap.com/historical/20151213/',
    'https://coinmarketcap.com/historical/20151220/',
    'https://coinmarketcap.com/historical/20151227/',
    'https://coinmarketcap.com/historical/20160103/',
    'https://coinmarketcap.com/historical/20160110/',
    'https://coinmarketcap.com/historical/20160117/',
    'https://coinmarketcap.com/historical/20160124/',
    'https://coinmarketcap.com/historical/20160131/',
    'https://coinmarketcap.com/historical/20160207/',
    'https://coinmarketcap.com/historical/20160214/',
    'https://coinmarketcap.com/historical/20160221/',
    'https://coinmarketcap.com/historical/20160228/',
    'https://coinmarketcap.com/historical/20160306/',
    'https://coinmarketcap.com/historical/20160313/',
    'https://coinmarketcap.com/historical/20160320/',
    'https://coinmarketcap.com/historical/20160327/',
    'https://coinmarketcap.com/historical/20160403/',
    'https://coinmarketcap.com/historical/20160410/',
    'https://coinmarketcap.com/historical/20160417/',
    'https://coinmarketcap.com/historical/20160424/',
    'https://coinmarketcap.com/historical/20160501/',
    'https://coinmarketcap.com/historical/20160508/',
    'https://coinmarketcap.com/historical/20160515/',
    'https://coinmarketcap.com/historical/20160522/',
    'https://coinmarketcap.com/historical/20160529/',
    'https://coinmarketcap.com/historical/20160605/',
    'https://coinmarketcap.com/historical/20160612/',
    'https://coinmarketcap.com/historical/20160619/',
    'https://coinmarketcap.com/historical/20160626/',
    'https://coinmarketcap.com/historical/20160703/',
    'https://coinmarketcap.com/historical/20160710/',
    'https://coinmarketcap.com/historical/20160717/',
    'https://coinmarketcap.com/historical/20160724/',
    'https://coinmarketcap.com/historical/20160731/',
    'https://coinmarketcap.com/historical/20160807/',
    'https://coinmarketcap.com/historical/20160814/',

    'https://coinmarketcap.com/historical/20160821/',
    'https://coinmarketcap.com/historical/20160828/',
    'https://coinmarketcap.com/historical/20160904/',
    'https://coinmarketcap.com/historical/20160911/',
    'https://coinmarketcap.com/historical/20160918/',
    'https://coinmarketcap.com/historical/20160925/',
    'https://coinmarketcap.com/historical/20161002/',
        */
    'https://coinmarketcap.com/historical/20161009/',
    'https://coinmarketcap.com/historical/20161016/',
    'https://coinmarketcap.com/historical/20161023/',
    'https://coinmarketcap.com/historical/20161030/',
    'https://coinmarketcap.com/historical/20161106/',
    'https://coinmarketcap.com/historical/20161113/',
    'https://coinmarketcap.com/historical/20161120/',
    'https://coinmarketcap.com/historical/20161127/',
    'https://coinmarketcap.com/historical/20161204/',
    'https://coinmarketcap.com/historical/20161211/',
    'https://coinmarketcap.com/historical/20161218/',
    'https://coinmarketcap.com/historical/20161225/',
    'https://coinmarketcap.com/historical/20170101/',
    'https://coinmarketcap.com/historical/20170108/',
    'https://coinmarketcap.com/historical/20170115/',
    'https://coinmarketcap.com/historical/20170122/',
    'https://coinmarketcap.com/historical/20170129/',
    'https://coinmarketcap.com/historical/20170205/',
    'https://coinmarketcap.com/historical/20170212/',
    'https://coinmarketcap.com/historical/20170219/',
    'https://coinmarketcap.com/historical/20170226/',
    'https://coinmarketcap.com/historical/20170305/',
    'https://coinmarketcap.com/historical/20170312/',
    'https://coinmarketcap.com/historical/20170319/',
    'https://coinmarketcap.com/historical/20170326/',
    'https://coinmarketcap.com/historical/20170402/',
    'https://coinmarketcap.com/historical/20170409/',
    'https://coinmarketcap.com/historical/20170416/',
    'https://coinmarketcap.com/historical/20170423/',
    'https://coinmarketcap.com/historical/20170430/',
    'https://coinmarketcap.com/historical/20170507/',
    'https://coinmarketcap.com/historical/20170514/',
    'https://coinmarketcap.com/historical/20170521/',
    'https://coinmarketcap.com/historical/20170528/',
    'https://coinmarketcap.com/historical/20170604/',
    'https://coinmarketcap.com/historical/20170611/', 
    'https://coinmarketcap.com/historical/20170618/',
    'https://coinmarketcap.com/historical/20170625/',
    'https://coinmarketcap.com/historical/20170702/',
    'https://coinmarketcap.com/historical/20170709/',
    'https://coinmarketcap.com/historical/20170716/',
    'https://coinmarketcap.com/historical/20170723/',
    'https://coinmarketcap.com/historical/20170730/',
    'https://coinmarketcap.com/historical/20170806/',
    'https://coinmarketcap.com/historical/20170813/',
    'https://coinmarketcap.com/historical/20170820/',
    'https://coinmarketcap.com/historical/20170827/',
    'https://coinmarketcap.com/historical/20170903/',
    'https://coinmarketcap.com/historical/20170910/',
    'https://coinmarketcap.com/historical/20170917/',
    'https://coinmarketcap.com/historical/20170924/',
    'https://coinmarketcap.com/historical/20171001/',
    'https://coinmarketcap.com/historical/20171008/',
    'https://coinmarketcap.com/historical/20171015/',
    'https://coinmarketcap.com/historical/20171022/',
    'https://coinmarketcap.com/historical/20171029/',
    'https://coinmarketcap.com/historical/20171105/',
    'https://coinmarketcap.com/historical/20171112/',
    'https://coinmarketcap.com/historical/20171119/',
    'https://coinmarketcap.com/historical/20171126/',
    'https://coinmarketcap.com/historical/20171203/',
    'https://coinmarketcap.com/historical/20171210/',
    'https://coinmarketcap.com/historical/20171217/',
    'https://coinmarketcap.com/historical/20171224/',
    'https://coinmarketcap.com/historical/20171231/',
    'https://coinmarketcap.com/historical/20180107/',
    'https://coinmarketcap.com/historical/20180114/',
    'https://coinmarketcap.com/historical/20180121/',
    'https://coinmarketcap.com/historical/20180128/',
    'https://coinmarketcap.com/historical/20180204/',
    'https://coinmarketcap.com/historical/20180211/',
    'https://coinmarketcap.com/historical/20180218/'
);
foreach ($urls as $url)
{
    scrapeHistData($url);
}

?>

