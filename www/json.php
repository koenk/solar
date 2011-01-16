<?php
error_reporting(0);
if (!include("config.php")) die("{\"error\": \"config.php not found! Copy config.example.php for a template.\"}");

mysql_connect($db_host, $db_user, $db_password) or die("{\"error\": \"Could not connect to database!\"}");
mysql_select_db($db_database) or die("{\"error\": \"Could not find database!\"}");

$res = mysql_query("SELECT * FROM `stats` ORDER BY `time` DESC LIMIT 1");
if (!$res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$data = mysql_fetch_object($res);
if (!$data) die("{\"error\": \"No data!\"}");

// Peak power of today
$res = mysql_query("SELECT `grid_pow` FROM `stats` WHERE DATE(`time`) = CURDATE() ORDER BY `grid_pow` DESC LIMIT 1");
if (!$res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$peak_pow = mysql_fetch_object($res)->grid_pow;
if (!is_numeric($peak_pow)) die("{\"error\": \"No data!\"}");

// Ammount of stuff we collected today
$res = mysql_query("SELECT `total_pow` FROM `stats` WHERE DATE(`time`)=CURDATE() AND HOUR(`time`)=0 AND MINUTE(`time`) < 5");
if (!$res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
$start_pow = mysql_fetch_object($res)->total_pow;
if (!$start_pow) die("{\"error\": \"No data!\"}");
?>

{"time": 		"<?php echo $data->time; ?>",
 "flags": 		<?php echo $data->flags; ?>,
 "pv_volt": 	<?php echo $data->pv_volt / 10.; ?>,
 "pv_amp": 		<?php echo $data->pv_amp / 100.; ?>,
 "grid_freq": 	<?php echo $data->grid_freq / 100.; ?>,
 "grid_volt": 	<?php echo $data->grid_volt; ?>,
 "grid_pow": 	<?php echo $data->grid_pow; ?>,
 "total_pow": 	<?php echo $data->total_pow / 100.; ?>,
 "temp": 		<?php echo $data->temp; ?>,
 "optime": 		<?php echo $data->optime; ?>,
 "hasdata": 	<?php echo $data->hasdata; ?>,
 "peak_pow":    <?php echo $peak_pow; ?>,
 "today_pow":   <?php echo ($data->total_pow - $start_pow) / 100.; ?>}
