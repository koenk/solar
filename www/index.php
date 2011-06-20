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
$res = mysql_query("SELECT * FROM `stats` WHERE DATEDIFF(CURDATE(), `time`)<2 ORDER BY `time`");
if (!$res) die('Invalid query: ' . mysql_error());
// This is fucked up... I need the first date/time for the graph... So we fetch
// the first row here, and the rest of the rows are read and directly printed
// where it is neceserry.
$row = mysql_fetch_object($res);
if (!$row) die("No data!");
$ts = explode(" ", $row->time);
$ts_date = explode("-", $ts[0]);
$ts_time = explode(":", $ts[1]);
$ts_year = $ts_date[0];
$ts_month =  $ts_date[1]-1; // JS months are zero based... seriously...?
$ts_day = $ts_date[2];
$ts_hour = $ts_time[0];
$ts_min = $ts_time[1];
$ts_sec = $ts_time[2];
$powstart = $row->hasdata ? $row->grid_pow : "-30";


// Request the total data of each day by asking for the data NEXT day, at 00:00:xx
$dates_res = mysql_query("SELECT DATE(`time`),`total_pow` FROM `stats` WHERE HOUR(`time`)=0 AND MINUTE(`time`) < 5 ORDER BY `time` ASC");
if (!$dates_res) die('Invalid query: ' . mysql_error());
$row = mysql_fetch_array($dates_res); // Array because the DATE thing is a bitch with objects?
if (!$row) die("No data!");
$tst_date = explode("-", $row["DATE(`time`)"]);
$tst_year = $tst_date[0];
$tst_month =  $tst_date[1]-1; // JS months are zero based, again...
$tst_day = $tst_date[2];
$powtotstart = $row["total_pow"] / 100.;

// Related to the thing above this... The peak power of each day
$dates_peak_res = mysql_query("SELECT MAX(`grid_pow`) as `peak_pow` FROM `stats` GROUP BY DATE(`time`)");
if (!$dates_peak_res) die("Invalid query: " . mysql_error());
$row = mysql_fetch_object($dates_peak_res);
if (!$row) die("No data!");
$powpeakstart = $row->peak_pow;


$current_res = mysql_query("SELECT * FROM `stats` ORDER BY `time` DESC LIMIT 1");
if (!$current_res) die('Invalid query: ' . mysql_error());
$current_data = mysql_fetch_object($current_res);
if (!$row) die("No data!");

