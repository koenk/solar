<?php
error_reporting(0);
if (!include("config.php"))
    die("config.php not found! Copy config.example.php for a template.");

if (!include("functions.php"))
    die("functions.php not found!");

if (!include("solar.php"))
    die("solar.php not found!");

error_reporting(-1);

mysql_connect($db_host, $db_user, $db_password) or
    die("Could not connect to database!");
mysql_select_db($db_database) or die("Could not find database!");

$solar_daterange = solar\daterange($db_tables_solar);
$solar_flags = solar\flags($db_tables_solar);

$solar = Array();
foreach ($db_tables_solar as $table) {
    $data = Array();

    $data['current_graph'] = solar\current_graph($table);
    list($data['day_total'], $data['day_peak']) =
        solar\daymode($table,
            $solar_daterange['hmonth'],
            $solar_daterange['hyear']);
    $data['week'] = solar\weekmode($table, $solar_daterange['hyear']);
    $data['month'] = solar\monthmode($table, $solar_daterange['hyear']);

    list($data['last_status'], $data['today_peak'], $data['today_total']) =
        solar\last_status($table);

    $data['money_total'] =
        solar\money_total($table, $db_table_prices, $db_table_holidays);
    $data['money_today'] =
        solar\money_today($table, $db_table_prices, $db_table_holidays);
    $solar[$table] = $data;
}

