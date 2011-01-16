<?php
error_reporting(0);
if (!include("config.php")) die("config.php not found! Copy config.example.php for a template.");
error_reporting(-1);

mysql_connect($db_host, $db_user, $db_password) or die("Could not connect to database!");
mysql_select_db($db_database) or die("Could not find database!");

// All data for first graph
$res = mysql_query("SELECT * FROM `stats` ORDER BY `time`");
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
$powstart = $row->hasdata ? $row->grid_pow : "null";


// Request the total data of each day by asking for the data NEXT day, at 00:00:xx
$dates_res = mysql_query("SELECT DATE(`time`),`total_pow` FROM `stats` WHERE HOUR(`time`)=0 AND MINUTE(`time`) < 5");
if (!$dates_res) die('Invalid query: ' . mysql_error());
$row = mysql_fetch_array($dates_res); // Array because the DATE thing is a bitch with objects?
if (!$row) die("No data!");
$tst_date = explode("-", $row["DATE(`time`)"]);
$tst_year = $ts_date[0];
$tst_month =  $ts_date[1]-1; // JS months are zero based, again...
$tst_day = $ts_date[2];
$powtotstart = $row["total_pow"] / 100.;


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
		var power_day_start = Date.UTC(<?php echo $ts_year . ", " . $ts_month . ", " . $ts_day . ", " . $ts_hour . ", " . $ts_min . ", " . $ts_sec ?>);
		var power_day_data = [
								<?php 
									echo $powstart;
									while ($row = mysql_fetch_object($res)) {
										echo ", " . ($row->hasdata ? ($row->grid_pow) : "-30");
									}
								?>
							];

		var power_total_start = Date.UTC(<?php echo $ts_year . ", " . $ts_month . ", " . $ts_day; ?>);
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
		
		// This in seperate file?
		$(document).ready(function() {
			$("#tabs").tabs();
			setInterval(
				function() { 
					$.getJSON("json.php", {}, 
						function(data){
							$("#ct_time").html(data.time);
							$("#ct_flags").html(data.flags);
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
				}, 1000 * 60 * 5);
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
				<li><a href="#tab_t_current">Huidige status</a></li>
			</ul>
			<div id="tab_g_detailpower">
				<div id="power_day" style="width: 800px; height: 400px"></div>
			</div>
			<div id="tab_g_daytotals">
				<div id="power_total" style="width: 800px; height: 400px"></div>
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
						<td>Flags</td> <!-- TODO!! -->
						<td><div id="ct_flags"><?php echo $current_data->flags; ?></div></td>
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
						<td>Temperatuur</td>
						<td><div id="ct_temp"><?php echo $current_data->temp; ?> &deg;C</div></td>
					</tr>
					<tr>
						<td>Tijd actief</td>
						<td><div id="ct_optime"><?php echo $current_data->optime; ?> min</div></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</body>
</html>