// Peak power of today
$peak_pow_res = mysql_query("SELECT `grid_pow` FROM `stats` WHERE DATE(`time`) = CURDATE() ORDER BY `grid_pow` DESC LIMIT 1");
if (!$peak_pow_res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$peak_pow = mysql_fetch_object($peak_pow_res)->grid_pow;
if (!is_numeric($peak_pow)) die("{\"error\": \"No data! 1\"}");

// Ammount of stuff we collected today
$today_pow_res = mysql_query("SELECT `total_pow` FROM `stats` WHERE DATE(`time`)=CURDATE() AND HOUR(`time`)=0 AND MINUTE(`time`) < 5");
if (!$today_pow_res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$start_pow = mysql_fetch_object($today_pow_res)->total_pow;
if (!$start_pow) die("{\"error\": \"No data! 2\"}");

$today_pow = ($current_data->total_pow - $start_pow) / 100.;


// Year range
$yearrange_res = mysql_query("
SELECT YEAR(MIN(`time`)) AS `lyear`,
       YEAR(MAX(`time`)) AS `hyear` 
FROM `stats`");
$yearrange = mysql_fetch_object($yearrange_res);

// Stuff for week mode
$week_year = $yearrange->hyear;
$week_res = mysql_query(
"SELECT WEEK(`time`, 1) AS `week`,
        MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
FROM  `stats` 
WHERE YEAR(`time`) = $week_year
GROUP BY WEEK(`time`, 1) 
ORDER BY `week` ASC");

// Stuff for week mode
$month_year = $yearrange->hyear;
$month_res = mysql_query(
"SELECT MONTH(`time`) - 1 AS `month`,
        MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
FROM  `stats` 
WHERE YEAR(`time`) = $month_year
GROUP BY month(`time`) 
ORDER BY `month` ASC");

// Last flags table
$flags_res = mysql_query("
SELECT `time`, `flags`
FROM `stats`
WHERE `flags` > 0
ORDER BY `time` DESC
LIMIT 5");

// Moneyz
$money_res = mysql_query("
SELECT SUM(`t`.`pow`) AS `money`
FROM (SELECT 1
                AS `temp`,
             IF(WEEKDAY(`time`) >= 5, 
                    0.1973,
                    IF((SELECT COUNT(`day`) AS `num`
                        FROM `holidays`
                        WHERE `day`=DATE(`time`)) > 0,
                            0.1973,
                            0.2243)
                    ) * ((MAX(total_pow) - MIN(total_pow)) / 100.0)
                AS `pow`
      FROM `stats`
      GROUP BY DATE(`time`)) 
        AS `t`
GROUP BY `temp`");
$money = mysql_fetch_object($money_res)->money;

// Todays euro per kWh
$today_mon_res = mysql_query("
SELECT 
   IF(WEEKDAY(CURDATE()) >= 5,
    0.1973,
    IF((SELECT COUNT(`day`) AS `num` FROM `holidays` WHERE `day`=CURDATE()) > 0,
     0.1973,
     0.2243))
AS `mon`");
$money_today = $today_pow * mysql_fetch_object($today_mon_res)->mon;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="nl" lang="nl">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" /> 
	
	<link type="text/css" href="css/smoothness/jquery-ui-1.8.7.custom.css" rel="stylesheet" />	
	<link type="text/css" href="css/layout.css" rel="stylesheet"/> 
	
	<script src="js/jquery-1.4.4.min.js" type="text/javascript"></script>
	<script src="js/jquery-ui-1.8.7.custom.min.js" type="text/javascript"></script>
	<script src="js/highcharts.js" type="text/javascript"></script>
	<script src="js/solargraphs.js" type="text/javascript"></script>
	<script type="text/javascript">
		// PHP inserts data here
        var lyear = <?php echo $yearrange->lyear; ?>;
        var hyear = <?php echo $yearrange->hyear; ?>;
        
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
		
		var power_total_start = Date.UTC(<?php echo $tst_year . ", " . $tst_month . ", " . $tst_day; ?>);
		var power_total_data = [
									<?php					
										$ptot = $powtotstart;
										while ($row = mysql_fetch_object($dates_res)) {
											echo ($row->total_pow / 100.) - $ptot;
                                            echo ", ";
											$ptot = $row->total_pow / 100.;
										}
                                        echo $today_pow;
									?>
								];
		var power_peak_data = [
									<?php					
										echo $powpeakstart;
										while ($row = mysql_fetch_object($dates_peak_res)) {
											echo ", ";
											echo $row->peak_pow;
                                            //echo ", ";
											//$ptot = $row->total_pow / 100.;
										}
                                        //echo $today_pow;
									?>
								];
                                
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
		
        function update_nav_buttons() {
            if (week_cur_year == lyear)
                $("#week_year_nav_prev").hide();
            else
                $("#week_year_nav_prev").show();
                
            if (week_cur_year == hyear)
                $("#week_year_nav_next").hide();
            else
                $("#week_year_nav_next").show();
                
            if (month_cur_year == lyear)
                $("#month_year_nav_prev").hide();
            else
                $("#month_year_nav_prev").show();
                
            if (month_cur_year == hyear)
                $("#month_year_nav_next").hide();
            else
                $("#month_year_nav_next").show();
                
            $("#week_year_nav_cur").html(week_cur_year);
            $("#month_year_nav_cur").html(month_cur_year);
        }
        
		// This in seperate file?
		$(document).ready(function() {
			$("#tabs").tabs();
            
            update_nav_buttons();
            
            $("#week_year_nav_prev").click(function() {
                week_cur_year -= 1;
                update_nav_buttons();
                
                $.getJSON("json.php", {'action': 'week', 'year': week_cur_year}, 
                    function(data){
                        week_chart.series[0].setData(data.data);
                    }
                );
                
                return false;
            });
            
            $("#week_year_nav_next").click(function() {
                week_cur_year += 1;
                update_nav_buttons();
                
                $.getJSON("json.php", {'action': 'week', 'year': week_cur_year}, 
                    function(data){
                        week_chart.series[0].setData(data.data);
                    }
                );
                
                return false;
            });
            
            $("#month_year_nav_prev").click(function() {
                month_cur_year -= 1;
                update_nav_buttons();
                
                $.getJSON("json.php", {'action': 'month', 'year': month_cur_year}, 
                    function(data){
                        month_chart.series[0].setData(data.data);
                    }
                );
                
                return false;
            });
            
            $("#month_year_nav_next").click(function() {
                month_cur_year += 1;
                update_nav_buttons();
                
                $.getJSON("json.php", {'action': 'month', 'year': month_cur_year}, 
                    function(data){
                        month_chart.series[0].setData(data.data);
                    }
                );
                
                return false;
            });
            
			setInterval(
				function() { 
					$.getJSON("json.php", {'action': 'stats'}, 
						function(data){
							$("#ct_time").html(data.time);
							$("#ct_pv_volt").html(data.pv_volt + " V");
							$("#ct_pv_amp").html(data.pv_amp + " A");
							$("#ct_grid_freq").html(data.grid_freq + " Hz");
							$("#ct_grid_volt").html(data.grid_volt + " V");
							$("#ct_grid_pow").html(data.grid_pow + " W");
							$("#ct_total_pow").html(data.total_pow + " kWh");
							$("#ct_temp").html(data.temp + " &deg;C");
							$("#ct_optime").html(data.optime + " min");
                            $("#ct_peak_pow").html(data.peak_pow + " W piek vandaag");
                            $("#ct_today_pow").html("+" + data.today_pow + " kWh vandaag");
                            
                            // Flags table thing
                            if (data.flags != "")
                                $("#flagstable th").parent().after($("<tr><td>" + data.time + "</td><td>" + data.flags + "</td></tr>"));
                            
							var tdate = data.time.split(" ");
							var ttime = tdate[1].split(":");
							tdate = tdate[0].split("-");
							
							var tday = parseInt(tdate[2]);
							var tmonth = parseInt(tdate[1])-1;
							var tyear = parseInt(tdate[0]);
							var thour = parseInt(ttime[0]);
							var tmin = parseInt(ttime[1]);
							var tsec = parseInt(ttime[2]);
							var newcoords = [Date.UTC(tyear, tmonth, tday, thour, tmin, tsec),data.hasdata ? data.grid_pow : "-30"];
							var series = pow_chart.series[0].data;
							
							// See whether the data is already in the graph
							if (series[series.length-1].x != newcoords[0])
								pow_chart.series[0].addPoint(newcoords, true, true);
						}
					);
				}, 1000 * 60 * 5); // Every 5 minutes
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
			<p>Hieronder enkele grafieken van de opbrengsten van de Soladin 600 met 3 panelen met een piekvermogen van 615 Wp.</p>
		</div>
		
		<!-- jQuery ui styles this to clickable tabs -->
		<div id="tabs">
			<ul>
				<li><a href="#tab_g_detailpower">Vermogen</a></li>
				<li><a href="#tab_g_daytotals">Dag totaal</a></li>
				<li><a href="#tab_g_weektotals">Week totaal</a></li>
				<li><a href="#tab_g_monthtotals">Maand totaal</a></li>
				<li><a href="#tab_t_current">Huidige status</a></li>
			</ul>
			<div id="tab_g_detailpower">
				<div id="power_day" style="width: 800px; height: 400px"></div>
			</div>
			<div id="tab_g_daytotals">
				<div id="power_total" style="width: 800px; height: 400px"></div>
			</div>
            <div id="tab_g_weektotals">
				<div id="week_total" style="width: 800px; height: 400px"></div>
                
                <table id="week_year_nav">
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
                
                <table id="month_year_nav">
                    <tr>
                        <td><a href="#" id="month_year_nav_prev">&larr;</a></td>
                        <td id="month_year_nav_cur">2011</td>
                        <td><a href="#" id="month_year_nav_next">&rarr;</a></td>
                    </tr>
                </table>
                
                <div class="clear"></div>
                
			</div>
			<div id="tab_t_current">
				<table>
					<tr>
						<th colspan="2">Actuele data</th>
					</tr>
					<tr>
						<td>Datum/Tijd</td>
						<td><div id="ct_time"><?php echo $current_data->time; ?></div></td>
					</tr>
					<tr>
						<td>PV voltage</td>
						<td><div id="ct_pv_volt"><?php echo $current_data->pv_volt / 10.; ?> V</div></td>
					</tr>
					<tr>
						<td>PV amperage</td>
						<td><div id="ct_pv_amp"><?php echo $current_data->pv_amp / 100.; ?> A</div></td>
					</tr>
					<tr>
						<td>Grid frequentie</td>
						<td><div id="ct_grid_freq"><?php echo $current_data->grid_freq / 100.; ?> Hz</div></td>
					</tr>
					<tr>
						<td>Grid voltage</td>
						<td><div id="ct_grid_volt"><?php echo $current_data->grid_volt; ?> V</div></td>
					</tr>
					<tr>
						<td>Grid vermogen</td>
						<td><div id="ct_grid_pow"><?php echo $current_data->grid_pow; ?> W</div></td>
                        <td><div id="ct_peak_pow"><?php echo $peak_pow; ?> W piek vandaag</div></td>
					</tr>
					<tr>
						<td>Totaal vermogen</td>
						<td><div id="ct_total_pow"><?php echo $current_data->total_pow / 100.; ?> kWh</div></td>
                        <td><div id="ct_today_pow">+<?php echo $today_pow; ?> kWh vandaag</div></td>
					</tr>
                    <tr>
						<td>Opbrengst euro's</td>
						<td><div id="ct_total_money">&euro;<?php echo sprintf('%.2f', $money); ?></div></td>
                        <td><div id="ct_today_money">&euro;<?php echo sprintf('%.2f', $money_today); ?> vandaag</div></td>
					</tr>
					<tr>
						<td>Temperatuur</td>
						<td><div id="ct_temp"><?php echo $current_data->temp; ?> &deg;C</div></td>
					</tr>
					<tr>
						<td>Tijd actief</td>
						<td><div id="ct_optime"><?php echo mins2verbose($current_data->optime); ?></div></td>
					</tr>
				</table>
                
                <?php if (mysql_num_rows($flags_res)): ?>
                <table id="flagstable" style="margin-top: 30px;">
                    <tr>
                        <th colspan="2">Laatste meldingen</th>
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

