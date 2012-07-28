<?php

// TODO: Make this monster modular for effeciency. (mainly for json.php)

function get_solar_data() {
    if (!include("config.php"))
        die("config.php not found! Copy config.example.php for a template.");
    $data = Array();

    foreach($db_tables_solar as $table) {
        $data[$table] = Array();

        // All data for first graph (last 2 days)
        $data[$table]['today_res'] = mysql_query("
        SELECT *
        FROM `$table`
        WHERE DATEDIFF(CURDATE(), `time`) < 2
        ORDER BY `time`");
        if (!$data[$table]['today_res'])
            die('Invalid query: ' . mysql_error());
        // This is fucked up... I need the first date/time for the graph... So we
        // fetch the first row here, and the rest of the rows are read and directly
        // printed where it is neceserry.
        $row = mysql_fetch_object($data[$table]['today_res']);
        if (!$row) die("No data!");
        $ts =       explode(" ", $row->time);
        $ts_date =  explode("-", $ts[0]);
        $ts_time =  explode(":", $ts[1]);
        $data[$table]['ts'] = Array();
        $data[$table]['ts']['year'] =  $ts_date[0];
        $data[$table]['ts']['month'] = $ts_date[1]-1; // JS months are zero based
        $data[$table]['ts']['day'] =   $ts_date[2];
        $data[$table]['ts']['hour'] =  $ts_time[0];
        $data[$table]['ts']['min'] =   $ts_time[1];
        $data[$table]['ts']['sec'] =   $ts_time[2];
        $data[$table]['ts']['pow'] = $row->hasdata ? $row->grid_pow : "-30";

        $current_res = mysql_query("
        SELECT *
        FROM `$table`
        ORDER BY `time` DESC
        LIMIT 1");
        if (!$current_res) die('Invalid query: ' . mysql_error());
        $data[$table]['current_data'] = mysql_fetch_object($current_res);
        if (!$data[$table]['current_data'])
           die("No data! (current_data)");

        // Peak power of today
        $peak_pow_res = mysql_query("
        SELECT `grid_pow`
        FROM `$table`
        WHERE DATE(`time`) = CURDATE()
        ORDER BY `grid_pow` DESC
        LIMIT 1");
        if (!$peak_pow_res) die("Invalid query: " . mysql_error());
        $data[$table]['peak_pow'] = mysql_fetch_object($peak_pow_res)->grid_pow;
        if (!is_numeric($data[$table]['peak_pow']))
           die("No data! (peak_pow)}");

        // Ammount of stuff we collected today
        $today_pow_res = mysql_query("
        SELECT `total_pow`
        FROM `$table`
        WHERE DATE(`time`) = CURDATE() AND
              HOUR(`time`) = 0 AND
              MINUTE(`time`) < 5");
        if (!$today_pow_res) die("Invalid query: " . mysql_error());
        $start_pow = mysql_fetch_object($today_pow_res)->total_pow;
        if (!$start_pow) die("No data! (start_pow)");

        $data[$table]['today_pow'] =
            ($data[$table]['current_data']->total_pow - $start_pow) / 100.;


        // Year range (+their min/max months)
        $yearrange_res = mysql_query("
        SELECT YEAR(MIN(`time`)) AS `lyear`,
               YEAR(MAX(`time`)) AS `hyear`,
               MONTH(MIN(`time`)) AS `lmonth`,
               MONTH(MAX(`time`)) AS `hmonth`
        FROM `$table`");
        $data[$table]['yearrange'] = mysql_fetch_object($yearrange_res);

        // Stuff for day mode
        $day_month = $data[$table]['yearrange']->hmonth;
        $day_year = $data[$table]['yearrange']->hyear;

        list($data[$table]['day_pow_data'], $data[$table]['day_peakpow_data']) =
            get_solar_daymode($table, $day_month, $day_year);


        // Stuff for week mode
        $week_year = $data[$table]['yearrange']->hyear;
        $data[$table]['week_data'] = get_solar_weekmode($table, $week_year);

        // Stuff for month mode
        $month_year = $data[$table]['yearrange']->hyear;
        $data[$table]['month_data'] = get_solar_monthmode($table, $month_year);

        // Moneyz
        $money_res = mysql_query("
        SELECT SUM(`t`.`day_money`) AS `money`
        FROM (
            SELECT
             1 AS `group_by_all`,
             (MAX(`s`.`total_pow`) - MIN(`s`.`total_pow`)) / 100. *
             IF(WEEKDAY(`s`.`time`) >= 5,
              `p`.`low`,
              IF((SELECT COUNT(`day`) AS `num`
                  FROM `$db_table_holidays` AS `h`
                  WHERE `h`.`day` = DATE(`s`.`time`)) > 0,
               `p`.`low`,
               `p`.`normal`)
              ) AS `day_money`
            FROM `$table` AS `s`
            LEFT JOIN `$db_table_prices` AS `p` ON
             DATE(`s`.`time`) >= `p`.`start` AND
             DATE(`s`.`time`) <= `p`.`end`
            GROUP BY DATE(`s`.`time`)
        ) AS `t`
        GROUP BY `group_by_all`");
        $data[$table]['money'] = mysql_fetch_object($money_res)->money;

        // Todays euro per kWh
        $tmon_res = mysql_query("
        SELECT
         (MAX(`s`.`total_pow`) - MIN(`s`.`total_pow`)) / 100. *
         IF(WEEKDAY(`s`.`time`) >= 5,
          `p`.`low`,
          IF((SELECT COUNT(`day`) AS `num`
              FROM `$db_table_holidays` AS `h`
              WHERE `h`.`day` = DATE(`s`.`time`)) > 0,
           `p`.`low`,
           `p`.`normal`)
          ) AS `money`
        FROM `$table` AS `s`,
             `$db_table_prices` AS `p`
        WHERE
         DATE(`s`.`time`) = CURDATE() AND
         CURDATE() >= `p`.`start` AND
         CURDATE() <= `p`.`end`");

        $data[$table]['money_today'] = mysql_fetch_object($tmon_res)->money;
    }

    // Flags table
    $flags_query = "";
    $i = 0;
    foreach($db_tables_solar as $table) {
        if ($i++ > 0)
            $flags_query .= "UNION\n";
        $flags_query .=
            "(SELECT `time`, `flags`, '$i' AS `num` " .
            " FROM `$table` " .
            " WHERE `flags` > 0 " .
            " ORDER BY `time` DESC " .
            " LIMIT 5)\n";
    }
    $flags_query .= "ORDER BY `time` DESC\n LIMIT 10";

    // Hacky stuff, we can't set this directly in the data array.
    foreach ($db_tables_solar as $table)
        $data[$table]['flags_res'] = mysql_query($flags_query);


    return $data;
}

function get_solar_daymode($table, $month, $year) {
    $res = mysql_query(
    "SELECT DAYOFMONTH(`time`) AS `day`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`,
            MAX(`grid_pow`) as `peak_pow`
    FROM `$table`
    WHERE YEAR(`time`) = $year AND
          MONTH(`time`) = $month
    GROUP BY `day`
    ORDER BY `day` ASC");

    $pow = Array();
    $peak = Array();

    if (mysql_num_rows($res)) {
        $row = mysql_fetch_object($res);

        // Fill up places before first day (if needed)
        for ($i = 1; $i < $row->day; $i++) {
            $pow[] = 0;
            $peak[] = 0;
        }

        $pow[] = $row->pow / 100.;
        $peak[] = $row->peak_pow;

        while ($row = mysql_fetch_object($res)) {
            $pow[] = $row->pow / 100.;
            $peak[] = $row->peak_pow;
        }
    }

    for ($i = count($pow); $i < date('t', mktime(0, 0, 0, $month, 1, $year));
            $i++) {
        $pow[] = 0;
        $peak[] = 0;
    }

    return Array($pow, $peak);
}

function get_solar_weekmode($table, $year) {
    $res = mysql_query(
    "SELECT WEEK(`time`, 1) AS `week`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `$table`
    WHERE YEAR(`time`) = $year
    GROUP BY WEEK(`time`, 1)
    ORDER BY `week` ASC");

    $ret = '';

    $c = 0;
    while ($row = mysql_fetch_object($res)) {
        if ($c > 0)
            $ret .= ", ";
        elseif ($c == 0 && $row->week > 0)
            while ($c < $row->week) {
                $ret .= "[$c, 0], ";
                ++$c;
            }

        $p = $row->pow / 100.;
        $ret .= "[$c, $p]";
        ++$c;
    }

    while ($c < 53) {
        if ($c > 0)
            $ret .= ", ";
        $ret .= "[$c, 0]";
        ++$c;
    }

    return '[' . $ret . ']';
}

function get_solar_monthmode($table, $year) {
    $res = mysql_query(
    "SELECT MONTH(`time`) - 1 AS `month`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `$table`
    WHERE YEAR(`time`) = $year
    GROUP BY month(`time`)
    ORDER BY `month` ASC");

    $ret = '';

    $c = 0;
    while ($row = mysql_fetch_object($res)) {
        if ($c > 0)
            $ret .= ", ";
        elseif ($c == 0 && $row->month > 0)
            while ($c < $row->month) {
                $ret .= "0, ";
                ++$c;
            }

        $p = $row->pow / 100.;
        $ret .= $p;
        ++$c;
    }

    while ($c < 12) {
        if ($c > 0)
            $ret .= ", ";
        $ret .= "0";
        ++$c;
    }

    return '[' . $ret . ']';
}

// End of solar.php
