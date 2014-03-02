<?php
namespace solar;
/*
 * Contains functions that fetch solar data from the database.
 */

/*
 * Returns data for the graph showing the last two days.
 * Result is a dictionary with the following fields:
 *  data: array with objects for each data point (raw database record)
 */
function current_graph($db, $table) {
    $res = $db->query("
    SELECT *
    FROM `$table`
    WHERE DATEDIFF(CURDATE(), `time`) < 2
    ORDER BY `time`");
    if (!$res)
        die('Invalid query: ' . $db->error());
    if ($res->num_rows == 0)
        die('No data for current-data graph.');

    $ret = Array();
    while ($row = $res->fetch_object())
        $ret[] = $row;

    return $ret;
}

/*
 * Returns the most recently gathered data and the peak and total power for
 * today.
 */
function last_status($db, $table) {
    // Most recent status
    $res = $db->query("
    SELECT *
    FROM `$table`
    ORDER BY `time` DESC
    LIMIT 1");
    if (!$res)
        die('Invalid query: ' . $db->error());
    if ($res->num_rows == 0)
        die('No data (today)');
    $status = $res->fetch_object();

    // Peak power today
    $res = $db->query("
    SELECT `grid_pow`
    FROM `$table`
    WHERE DATE(`time`) = CURDATE()
    ORDER BY `grid_pow` DESC
    LIMIT 1");
    if (!$res)
        die("Invalid query: " . $db->error());
    if ($res->num_rows == 0)
        die('No data (today)');
    $peak = $res->fetch_object()->grid_pow;

    // Power generated today
    $res = $db->query("
    SELECT `total_pow`
    FROM `$table`
    WHERE DATE(`time`) = CURDATE() AND
            HOUR(`time`) = 0 AND
            MINUTE(`time`) < 5");
    if (!$res)
        die("Invalid query: " . $db->error());
    if ($res->num_rows == 0)
        die('No data (today)');

    $start_pow = $res->fetch_object()->total_pow;
    $total = ($status->total_pow - $start_pow) / 100.;

    return Array($status, $peak, $total);
}

/*
 * Returns the amount of money made in total.
 */
function money_total($db, $table, $table_prices, $table_holidays) {
    $res = $db->query("
    SELECT SUM(`t`.`day_money`) AS `money`
    FROM (
        SELECT
            1 AS `group_by_all`,
            (MAX(`s`.`total_pow`) - MIN(`s`.`total_pow`)) / 100. *
            IF(WEEKDAY(`s`.`time`) >= 5,
            `p`.`low`,
            IF((SELECT COUNT(`day`) AS `num`
                FROM `$table_holidays` AS `h`
                WHERE `h`.`day` = DATE(`s`.`time`)) > 0,
            `p`.`low`,
            `p`.`normal`)
            ) AS `day_money`
        FROM `$table` AS `s`
        LEFT JOIN `$table_prices` AS `p` ON
            DATE(`s`.`time`) >= `p`.`start` AND
            DATE(`s`.`time`) <= `p`.`end`
        GROUP BY DATE(`s`.`time`)
    ) AS `t`
    GROUP BY `group_by_all`");
    return $res->fetch_object()->money;
}

/*
 * Returns the amount of money made today.
 */
function money_today($db, $table, $table_prices, $table_holidays) {
    $res = $db->query("
    SELECT
        (MAX(`s`.`total_pow`) - MIN(`s`.`total_pow`)) / 100. *
        IF(WEEKDAY(`s`.`time`) >= 5,
        `p`.`low`,
        IF((SELECT COUNT(`day`) AS `num`
            FROM `$table_holidays` AS `h`
            WHERE `h`.`day` = DATE(`s`.`time`)) > 0,
        `p`.`low`,
        `p`.`normal`)
        ) AS `money`
    FROM `$table` AS `s`,
            `$table_prices` AS `p`
    WHERE
        DATE(`s`.`time`) = CURDATE() AND
        CURDATE() >= `p`.`start` AND
        CURDATE() <= `p`.`end`");

    return $res->fetch_object()->money;
}

/*
 * Returns all errors flags from the given tables combined.
 * The result is an array with each entry a (time, flags) tuple.
 */
function flags($db, $tables) {
    // Construct a big query that combines the data from all tables.
    $query = "";
    $device_num = 1;
    foreach($tables as $table) {
        if ($query)
            $query .= "UNION\n";
        $query .=
            "(SELECT `time`, `flags`, '$device_num' as `device`" .
            " FROM `$table` " .
            " WHERE `flags` > 0 " .
            " ORDER BY `time` DESC " .
            " LIMIT 5)\n";
        $device_num++;
    }
    $query .= "ORDER BY `time` DESC\n LIMIT 10";

    $res = $db->query($query);
    $ret = Array();
    while ($row = $res->fetch_object())
        $ret[] = $row;

    return $ret;
}

/*
 * Returns the range of dates for which data exists.
 * lyear:  year lower bound
 * lmonth: lower bound month of year lyear
 * hyear:  year upper bound
 * hmonth: upper bound month of year hyear
 */
function daterange($db, $tables) {
    $daterange = Array('lyear' => 9999,
                       'hyear' => 0,
                       'lmonth' => 99,
                       'hmonth' => 0);

    foreach ($tables as $table) {
        $res = $db->query("
        SELECT YEAR(MIN(`time`)) AS `lyear`,
               YEAR(MAX(`time`)) AS `hyear`,
               MONTH(MIN(`time`)) AS `lmonth`,
               MONTH(MAX(`time`)) AS `hmonth`
        FROM `$table`");
        $daterange_table = $res->fetch_object();
        if ($daterange['lyear'] > $daterange_table->lyear)
            $daterange['lyear'] = $daterange_table->lyear;

        if ($daterange['hyear'] < $daterange_table->hyear)
            $daterange['hyear'] = $daterange_table->hyear;

        if ($daterange['lmonth'] > $daterange_table->lmonth)
            $daterange['lmonth'] = $daterange_table->lmonth;

        if ($daterange['hmonth'] < $daterange_table->hmonth)
            $daterange['hmonth'] = $daterange_table->hmonth;
    }
    return $daterange;
}

/*
 * Returns the total and peak power per day for a single month in a year.
 * The result is an array with two arrays. The first array contains the total
 * power per day, the other the peak power.
 */
function daymode($db, $table, $month, $year) {
    $res = $db->query(
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

    if ($res->num_rows) {
        $row = $res->fetch_object();

        // Fill up places before first day (if needed)
        for ($i = 1; $i < $row->day; $i++) {
            $pow[] = 0;
            $peak[] = 0;
        }

        $pow[] = $row->pow / 100.;
        $peak[] = $row->peak_pow;

        while ($row = $res->fetch_object()) {
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

/*
 * Returns the total power per week for a given year.
 * The result is a json string, containing an array of (week, power) tuples.
 */
function weekmode($db, $table, $year) {
    $res = $db->query(
    "SELECT WEEK(`time`, 1) AS `week`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `$table`
    WHERE YEAR(`time`) = $year
    GROUP BY WEEK(`time`, 1)
    ORDER BY `week` ASC");

    $ret = '';

    $c = 0;
    while ($row = $res->fetch_object()) {
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

/*
 * Returns the total power per month for a given year.
 * The result is a json string, containing an array with the power per month.
 */
function monthmode($db, $table, $year) {
    $res = $db->query(
    "SELECT MONTH(`time`) - 1 AS `month`,
            MAX(`total_pow`) - MIN(`total_pow`) AS `pow`
    FROM  `$table`
    WHERE YEAR(`time`) = $year
    GROUP BY month(`time`)
    ORDER BY `month` ASC");

    $ret = '';

    $c = 0;
    while ($row = $res->fetch_object()) {
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
