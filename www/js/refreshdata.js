setInterval(
    function() { 
    // solar
    $.getJSON("json.php", {'action': 'stats'}, 
        function(data){
            for (var i = 0; i < data.data.length; i++) {
                inst = data.data[i];
                $(".ct_time").eq(i).html(inst.time);
                $(".ct_pv_volt").eq(i).html(inst.pv_volt + " V");
                $(".ct_pv_amp").eq(i).html(inst.pv_amp + " A");
                $(".ct_grid_freq").eq(i).html(inst.grid_freq + " Hz");
                $(".ct_grid_volt").eq(i).html(inst.grid_volt + " V");
                $(".ct_grid_pow").eq(i).html(inst.grid_pow + " W");
                $(".ct_total_pow").eq(i).html(inst.total_pow + " kWh");
                $(".ct_temp").eq(i).html(inst.temp + " &deg;C");
                $(".ct_optime").eq(i).html(inst.optime);
                $(".ct_peak_pow").eq(i).html(inst.peak_pow + " W piek vandaag");
                $(".ct_today_pow").eq(i).html(inst.today_pow + " kWh vandaag");
                $(".ct_total_money").eq(i).html("&euro;" + inst.money);
                $(".ct_today_money").eq(i).html("&euro;" +
                    inst.money_today.toFixed(2) + " vandaag");
                
                // Flags table thing
                if (inst.flags != "")
                    $("#flagstable th").parent().after($("<tr><td>" +
                                inst.time + "</td><td>" + inst.flags +
                                "</td></tr>"));
                
                var tdate = inst.time.split(" ");
                var ttime = tdate[1].split(":");
                tdate = tdate[0].split("-");
                
                var tday = parseInt(tdate[2]);
                var tmonth = parseInt(tdate[1])-1;
                var tyear = parseInt(tdate[0]);
                var thour = parseInt(ttime[0]);
                var tmin = parseInt(ttime[1]);
                var tsec = parseInt(ttime[2]);
                var newcoords = [Date.UTC(tyear, tmonth, tday, thour, tmin,
                        tsec),inst.hasdata ? inst.grid_pow : -30];
                var series = pow_chart.series[i].data;
                
                // See whether the data is already in the graph
                // TODO: change this to own var with timestamp for reliability?
                if (series[series.length-1].x != newcoords[0]) {
                    pow_chart.series[i].addPoint(newcoords, true, true);
                }
            }
        }
    );
    
    // resol
    $.getJSON("json.php", {'action': 'resol'}, 
        function(data){                                
            start = Date.UTC(data.year, data.month-1, data.day, data.hour, data.min, data.sec);
            resol_chart.series[0].options.pointStart = start;
            resol_chart.series[0].setData(data.p1); // p1
            resol_chart.series[1].options.pointStart = start;
            resol_chart.series[1].setData(data.t1); // t1
            resol_chart.series[2].options.pointStart = start;
            resol_chart.series[2].setData(data.t2); // t2
            resol_chart.series[3].options.pointStart = start;
            resol_chart.series[3].setData(data.t3); // t3
            
            // Update current data table
            $("#ct_resol_time").html(data.cur_time);
            $("#ct_resol_t1").html(data.cur_t1 + " &deg;C");
            $("#ct_resol_t2").html(data.cur_t2 + " &deg;C");
            $("#ct_resol_t3").html(data.cur_t3 + " &deg;C");
            $("#ct_resol_p1").html(data.cur_p1 + "%");
        }
    );
    }, 1000 * 60 * 5); // Every 5 minutes
