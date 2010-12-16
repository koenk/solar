$(document).ready(function() {
	chart1 = new Highcharts.Chart({
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
			tickInterval: 24 * 3600 * 1000, // day
			maxZoom: 3600000, // hour
			title: {
				text: 'Tijd'
			}
		},
		yAxis: {
			title: {
				text: 'kWh'
			},
			min: 0.0,
			startOnTick: false,
			showFirstLabel: false
		},
		tooltip: {
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
			type: 'column',
			name: 'Vermogen',
			pointInterval: 24 * 60 * 60 * 1000,
			pointStart: power_total_start, // See the index for this var
			data: power_total_data // See the index for this var
			}]
		});
	});
