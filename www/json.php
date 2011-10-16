<?php
error_reporting(0);
if (!include("config.php")) die("{\"error\": \"config.php not found! Copy config.example.php for a template.\"}");
if (!include("functions.php")) die("{\"error\": \"functions.php not found!\"}");

mysql_connect($db_host, $db_user, $db_password) or die("{\"error\": \"Could not connect to database!\"}");
mysql_select_db($db_database) or die("{\"error\": \"Could not find database!\"}");



$action = isset($_GET['action']) ?  $_GET['action'] : 'stats';

if ($action == 'stats'):
    $res = mysql_query("SELECT * FROM `$db_table_solar` ORDER BY `time` DESC LIMIT 1");
    if (!$res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
    $data = mysql_fetch_object($res);
    if (!$data) die("{\"error\": \"No data!\"}");

    // Peak power of today
    $res = mysql_query("SELECT `grid_pow` FROM `$db_table_solar` WHERE DATE(`time`) = CURDATE() ORDER BY `grid_pow` DESC LIMIT 1");
    if (!$res) die("{\"error\": \"Invalid query: " . mysql_error() . "\"}");
    $peak_pow = mysql_fetch_object($res)->grid_pow;
    if (!is_numeric($peak_pow)) die("{\"error\": \"No data!\"}");

    // Ammount of stuff we collected today
    $res = mysql_query("SELECT `total_pow` FROM `$db_table_solar` WHERE DATE(`time`)=CURDATE() AND HOUR(`time`)=0 AND MINUTE(`time`) < 5");
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
elseif ($action == 'day'):
    $year = isset($_GET['year']) ? $_GET['year'] : 2011;
    $year = mysql_real_escape_string($year);
    $month = isset($_GET['month']) ? $_GET['month'] : 7;
    $month = mysql_real_escape_string($month);
    $res = mysql_query(
    "SELECT DAYOFMONTH(`time`) AS `day`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`,
            MAX(`grid_pow`) as `peak_pow`
    FROM `$db_table_solar` 
    WHERE YEAR(`time`) = $year AND
          MONTH(`time`) = $month
    GROUP BY `day`
    ORDER BY `day` ASC");

    $row = mysql_fetch_object($res);

    $pow_data = Array();
    $peakpow_data = Array();

    // Fill up places before first day (if needed)
    for ($i = 1; $i < $row->day; $i++) {
        $pow_data[] = 0;
        $peakpow_data[] = 0;
    }

    $pow_data[] = $row->pow / 100.;
    $peakpow_data[] = $row->peak_pow;

    while ($row = mysql_fetch_object($res)) {
        $pow_data[] = $row->pow / 100.;
        $peakpow_data[] = $row->peak_pow;
    }

    // Fill up places after last day (if needed)
    for ($i = count($pow_data); $i < date('t', mktime(0, 0, 0, $month, 1, $year)); $i++) {
        $pow_data[] = 0;
        $peakpow_data[] = 0;
    }
    
    ?>
    {
     "year":    <?php echo $year; ?>,
     "month":   <?php echo $month; ?>,
     "pow":     [<?php echo implode(',', $pow_data); ?>],
     "peakpow": [<?php echo implode(',', $peakpow_data); ?>]
    }
    <?php    
elseif ($action == 'week'):
    $year = isset($_GET['year']) ? $_GET['year'] : 2011;
    $year = mysql_real_escape_string($year);
    $res = mysql_query(
    "SELECT WEEK(`time`, 1) AS `week`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `$db_table_solar` 
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
    FROM  `$db_table_solar` 
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
elseif ($action == 'resol'):
    $res = mysql_query(
    "SELECT `time`, `t1`, `t2`, `t3`, `p1`
    FROM `$db_table_resol`
    WHERE DATEDIFF(CURDATE(), `time`) < 2
    ORDER BY `time`");
    if (!$res) die('{"error": "'.mysql_error().'"}');
    // Same as first one: we need starting data and stuff
    $row = mysql_fetch_object($res);
    if (!$row) die("{\"error\": \"No data!\"}");
    $ts =       explode(" ", $row->time);
    $ts_date =  explode("-", $ts[0]);
    $ts_time =  explode(":", $ts[1]);
    $ts_year =  $ts_date[0];
    $ts_month = $ts_date[1]-1; // JS months are zero based... seriously...?
    $ts_day =   $ts_date[2];
    $ts_hour =  $ts_time[0];
    $ts_min =   $ts_time[1];
    $ts_sec =   $ts_time[2];
    $t1_data = Array($row->t1/10.);
    $t2_data = Array($row->t2/10.);
    $t3_data = Array($row->t3/10.);
    $p1_data = Array($row->p1);
    while ($row = mysql_fetch_object($res)) {
        $resol_t1_data[] = $row->t1/10.;
        $resol_t2_data[] = $row->t2/10.;
        $resol_t3_data[] = $row->t3/10.;
        $resol_p1_data[] = $row->p1;
    }
    
    $res = mysql_query("
    SELECT `time`, `t1`, `t2`, `t3`, `p1`
    FROM `$db_table_resol`
    ORDER BY `time` DESC
    LIMIT 1");
    if (!$res) die('{"error": "'.mysql_error().'"}');
    $current_data = mysql_fetch_object($res);
    if (!$current_data) die("{\"error\": \"No data!\"}");
    
    // Why are all those things casted to an int, you may ask? Otherwise, it may
    // send a value as '00' (two zeroes). The JSON thing of jQuery hates this,
    // and silently ignores the entire response.
    ?>
    {"year":     <?php echo (int)$ts_year; ?>,
     "month":    <?php echo (int)$ts_month; ?>,
     "day":      <?php echo (int)$ts_day; ?>,
     "hour":     <?php echo (int)$ts_hour; ?>, 
     "min":      <?php echo (int)$ts_min; ?>,
     "sec":      <?php echo (int)$ts_sec; ?>,
     "p1":       [<?php echo implode(',', $resol_p1_data); ?>],
     "t1":       [<?php echo implode(',', $resol_t1_data); ?>],
     "t2":       [<?php echo implode(',', $resol_t2_data); ?>],
     "t3":       [<?php echo implode(',', $resol_t3_data); ?>],
     "cur_time": "<?php echo $current_data->time; ?>",
     "cur_t1":   <?php echo $current_data->t1 / 10.; ?>,
     "cur_t2":   <?php echo $current_data->t2 / 10.; ?>,
     "cur_t3":   <?php echo $current_data->t3 / 10.; ?>,
     "cur_p1":   <?php echo $current_data->p1; ?>}
     
    <?php    
else:
    die("{\"error\": \"Unkown action '$action'\"}");
endif;
?>