// Resol stats (3 temps, 1 pump)
$resol_res = mysql_query("
SELECT `time`, `t1`, `t2`, `t3`, `p1`
FROM `$db_table_resol`
WHERE DATEDIFF(CURDATE(), `time`) < 2
ORDER BY `time`");
if (!$resol_res)
    die('Invalid query: ' . mysql_error());
$resol_start_date = NULL;
$resol_t1_data = Array();
$resol_t2_data = Array();
$resol_t3_data = Array();
$resol_p1_data = Array();
while ($row = mysql_fetch_object($resol_res)) {
    if ($resol_start_date === NULL)
        $resol_start_date = datetime_mysql_to_js($row->time);
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

    <link type="text/css" href="css/smoothness/jquery-ui-1.8.7.custom.css"
        rel="stylesheet" />
    <link type="text/css" href="css/layout.css" rel="stylesheet"/>
    <link type="text/css" href="css/colorbox.css" rel="stylesheet"/>


    <script src="js/jquery-1.4.4.min.js" type="text/javascript"></script>
    <script src="js/jquery-ui-1.8.7.custom.min.js"
        type="text/javascript"></script>
    <script src="js/highcharts.js" type="text/javascript"></script>
    <script src="js/jquery.colorbox-min.js" type="text/javascript"></script>

    <script src="js/solargraphs.js" type="text/javascript"></script>
    <script src="js/imagepopup.js" type="text/javascript"></script>
    <script src="js/navbuttons.js" type="text/javascript"></script>
    <script src="js/refreshdata.js" type="text/javascript"></script>

    <script type="text/javascript">
        // PHP inserts data here
        var lyear = <?php echo $solar_daterange['lyear']; ?>;
        var hyear = <?php echo $solar_daterange['hyear']; ?>;
        var lmonth = <?php echo $solar_daterange['lmonth']  - 1; ?>;
        var hmonth = <?php echo $solar_daterange['hmonth'] - 1; ?>;

        var month_map = ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
            'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'];
        function day_cur_month_length() {
            return new Date(day_cur_year, day_cur_month + 1, 0).getDate();
        }

        var day_cur_month = hmonth;
        var day_cur_year = hyear;
        var week_cur_year = hyear;
        var month_cur_year = hyear;

        var power_day_start = [];
        var power_day_data = [];

        var day_total_start = Date.UTC(day_cur_year, day_cur_month, 1);
        var day_total_data = [];
        var day_peak_data = [];

        var week_total_data = [];
        var month_total_data = [];

        <?php foreach ($solar as $table => $data): ?>

        power_day_start.push(Date.UTC(
            <?php echo implode(", ",
                      datetime_mysql_to_js($data['current_graph'][0]->time)); ?>
        ));
        power_day_data.push(
            [
                <?php
                    echo implode(", ",
                        array_map(
                            function($row) {
                                return $row->hasdata ? $row->grid_pow : -30;
                            },
                            $data['current_graph']));
                ?>
            ]);

        day_total_data.push(
            [<?php echo implode(', ', $data['day_total']); ?>]
        );
        day_peak_data.push(
            [<?php echo implode(', ', $data['day_peak']); ?>]
        );

        week_total_data.push(<?php echo $data['week']; ?>);
        month_total_data.push(<?php echo $data['month']; ?>);

        <?php endforeach; ?>

        var resol_start = Date.UTC(
                                <?php
                                    echo implode(',', $resol_start_date);
                                ?>);
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
        </div>

        <div id="intro">
            <p>
                Hieronder enkele grafieken van de opbrengsten van twee zonnecel
                installaties met beiden een Soladin 600 met drie panelen. De
                eerst set is 24-12-2010 ge&iuml;nstalleerd en heeft een
                piekvermogen van 615Wp. De tweede set is 23-09-2011
                ge&iuml;nstalleerd met een piek van 675 Wp. Ook staan hier de
                temperaturen van de zonneboiler (twee panelen + Resol BS).
            </p>
            <p>Foto's:
                <a href="solar_old.png" title="Zonnepanelen 24-12-2010 (oud)"
                    rel="imgs">Zonnepanelen (oud)</a>
                <a href="solar_new.png" title="Zonnepanelen 23-09-2011 (nieuw)"
                    rel="imgs">Zonnepanelen (nieuw)</a>
                <a href="resol.png" title="Zonneboiler"
                    rel="imgs">Zonneboiler</a>
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

                <div id="stats_solar">
                    <table>
                        <tr>
                            <th colspan="3">Zonnecellen</th>
                        </tr>
                        <tr>
                            <td>Datum/Tijd</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_time">
                                <?php
                                    echo $data['last_status']->time;
                                ?>
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>PV voltage</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_pv_volt">
                                <?php
                                    echo $data['last_status']->pv_volt / 10.;
                                 ?> V
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>PV amperage</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_pv_amp">
                                <?php
                                    echo $data['last_status']->pv_amp / 100.;
                                ?> A
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Grid frequentie</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_grid_freq">
                                <?php
                                    echo $data['last_status']->grid_freq
                                        / 100.;
                                ?> Hz
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Grid voltage</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_grid_volt">
                                <?php
                                        echo $data['last_status']->grid_volt;
                                ?> V
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Grid vermogen</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_pow">
                                <span class="ct_grid_pow">
                                <?php
                                    echo $data['last_status']->grid_pow;
                                ?> W
                                </span>
                                <span class="ct_peak_pow add_today">
                                <?php
                                    echo $data['today_peak'];
                                ?> W piek vandaag
                                </span>
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Totaal vermogen</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_tpow">
                                <span class="ct_total_pow">
                                <?php
                                    echo $data['last_status']->total_pow
                                        / 100.;
                                ?> kWh
                                </span>
                                <span class="ct_today_pow add_today">
                                <?php
                                    echo $data['today_total'];
                                ?> kWh vandaag
                                </span>
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Opbrengst euro's</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td>
                                <span class="ct_total_money">
                                    &euro;
                                    <?php
                                    echo sprintf('%.2f',
                                        $data['money_total']);
                                    ?>
                                </span>
                                <span class="ct_today_money add_today">
                                    &euro;
                                    <?php
                                        echo sprintf('%.2f',
                                            $data['money_today']);
                                    ?>
                                    vandaag
                                </span>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Temperatuur</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_temp">
                                <?php
                                    echo $data['last_status']->temp;
                                ?>
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Tijd actief</td>
                            <?php foreach ($solar as $t => $data): ?>
                            <td><span class="ct_optime">
                                <?php
                                    echo mins2verbose(
                                        $data['last_status']->optime);
                                ?>
                            </span></td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </div>

                <div class="seperator"></div>
                <div id="stats_resol">
                    <table>
                        <tr>
                            <th colspan="2">Zonneboiler</th>
                        </tr>
                        <tr>
                            <td>Datum/Tijd</td>
                            <td><span id="ct_resol_time">
                                <?php echo $resol_current_data->time; ?>
                            </span></td>
                        </tr>
                        <tr>
                            <td>Temp. Panelen</td>
                            <td><span id="ct_resol_t1">
                                <?php echo $resol_current_data->t1 / 10.; ?>
                                &deg;C
                            </span></td>
                        </tr>
                        <tr>
                            <td>Temp. Boiler</td>
                            <td><span id="ct_resol_t2">
                                <?php echo $resol_current_data->t2 / 10.; ?>
                                &deg;C
                            </span></td>
                        </tr>
                        <tr>
                            <td>Temp. Zwembad</td>
                            <td><span id="ct_resol_t3">
                                <?php echo $resol_current_data->t3 / 10.; ?>
                                &deg;C
                            </span></td>
                        </tr>
                        <tr>
                            <td>Pomp</td>
                            <td><span id="ct_resol_p1">
                                <?php echo $resol_current_data->p1; ?>
                                %
                            </span></td>
                        </tr>
                    </table>
                </div>


                <br class="clear" />


                <?php if (count($solar_flags)): ?>
                <table id="flagstable" style="margin-top: 30px;">
                    <tr>
                        <th colspan="3">Laatste meldingen Soladin</th>
                    </tr>

                    <?php foreach($solar_flags as $row): ?>
                        <tr>
                            <td><?php echo $row->time; ?></td>
                            <td>#<?php echo $row->device; ?></td>
                            <td><?php echo flags2html($row->flags); ?></td>
                        </tr>
                    <?php endforeach; ?>

                </table>
                <?php endif; ?>
            </div>
        </div>
        <div id="footer">
            <a href="http://git.chozo.nl/solar.git/">Source code</a><br />
            Made by <a href="http://chozo.nl/">Chozo.nl</a>
        </div>
    </div>
</body>
</html>

