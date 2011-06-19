<?php
error_reporting(0);
if (!include("config.php")) die("{\"error\": \"config.php not found! Copy config.example.php for a template.\"}");
if (!include("functions.php")) die("{\"error\": \"functions.php not found!\"}");

mysql_connect($db_host, $db_user, $db_password) or die("{\"error\": \"Could not connect to database!\"}");
mysql_select_db($db_database) or die("{\"error\": \"Could not find database!\"}");



$action = isset($_GET['action']) ?  $_GET['action'] : 'stats';

if ($action == 'stats'):
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
    
    $pow_today = ($data->total_pow - $start_pow) / 100.;
    
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
    $money_today = $pow_today * mysql_fetch_object($today_mon_res)->mon;
    
    ?>

    {"time": 		"<?php echo $data->time; ?>",
     "flags": 		"<?php echo flags2html($data->flags); ?>",
     "pv_volt": 	<?php echo $data->pv_volt / 10.; ?>,
     "pv_amp": 		<?php echo $data->pv_amp / 100.; ?>,
     "grid_freq": 	<?php echo $data->grid_freq / 100.; ?>,
     "grid_volt": 	<?php echo $data->grid_volt; ?>,
     "grid_pow": 	<?php echo $data->grid_pow; ?>,
     "total_pow": 	<?php echo $data->total_pow / 100.; ?>,
     "temp": 		<?php echo $data->temp; ?>,
     "optime": 		"<?php echo mins2verbose($data->optime); ?>",
     "hasdata": 	<?php echo $data->hasdata; ?>,
     "peak_pow":    <?php echo $peak_pow; ?>,
     "today_pow":   <?php echo $pow_today; ?>,
     "money":       <?php echo sprintf('%.2f', $money); ?>,
     "money_today": <?php echo sprintf('%.2f', $money_today); ?>}
     
    <?php
elseif ($action == 'week'):
    $year = isset($_GET['year']) ? $_GET['year'] : 2011;
    $year = mysql_real_escape_string($year);
    $res = mysql_query(
    "SELECT WEEK(`time`, 1) AS `week`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `stats` 
    WHERE YEAR(`time`) = $year
    GROUP BY WEEK(`time`, 1) 
    ORDER BY `week` ASC");
    
    ?>
    {"year":    <?php echo $year; ?>,
     "data":    
    [
        <?php
            $c = 0;
            while ($row = mysql_fetch_object($res)) {
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
    ]}
    <?php
elseif ($action == 'month'):
    $year = isset($_GET['year']) ? $_GET['year'] : 2011;
    $year = mysql_real_escape_string($year);
    $res = mysql_query(
    "SELECT MONTH(`time`) - 1 AS `month`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `stats` 
    WHERE YEAR(`time`) = $year
    GROUP BY month(`time`) 
    ORDER BY `month` ASC");
    
    ?>
    {"year":    <?php echo $year; ?>,
     "data":    
    [
        <?php
            $c = 0;
            while ($row = mysql_fetch_object($res)) {
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
    ]}
    <?php
    
else:
    die("{\"error\": \"Unkown action '$action'\"}");
endif;
?>
