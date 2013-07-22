<?php

/*
 * Converts the flags used by the Soladin to human-readable string, in html.
 */
function flags2html($flags) {
    $strs = Array();
    if ($flags & 0x0001) $strs[] = "Voltage solar te hoog.";
    if ($flags & 0x0002) $strs[] = "Voltage solar te laag.";
    if ($flags & 0x0004) $strs[] = "Geen grid.";
    if ($flags & 0x0008) $strs[] = "Voltage AC te hoog.";
    if ($flags & 0x0010) $strs[] = "Voltage AC te laag.";
    if ($flags & 0x0020) $strs[] = "F AC te hoog.";
    if ($flags & 0x0040) $strs[] = "F AC te laag.";
    if ($flags & 0x0080) $strs[] = "Temperatuur te hoog.";
    if ($flags & 0x0100) $strs[] = "Hardware fout.";
    if ($flags & 0x0200) $strs[] = "Starten...";
    if ($flags & 0x0400) $strs[] = "Maximaal vermogen.";
    if ($flags & 0x0800) $strs[] = "Maximale spanning.";

    if (count($strs) == 0) return "";
    elseif (count($strs) == 1) return $strs[0];
    else return "<ul><li>" . implode("</li><li>", $strs) . "</li></ul>";
}

/*
 * Converts a given number of minutes to a human-readable string with days,
 * hours, minutes.
 */
function mins2verbose($mins) {
    $hours = (int)($mins / 60);
    $mins = $mins % 60;

    $days = (int)($hours / 60);
    $hours = $hours % 60;

    $ret = "";
    if ($days > 0)
        $ret .= "$days dagen ";
    if ($hours > 0)
        $ret .= "$hours uur ";
    if ($mins > 0)
        $ret .= "$mins minuten";

    return $ret;
}

/*
 * Converts mysql datetime to an array which can be used for javascript.
 * Javascript's months are zero-based for some reason.
 */
function datetime_mysql_to_js($datetime) {
    $dt = explode(" ", $datetime);
    $d  = explode("-", $dt[0]);
    $t  = explode(":", $dt[1]);
    $ret = Array();
    $ret[] = $d[0];
    $ret[] = $d[1]-1; // JS months are zero based
    $ret[] = $d[2];
    $ret[] = $t[0];
    $ret[] = $t[1];
    $ret[] = $t[2];
    return $ret;
}

// End of functions.php
