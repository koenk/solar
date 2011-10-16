setInterval(
    function() { 
        // solar
        $.getJSON("json.php", {'action': 'stats'}, 
            function(data){
                $("#ct_time").html(data.time);
                $("#ct_pv_volt").html(data.pv_volt + " V");
                $("#ct_pv_amp").html(data.pv_amp + " A");
                $("#ct_grid_freq").html(data.grid_freq + " Hz");
                $("#ct_grid_volt").html(data.grid_volt + " V");
                $("#ct_grid_pow").html(data.grid_pow + " W");
                $("#ct_total_pow").html(data.total_pow + " kWh");
                $("#ct_temp").html(data.temp + " &deg;C");
                $("#ct_optime").html(data.optime);
                $("#ct_peak_pow").html(data.peak_pow + " W piek vandaag");
                $("#ct_today_pow").html(data.today_pow + " kWh vandaag");
                $("#ct_total_money").html("&euro;" + data.money);
                $("#ct_today_money").html("&euro;" + data.money_today.toFixed(2) + " vandaag");
                
                // Flags table thing
                if (data.flags != "")
                    $("#flagstable th").parent().after($("<tr><td>" + data.time + "</td><td>" + data.flags + "</td></tr>"));
                
                var tdate = data.time.split(" ");
                var ttime = tdate[1].split(":");
                tdate = tdate[0].split("-");
                
                var tday = parseInt(tdate[2]);
                var tmonth = parseInt(tdate[1])-1;
                var tyear = parseInt(tdate[0]);
                var thour = parseInt(ttime[0]);
                var tmin = parseInt(ttime[1]);
                var tsec = parseInt(ttime[2]);
                var newcoords = [Date.UTC(tyear, tmonth, tday, thour, tmin, tsec),data.hasdata ? data.grid_pow : "-30"];
                var series = pow_chart.series[0].data;
                
                // See whether the data is already in the graph
                if (series[series.length-1].x != newcoords[0])
                    pow_chart.series[0].addPoint(newcoords, true, true);
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