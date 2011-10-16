<?php
error_reporting(0);
if (!include("config.php"))
    die("config.php not found! Copy config.example.php for a template.");
    
if (!include("functions.php"))
    die("functions.php not found!");
    
error_reporting(-1);

mysql_connect($db_host, $db_user, $db_password) or die("Could not connect to database!");
mysql_select_db($db_database) or die("Could not find database!");

// All data for first graph
$res = mysql_query("
SELECT *
FROM `$db_table_solar`
WHERE DATEDIFF(CURDATE(), `time`) < 2
ORDER BY `time`");
if (!$res) die('Invalid query: ' . mysql_error());
// This is fucked up... I need the first date/time for the graph... So we fetch
// the first row here, and the rest of the rows are read and directly printed
// where it is neceserry.
$row = mysql_fetch_object($res);
if (!$row) die("No data!");
$ts =       explode(" ", $row->time);
$ts_date =  explode("-", $ts[0]);
$ts_time =  explode(":", $ts[1]);
$ts_year =  $ts_date[0];
$ts_month = $ts_date[1]-1; // JS months are zero based... seriously...?
$ts_day =   $ts_date[2];
$ts_hour =  $ts_time[0];
$ts_min =   $ts_time[1];
$ts_sec =   $ts_time[2];
$powstart = $row->hasdata ? $row->grid_pow : "-30";

$current_res = mysql_query("
SELECT *
FROM `$db_table_solar`
ORDER BY `time` DESC
LIMIT 1");
if (!$current_res) die('Invalid query: ' . mysql_error());
$current_data = mysql_fetch_object($current_res);
if (!$row) die("No data!");

// Peak power of today
$peak_pow_res = mysql_query("
SELECT `grid_pow`
FROM `$db_table_solar`
WHERE DATE(`time`) = CURDATE()
ORDER BY `grid_pow` DESC
LIMIT 1");
if (!$peak_pow_res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$peak_pow = mysql_fetch_object($peak_pow_res)->grid_pow;
if (!is_numeric($peak_pow)) die("{\"error\": \"No data! 1\"}");

// Ammount of stuff we collected today
$today_pow_res = mysql_query("
SELECT `total_pow`
FROM `$db_table_solar`
WHERE DATE(`time`) = CURDATE() AND
      HOUR(`time`) = 0 AND
      MINUTE(`time`) < 5");
if (!$today_pow_res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$start_pow = mysql_fetch_object($today_pow_res)->total_pow;
if (!$start_pow) die("{\"error\": \"No data! 2\"}");

$today_pow = ($current_data->total_pow - $start_pow) / 100.;


// Year range (+their min/max months)
$yearrange_res = mysql_query("
SELECT YEAR(MIN(`time`)) AS `lyear`,
       YEAR(MAX(`time`)) AS `hyear`,
       MONTH(MIN(`time`)) AS `lmonth`,
       MONTH(MAX(`time`)) AS `hmonth`
FROM `$db_table_solar`");
$yearrange = mysql_fetch_object($yearrange_res);

// Stuff for day mode
$day_month = $yearrange->hmonth;
$day_year = $yearrange->hyear;
$day_res = mysql_query(
"SELECT DAYOFMONTH(`time`) AS `day`,
        MAX(`total_pow`) - MIN(`total_pow`) AS `pow`,
        MAX(`grid_pow`) as `peak_pow`
FROM `$db_table_solar` 
WHERE YEAR(`time`) = $day_year AND
      MONTH(`time`) = $day_month
GROUP BY `day`
ORDER BY `day` ASC");

$row = mysql_fetch_object($day_res);

$day_pow_data = Array();
$day_peakpow_data = Array();

// Fill up places before first day (if needed)
for ($i = 1; $i < $row->day; $i++) {
    $day_pow_data[] = 0;
    $day_peakpow_data[] = 0;
}

$day_pow_data[] = $row->pow / 100.;
$day_peakpow_data[] = $row->peak_pow;

while ($row = mysql_fetch_object($day_res)) {
    $day_pow_data[] = $row->pow / 100.;
    $day_peakpow_data[] = $row->peak_pow;
}

// Fill up places after last day (if needed)
for ($i = count($day_pow_data); $i < date('t', mktime(0, 0, 0, $yearrange->hmonth, 1, $yearrange->hyear)); $i++) {
    $day_pow_data[] = 0;
    $day_peakpow_data[] = 0;
}

// Stuff for week mode
$week_year = $yearrange->hyear;
$week_res = mysql_query(
"SELECT WEEK(`time`, 1) AS `week`,
        MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
FROM  `$db_table_solar` 
WHERE YEAR(`time`) = $week_year
GROUP BY WEEK(`time`, 1) 
ORDER BY `week` ASC");

// Stuff for month mode
$month_year = $yearrange->hyear;
$month_res = mysql_query(
"SELECT MONTH(`time`) - 1 AS `month`,
        MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
FROM  `$db_table_solar` 
WHERE YEAR(`time`) = $month_year
GROUP BY month(`time`) 
ORDER BY `month` ASC");

// Last flags table
$flags_res = mysql_query("
SELECT `time`, `flags`
FROM `$db_table_solar`
WHERE `flags` > 0
ORDER BY `time` DESC
LIMIT 5");

// Moneyz
$money_res = mysql_query("
SELECT SUM(`t`.`pow`) AS `money`
FROM (SELECT 1
                AS `temp`,
             IF(WEEKDAY(`time`) >= 5, 
                    $power_cost_low,
                    IF((SELECT COUNT(`day`) AS `num`
                        FROM `$db_table_holidays`
                        WHERE `day`=DATE(`time`)) > 0,
                            $power_cost_low,
                            $power_cost_normal)
                    ) * ((MAX(total_pow) - MIN(total_pow)) / 100.0)
                AS `pow`
      FROM `$db_table_solar`
      GROUP BY DATE(`time`)) 
        AS `t`
GROUP BY `temp`");
$money = mysql_fetch_object($money_res)->money;

// Todays euro per kWh
$today_mon_res = mysql_query("
SELECT 
   IF(WEEKDAY(CURDATE()) >= 5,
    $power_cost_low,
    IF((SELECT COUNT(`day`) AS `num` FROM `$db_table_holidays` WHERE `day`=CURDATE()) > 0,
     $power_cost_low,
     $power_cost_normal))
AS `mon`");
$money_today = $today_pow * mysql_fetch_object($today_mon_res)->mon;

// Resol stats (3 temps, 1 pump)
$resol_res = mysql_query("
SELECT `time`, `t1`, `t2`, `t3`, `p1`
FROM `$db_table_resol`
WHERE DATEDIFF(CURDATE(), `time`) < 2
ORDER BY `time`");
if (!$resol_res) die('Invalid query: ' . mysql_error());
// Same as first one: we need starting data and stuff
$row = mysql_fetch_object($resol_res);
if (!$row) die("No data!");
$resol_ts =       explode(" ", $row->time);
$resol_ts_date =  explode("-", $resol_ts[0]);
$resol_ts_time =  explode(":", $resol_ts[1]);
$resol_ts_year =  $resol_ts_date[0];
$resol_ts_month = $resol_ts_date[1]-1; // JS months are zero based... seriously...?
$resol_ts_day =   $resol_ts_date[2];
$resol_ts_hour =  $resol_ts_time[0];
$resol_ts_min =   $resol_ts_time[1];
$resol_ts_sec =   $resol_ts_time[2];
$resol_t1_data = Array($row->t1/10.);
$resol_t2_data = Array($row->t2/10.);
$resol_t3_data = Array($row->t3/10.);
$resol_p1_data = Array($row->p1);
while ($row = mysql_fetch_object($resol_res)) {
    $resol_t1_data[] = $row->t1/10.;
    $resol_t2_data[] = $row->t2/10.;
    $resol_t3_data[] = $row->t3/10.;
    $resol_p1_data[] = $row->p1;
}

// lazy
$resol_cur_res = mysql_query("
SELECT `time`, `t1`, `t2`, `t3`, `p1`
FROM `$db_table_resol`
ORDER BY `time` DESC
LIMIT 1");
if (!$resol_cur_res) die('Invalid query: ' . mysql_error());
$resol_current_data = mysql_fetch_object($resol_cur_res);
if (!$resol_current_data) die("No data!");


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="nl" lang="nl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" /> 
    
    <link type="text/css" href="css/smoothness/jquery-ui-1.8.7.custom.css" rel="stylesheet" />    
    <link type="text/css" href="css/layout.css" rel="stylesheet"/> 
    <link type="text/css" href="css/colorbox.css" rel="stylesheet"/>

    
    <script src="js/jquery-1.4.4.min.js" type="text/javascript"></script>
    <script src="js/jquery-ui-1.8.7.custom.min.js" type="text/javascript"></script>
    <script src="js/highcharts.js" type="text/javascript"></script>
    <script src="js/jquery.colorbox-min.js" type="text/javascript"></script>
    
    <script src="js/solargraphs.js" type="text/javascript"></script>
    <script src="js/imagepopup.js" type="text/javascript"></script>
    <script src="js/navbuttons.js" type="text/javascript"></script>
    <script src="js/refreshdata.js" type="text/javascript"></script>
    
    <script type="text/javascript">
        // PHP inserts data here
        var lyear = <?php echo $yearrange->lyear; ?>;
        var hyear = <?php echo $yearrange->hyear; ?>;
        var lmonth = <?php echo $yearrange->lmonth - 1; ?>;
        var hmonth = <?php echo $yearrange->hmonth - 1; ?>;
        
        var month_map = ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'];
        function day_cur_month_length() {
            return new Date(day_cur_year, day_cur_month + 1, 0).getDate();
        }
        
        var day_cur_month = hmonth;
        var day_cur_year = hyear;
        var week_cur_year = hyear;
        var month_cur_year = hyear;
        
        var power_day_start = Date.UTC(<?php echo $ts_year . ", " . $ts_month . ", " . $ts_day . ", " . $ts_hour . ", " . $ts_min . ", " . $ts_sec ?>);
        var power_day_data = [
                                <?php 
                                    echo $powstart;
                                    while ($row = mysql_fetch_object($res)) {
                                        echo ", " . ($row->hasdata ? ($row->grid_pow) : "-30");
                                    }
                                ?>
                            ];
                                
        var day_total_start = Date.UTC(day_cur_year, day_cur_month, 1);
        var day_total_data = [<?php echo implode(',', $day_pow_data); ?>];
        var day_peak_data = [<?php echo implode(',', $day_peakpow_data); ?>];
                                
        var week_total_data = [
                                    <?php
                                        $c = 0;
                                        while ($row = mysql_fetch_object($week_res)) {
                                            if ($c > 0)
                                                echo ", ";
                                            elseif ($c == 0 && $row->week > 0)
                                                while ($c < $row->week) {
                                                    echo "[$c, 0], ";
                                                    ++$c;
                                                }
                                            
                                            $p = $row->pow / 100.;
                                            echo "[$c, $p]";
                                            ++$c;
                                        }
                                        
                                        while ($c < 53) {
                                            if ($c > 0)
                                                echo ", ";
                                            echo "[$c, 0]";
                                            ++$c;
                                        }
                                    ?>
                                ];
                                
        var month_total_data = [
                                    <?php
                                        $c = 0;
                                        while ($row = mysql_fetch_object($month_res)) {
                                            if ($c > 0)
                                                echo ", ";
                                            elseif ($c == 0 && $row->month > 0)
                                                while ($c < $row->month) {
                                                    echo "0, ";
                                                    ++$c;
                                                }
                                            
                                            $p = $row->pow / 100.;
                                            echo $p;
                                            ++$c;
                                        }
                                        
                                        while ($c < 12) {
                                            if ($c > 0)
                                                echo ", ";
                                            echo "0";
                                            ++$c;
                                        }
                                    ?>
                                ];
                                
        var resol_start = Date.UTC(<?php echo $resol_ts_year . ", " . $resol_ts_month . ", " . $resol_ts_day . ", " . $resol_ts_hour . ", " . $resol_ts_min . ", " . $resol_ts_sec ?>);
        var resol_t1_data = [
                                <?php
                                    echo implode(',', $resol_t1_data);
                                ?>
                            ];
        var resol_t2_data = [
                                <?php
                                    echo implode(',', $resol_t2_data);
                                ?>
                            ];
        var resol_t3_data = [
                                <?php
                                    echo implode(',', $resol_t3_data);
                                ?>
                            ];
        var resol_p1_data = [
                                <?php
                                    echo implode(',', $resol_p1_data);
                                ?>
                            ];
        
        
        
        // Create the JS tabs
        $(document).ready(function() {
            $("#tabs").tabs();
        });
    </script>

    <title>Zonnepanelen data</title>
</head>
<body>
    <div id="wrapper">
        <div id="header">
            <h1>Zonnecellen data</h1>
        </div>
        
        <div id="notify"> 
            <noscript> 
                <div class="error"> 
                    Javascript needs to be turned on for this page.
                </div> 
            </noscript> 
            
            <!--[if lt IE 7]>
                <div class="error">
                    Internet Explorer 6 and lower is not supported.
                </div>
            <![endif]--> 
        </div> 
        
        <div id="intro">
            <p>Hieronder enkele grafieken van de opbrengsten van de Soladin 600 met 3 panelen met een piekvermogen van 615 Wp. Ook staan hier de temperaturen van de zonneboiler.</p>
            <p>Foto's:
                <a href="solar_old.png" title="Zonnepanelen (oud)" rel="imgs">Zonnepanelen (oud)</a>
                <a href="solar_new.png" title="Zonnepanelen (nieuw)" rel="imgs">Zonnepanelen (nieuw)</a>
                <a href="resol.png" title="Zonneboiler" rel="imgs">Zonneboiler</a>
        </div>
        
        <!-- jQuery ui styles this to clickable tabs -->
        <div id="tabs">
            <ul>
                <li><a href="#tab_g_detailpower">Vermogen</a></li>
                <li><a href="#tab_g_daytotals">Dag totaal</a></li>
                <li><a href="#tab_g_weektotals">Week totaal</a></li>
                <li><a href="#tab_g_monthtotals">Maand totaal</a></li>
                <li><a href="#tab_g_resol">Zonneboiler</a></li>
                <li><a href="#tab_t_current">Huidige status</a></li>
            </ul>
            <div id="tab_g_detailpower">
                <div id="power_day" style="width: 800px; height: 400px"></div>
            </div>
            <div id="tab_g_daytotals">
                <div id="power_total" style="width: 800px; height: 400px"></div>
                
                <table class="period_nav" id="day_month_nav">
                    <tr>
                        <td><a href="#" id="day_month_nav_prev">&larr;</a></td>
                        <td id="day_month_nav_cur">Januari</td>
                        <td><a href="#" id="day_month_nav_next">&rarr;</a></td>
                    </tr>
                </table>
                
                <table class="period_nav" id="day_year_nav">
                    <tr>
                        <td><a href="#" id="day_year_nav_prev">&larr;</a></td>
                        <td id="day_year_nav_cur">2011</td>
                        <td><a href="#" id="day_year_nav_next">&rarr;</a></td>
                    </tr>
                </table>
                
                <div class="clear"></div>
            </div>
            <div id="tab_g_weektotals">
                <div id="week_total" style="width: 800px; height: 400px"></div>
                
                <table class="period_nav" id="week_year_nav">
                    <tr>
                        <td><a href="#" id="week_year_nav_prev">&larr;</a></td>
                        <td id="week_year_nav_cur">2011</td>
                        <td><a href="#" id="week_year_nav_next">&rarr;</a></td>
                    </tr>
                </table>
                
                <div class="clear"></div>
                
            </div>
            <div id="tab_g_monthtotals">
                <div id="month_total" style="width: 800px; height: 400px"></div>
                
                <table class="period_nav" id="month_year_nav">
                    <tr>
                        <td><a href="#" id="month_year_nav_prev">&larr;</a></td>
                        <td id="month_year_nav_cur">2011</td>
                        <td><a href="#" id="month_year_nav_next">&rarr;</a></td>
                    </tr>
                </table>
                
                <div class="clear"></div>
                
            </div>
            <div id="tab_g_resol">
                <div id="resol_graph" style="width: 800px; height: 400px"></div>
            </div>
            <div id="tab_t_current">
                <div id="cur_intro">Actuele data</div>
                <br class="clear" />
                <div>
                    <table>
                        <tr>
                            <th colspan="2">Zonnecellen</th>
                        </tr>
                        <tr>
                            <td>Datum/Tijd</td>
                            <td><span id="ct_time"><?php echo $current_data->time; ?></span></td>
                        </tr>
                        <tr>
                            <td>PV voltage</td>
                            <td><span id="ct_pv_volt"><?php echo $current_data->pv_volt / 10.; ?> V</span></td>
                        </tr>
                        <tr>
                            <td>PV amperage</td>
                            <td><span id="ct_pv_amp"><?php echo $current_data->pv_amp / 100.; ?> A</span></td>
                        </tr>
                        <tr>
                            <td>Grid frequentie</td>
                            <td><span id="ct_grid_freq"><?php echo $current_data->grid_freq / 100.; ?> Hz</span></td>
                        </tr>
                        <tr>
                            <td>Grid voltage</td>
                            <td><span id="ct_grid_volt"><?php echo $current_data->grid_volt; ?> V</span></td>
                        </tr>
                        <tr>
                            <td>Grid vermogen</td>
                            <td><span id="ct_grid_pow"><?php echo $current_data->grid_pow; ?> W</span>
                                <span id="ct_peak_pow" class="add_today"><?php echo $peak_pow; ?> W piek vandaag</span></td>
                        </tr>
                        <tr>
                            <td>Totaal vermogen</td>
                            <td><span id="ct_total_pow"><?php echo $current_data->total_pow / 100.; ?> kWh</span>
                                <span id="ct_today_pow" class="add_today"><?php echo $today_pow; ?> kWh vandaag</span></td>
                        </tr>
                        <tr>
                            <td>Opbrengst euro's</td>
                            <td><span id="ct_total_money">&euro;<?php echo sprintf('%.2f', $money); ?></span>
                                <span id="ct_today_money" class="add_today">&euro;<?php echo sprintf('%.2f', $money_today); ?> vandaag</span></td>
                        </tr>
                        <tr>
                            <td>Temperatuur</td>
                            <td><span id="ct_temp"><?php echo $current_data->temp; ?> &deg;C</span></td>
                        </tr>
                        <tr>
                            <td>Tijd actief</td>
                            <td><span id="ct_optime"><?php echo mins2verbose($current_data->optime); ?></span></td>
                        </tr>
                    </table>
                </div>
                <div class="seperator"></div>
                <div>
                    <table>
                        <tr>
                            <th colspan="2">Zonneboiler</th>
                        </tr>
                        <tr>
                            <td>Datum/Tijd</td>
                            <td><span id="ct_resol_time"><?php echo $resol_current_data->time; ?></span></td>
                        </tr>
                        <tr>
                            <td>Temp. Panelen</td>
                            <td><span id="ct_resol_t1"><?php echo $resol_current_data->t1 / 10.; ?> &deg;C</span></td>
                        </tr>
                        <tr>
                            <td>Temp. Boiler</td>
                            <td><span id="ct_resol_t2"><?php echo $resol_current_data->t2 / 10.; ?> &deg;C</span></td>
                        </tr>
                        <tr>
                            <td>Temp. Zwembad</td>
                            <td><span id="ct_resol_t3"><?php echo $resol_current_data->t3 / 10.; ?> &deg;C</span></td>
                        </tr>
                        <tr>
                            <td>Pomp</td>
                            <td><span id="ct_resol_p1"><?php echo $resol_current_data->p1; ?>%</span></td>
                        </tr>
                    </table>
                </div>
                
                
                <br class="clear" />
                
                <?php if (mysql_num_rows($flags_res)): ?>
                <table id="flagstable" style="margin-top: 30px;">
                    <tr>
                        <th colspan="2">Laatste meldingen Soladin</th>
                    </tr>
                    
                    <?php while ($row = mysql_fetch_object($flags_res)): ?>
                        <tr>
                            <td><?php echo $row->time; ?></td>
                            <td><?php echo flags2html($row->flags); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    
                </table>
                <?php endif; ?>
            </div>
        </div>
        <div id="footer">
            
            <a href="http://websvn.chozo.nl/listing.php?repname=dump&path=%2FWeb%2Fsolar%2F">Source code</a><br />
            Made by <a href="http://chozo.nl/">Chozo.nl</a>
        </div>
    </div>
</body>
</html>

