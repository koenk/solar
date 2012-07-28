var highchartsOptions = Highcharts.getOptions(); 

var pow_chart;
$(document).ready(function() {
    var pow_chart_series = [];
    for (var i = 0; i < power_day_data.length; i++) {
        pow_chart_series.push({
            type: 'spline',
            name: 'Vermogen ' + (i + 1),
            pointInterval: 5 * 60 * 1000,
            pointStart: power_day_start[i], // See the index for this var
            data: power_day_data[i] // See the index for this var
        });
    }

    var day_chart_series = [];
    for (var i = 0; i < day_total_data.length; i++) {
        day_chart_series.push({
            type: 'line',
            name: 'Piek vermogen ' + (i + 1),
            pointInterval: 24 * 60 * 60 * 1000, // Day
            pointStart: day_total_start,
            yAxis: 1,
            data: day_peak_data[i],
            color: highchartsOptions.colors[1]
        });
        day_chart_series.push({
            type: 'column',
            name: 'Dag totaal ' + (i + 1),
            pointInterval: 24 * 60 * 60 * 1000, // Day
            pointStart: day_total_start, // See the index for this var
            data: day_total_data[i], // See the index for this var
            color: '#4572A7'
        });
    }
    var week_chart_series = [];
    for (var i = 0; i < week_total_data.length; i++) {
        week_chart_series.push({
            type: 'column',
            name: 'Week totaal',
            pointStart: 0,
            data: week_total_data[i] // See the index for this var
        });
    }
    var month_chart_series = [];
    for (var i = 0; i < month_total_data.length; i++) {
        month_chart_series.push({
            type: 'column',
            name: 'Maand totaal',
            data: month_total_data[i] // See the index for this var
        });
    }

	pow_chart = new Highcharts.Chart({
		credits: {enabled: false},
		chart: {
			renderTo: 'power_day',
			zoomType: 'x',
			spacingRight: 20
		},
		title: {
			text: 'Opbrengst'
		},
		xAxis: {
			type: 'datetime',
			maxZoom: 3600000, // hour
			title: {
				text: 'Tijd'
			}
		},
		yAxis: {
			title: {
				text: 'Watt'
			},
			min: 0.0,
			startOnTick: false,
			showFirstLabel: false
		},
		tooltip: {
			formatter: function() {
               var point = this.points[0];
               return '<b>'+ point.series.name +'</b><br/>'+
                  Highcharts.dateFormat('%a %e-%m-%Y %H:%M', this.x) + '<br/>'+
                  point.y + ' Watt';
            },
			shared: true               
		},
		legend: {
			enabled: false
		},
		plotOptions: {
			spline: {
				lineWidth: 2,
				marker: {
					enabled: false,
					states: {
						hover: {
							enabled: true,
							radius: 5
						}
					}
				},
				shadow: false,
				states: {
					hover: {
						lineWidth: 2
					}
				}
			}
		}, 
		series: pow_chart_series
		}
    );
		
    // -----------------------------------
    // -- Day total ( + peak that day)
    // -----------------------------------
		
	day_chart = new Highcharts.Chart({
		credits: {enabled: false},
		chart: {
			renderTo: 'power_total',
			spacingRight: 20
		},
		title: {
			text: 'Opbrengst per dag'
		},
		xAxis: {
			type: 'datetime',
            tickInterval: 5 * 24 * 3600 * 1000, // Five days
			title: {
				text: 'Datum'
			}
		},
		yAxis: [{ // Primary (KWh)
			title: {
				text: 'kWh'
			},
			min: 0.0,
            max: 6.0,
			startOnTick: false,
			showFirstLabel: false,
			labels: {
				formatter: function() {
					return this.value + ' KWh';
				}
			}
		}, { // Seconday (right, Watt)
			title: {
				text: 'W'
			},
			min: 0,
            max: 600,
			startOnTick: false,
			showFirstLabel: false,
			labels: {
				formatter: function() {
					return this.value + ' W';
				},
				style: {
					color: '#4572A7'
				}
			},
			opposite: true
		}],
		tooltip: {
			shared: false,
			formatter: function() {
				return '<b>' + this.series.name + '</b><br>' +
					Highcharts.dateFormat('%A %e-%m-%Y', this.x) + '<br>' +
					this.y + ' ' + (this.series.name == 'Piek vermogen' ? 'W' : 'kWh');
			}
		},
		legend: {
			enabled: false
		},
		plotOptions: {
			line: {
				lineWidth: 1,
				marker: {
					enabled: false,
					states: {
						hover: {
							enabled: true,
							radius: 5
						}
					}
				},
				shadow: false,
				states: {
					hover: {
						lineWidth: 1
					}
				}
			},
            area: {
				lineWidth: 0,
				marker: {
					enabled: false,
					states: {
						hover: {
							enabled: true,
							radius: 1
						}
					}
				},
				shadow: false,
				states: {
					hover: {
						lineWidth: 0
					}
				}
			}
		}, 
		series: day_chart_series
		}
    );
    
    // -----------------------------------
    // -- WEEK
    // -----------------------------------
    
    week_chart = new Highcharts.Chart({
		credits: {enabled: false},
		chart: {
			renderTo: 'week_total',
			zoomType: 'x',
			spacingRight: 20
		},
		title: {
			text: 'Opbrengst per week'
		},
		xAxis: {
            min: 0,
            max: 53,
			tickInterval: 2,
			maxZoom: 3, // hour
			title: {
				text: 'Week'
			}
		},
		yAxis: { // Primary (KWh)
			title: {
				text: 'kWh'
			},
			min: 0,
			startOnTick: false,
			showFirstLabel: false,
			labels: {
				formatter: function() {
					return this.value + ' KWh';
				}
			}
		},
		tooltip: {
			shared: false,
			formatter: function() {
				return '<b>' + this.series.name + '</b><br>' +
					'Week ' + this.x + '<br>' +
					this.y + ' kWh';
			}
		},
		legend: {
			enabled: false
		},
		series: week_chart_series
		}
    );
    
    // -----------------------------------
    // -- MONTH
    // -----------------------------------
    
    month_chart = new Highcharts.Chart({
		credits: {enabled: false},
		chart: {
			renderTo: 'month_total',
			spacingRight: 20
		},
		title: {
			text: 'Opbrengst per maand'
		},
		xAxis: {
            categories: ['Jan', 'Feb', 'Mrt', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'],
			title: {
				text: 'Week'
			}
		},
		yAxis: { // Primary (KWh)
			title: {
				text: 'kWh'
			},
			min: 0,
			startOnTick: false,
			showFirstLabel: false,
			labels: {
				formatter: function() {
					return this.value + ' KWh';
				}
			}
		},
		tooltip: {
			shared: false,
			formatter: function() {
				return '<b>' + this.series.name + '</b><br>' +
					this.x + '<br>' +
					this.y + ' kWh';
			}
		},
		legend: {
			enabled: false
		},
		series: month_chart_series
		}
    );
    
    // -----------------------------------
    // -- RESOL
    // -----------------------------------
    
    mintemp1 = Math.min.apply(Math, resol_t1_data);
    mintemp2 = Math.min.apply(Math, resol_t2_data);
    mintemp3 = Math.min.apply(Math, resol_t3_data);
    mintemp = Math.min(mintemp1, mintemp2, mintemp3, 0.0);
    
    resol_chart = new Highcharts.Chart({
		credits: {enabled: false},
		chart: {
			renderTo: 'resol_graph',
			zoomType: 'x',
			spacingRight: 20
		},
		title: {
			text: 'Zonneboiler'
		},
		xAxis: {
			type: 'datetime',
			maxZoom: 3600000, // hour
			title: {
				text: 'Tijd'
			}
		},

		yAxis: [{
			title: {
				text: 'Temp. °C'
			},
			min: mintemp,
			startOnTick: false,
			showFirstLabel: false,
            labels: {
				formatter: function() {
					return this.value + ' °C';
				}
			}
		},{
            title: {
				text: 'Pomp'
			},
			min: 0.0,
            max: 500.0,
			startOnTick: false,
			showFirstLabel: false,
            opposite: true,
            labels: {
				formatter: function() {
					return this.value > 100 ? '' : this.value + '%';
				}
			}
        }],
		tooltip: {
			shared: false,
			formatter: function() {
				return '<b>' + this.series.name + '</b><br>' +
					Highcharts.dateFormat('%A %e-%m-%Y', this.x) + '<br>' +
					this.y + (this.series.name == 'Pomp' ? '%' : ' °C');
			}
		},
		legend: {
			enabled: true
		},
		plotOptions: {
			spline: {
				lineWidth: 2,
				marker: {
					enabled: false,
					states: {
						hover: {
							enabled: true,
							radius: 5
						}
					}
				},
				shadow: false,
				states: {
					hover: {
						lineWidth: 2
					}
				}
			},
            area: {
				lineWidth: 0,
				marker: {
					enabled: false,
					states: {
						hover: {
							enabled: true,
							radius: 3
						}
					}
				},
				shadow: false,
				states: {
					hover: {
						lineWidth: 0
					}
				}
			}
		}, 
		series: [{
                type: 'area',
                name: 'Pomp',
                pointInterval: 5 * 60 * 1000,
                pointStart: resol_start, // See the index for this var
                data: resol_p1_data, // See the index for this var
                yAxis: 1
            },{
                type: 'spline',
                name: 'Temp. Panelen',
                pointInterval: 5 * 60 * 1000,
                pointStart: resol_start, // See the index for this var
                data: resol_t1_data // See the index for this var
            },{
                type: 'spline',
                name: 'Temp. Boiler',
                pointInterval: 5 * 60 * 1000,
                pointStart: resol_start, // See the index for this var
                data: resol_t2_data, // See the index for this var
                color: "#FF7F30"
            },{
                type: 'spline',
                name: 'Temp. Zwembad',
                pointInterval: 5 * 60 * 1000,
                pointStart: resol_start, // See the index for this var
                data: resol_t3_data, // See the index for this var
                color: "#FFDF65"
            }]
		}
    );
	}
);
