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
if (!$dates_res) die('Invalid query: ' . mysql_error());
$current_data = mysql_fetch_object($current_res);
if (!$row) die("No data!");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
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
										echo ", " . ($row->hasdata ? ($row->grid_pow) : "null");
									}
								?>
							];

		var power_total_start = Date.UTC(<?php echo $ts_year . ", " . $ts_month . ", " . $ts_day; ?>);
		var power_total_data = [
									<?php					
										//echo $powtotstart;
										$ptot = $powtotstart;
										$i = 0;
										while ($row = mysql_fetch_object($dates_res)) {
											if ($i > 0) echo ", ";
											++$i;
											echo ($row->total_pow / 100.) - $ptot;
											$ptot = $row->total_pow / 100.;
										}
										if ($i == 0) echo "null"; // Happens ons first day this script runs
									?>
								];
		
		// This in seperate file?
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
					Javascript needs to be turned on for
					this page.
				</div> 
			</noscript> 
			
			<!--[if lt IE 7]>
				<div class="error">
					Internet Explorer 6 and lower is not
					supported.
				</div>
			<![endif]--> 
		</div> 
		
		<div id="intro">
			<p>Hieronder enkele grafieken van de opbrengsten van de Soladin 600 panelen.</p>
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
					<th colspan="2">
						Actuele data
					</th>
					<tr>
						<td>Datum/Tijd</td>
						<td><?php echo $current_data->time; ?></td>
					</tr>
					<tr>
						<td>Flags</td> <!-- TODO!! -->
						<td><?php echo $current_data->flags; ?></td>
					</tr>
					<tr>
						<td>PV voltage</td>
						<td><?php echo $current_data->pv_volt / 10.; ?> V</td>
					</tr>
					<tr>
						<td>PV amperage</td>
						<td><?php echo $current_data->pv_amp / 100.; ?> A</td>
					</tr>
					<tr>
						<td>Grid frequentie</td>
						<td><?php echo $current_data->grid_freq / 100.; ?> Hz</td>
					</tr>
					<tr>
						<td>Grid voltage</td>
						<td><?php echo $current_data->grid_volt; ?> V</td>
					</tr>
					<tr>
						<td>Grid vermogen</td>
						<td><?php echo $current_data->grid_pow; ?> W</td>
					</tr>
					<tr>
						<td>Totaal vermogen</td>
						<td><?php echo $current_data->total_pow / 100.; ?> kWh</td>
					</tr>
					<tr>
						<td>Temperatuur</td>
						<td><?php echo $current_data->temp; ?> &deg;C</td>
					</tr>
					<tr>
						<td>Tijd actief</td>
						<td><?php echo $current_data->optime; ?> min</td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</body>
</html>

