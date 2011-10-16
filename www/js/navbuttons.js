$(function() {
    update_nav_buttons();

            
    $("#day_month_nav_prev").click(function() {
        day_cur_month -= 1;
        
        if (day_cur_month < 0) {
            day_cur_month = 11;
            day_cur_year -= 1;
        }
        
        update_nav_buttons();
        
        $.getJSON("json.php", {'action': 'day', 'year': day_cur_year, 'month': day_cur_month + 1}, day_nav_getjson);
        
        return false;
    });
    
    $("#day_month_nav_next").click(function() {
        day_cur_month += 1;
        
        if (day_cur_month > 11) {
            day_cur_month = 0;
            day_cur_year += 1;
        }
        
        day_total_start = Date.UTC(day_cur_year, day_cur_month, 1);
        
        update_nav_buttons();
        
        $.getJSON("json.php", {'action': 'day', 'year': day_cur_year, 'month': day_cur_month + 1}, day_nav_getjson);
        
        return false;
    });
    
    $("#day_year_nav_prev").click(function() {
        day_cur_year -= 1;
        
        if (day_cur_year == lyear && day_cur_month < lmonth)
            day_cur_month = lmonth;
        
        update_nav_buttons();
        
        $.getJSON("json.php", {'action': 'day', 'year': day_cur_year, 'month': day_cur_month + 1}, day_nav_getjson);
        
        return false;
    });
    
    $("#day_year_nav_next").click(function() {
        day_cur_year += 1;
        
        if (day_cur_year == hyear && day_cur_month > hmonth)
            day_cur_month = hmonth;
        
        update_nav_buttons();
        
        $.getJSON("json.php", {'action': 'day', 'year': day_cur_year, 'month': day_cur_month + 1}, day_nav_getjson);
        
        return false;
    });
    
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
});
            
            
function day_nav_getjson(data) {
    day_total_start = Date.UTC(data.year, data.month - 1, 1);
    day_chart.series[0].options.pointStart = day_total_start;
    day_chart.series[0].setData(data.peakpow);
    day_chart.series[1].options.pointStart = day_total_start;
    day_chart.series[1].setData(data.pow);
}

function update_nav_buttons() {
    if (day_cur_year == lyear && day_cur_month == lmonth)
        $("#day_month_nav_prev").hide();
    else
        $("#day_month_nav_prev").show();
        
    if (day_cur_year == hyear && day_cur_month == hmonth)
        $("#day_month_nav_next").hide();
    else
        $("#day_month_nav_next").show();
        
    if (day_cur_year == lyear)
        $("#day_year_nav_prev").hide();
    else
        $("#day_year_nav_prev").show();
        
    if (day_cur_year == hyear)
        $("#day_year_nav_next").hide();
    else
        $("#day_year_nav_next").show();

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
        
    $("#day_month_nav_cur").html(month_map[day_cur_month]);
    $("#day_year_nav_cur").html(day_cur_year);
    $("#week_year_nav_cur").html(week_cur_year);
    $("#month_year_nav_cur").html(month_cur_year);
}