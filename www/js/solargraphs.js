var pow_chart;
$(document).ready(function() {
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
		series: [{
			type: 'spline',
			name: 'Vermogen',
			pointInterval: 5 * 60 * 1000,
			pointStart: power_day_start, // See the index for this var
			data: power_day_data // See the index for this var
			}]
		});
		// -----------------------------------
		
		chart1 = new Highcharts.Chart({
		credits: {enabled: false},
		chart: {
			renderTo: 'power_total',
			zoomType: 'x',
			spacingRight: 20
		},
		title: {
			text: 'Opbrengst per dag'
		},
		xAxis: {
			type: 'datetime',
			tickInterval: 5 * 24 * 3600 * 1000, // Five days
			maxZoom: 3600000, // hour
			title: {
				text: 'Datum'
			}
		},
		yAxis: [{ // Primary (KWh)
			title: {
				text: 'kWh'
			},
			min: 0.0,
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
					this.y + ' ' + (this.series.name == 'Piek vermogen' ? 'W' : 'Kwh');
			}
		},
		legend: {
			enabled: false
		},
		plotOptions: {
			spline: {
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
			}
		}, 
		series: [{
				type: 'column',
				name: 'Dag totaal',
				pointInterval: 24 * 60 * 60 * 1000, // Day
				pointStart: power_total_start, // See the index for this var
				data: power_total_data // See the index for this var
			},
			{
				type: 'spline',
				name: 'Piek vermogen',
				pointInterval: 24 * 60 * 60 * 1000, // Day
				pointStart: power_total_start,
				yAxis: 1,
				data: power_peak_data
			}]
		});
	});
