/**
 * Created by ryansandnes on 2017-06-23.
 */
jQuery.Class("BalanceDetails_Js",{
    currentInstance : false,
    symbols : [],
    chart : [],
    chartData : [],
    selectedCount : 0,
    getInstanceByView : function(){
        var instance = new BalanceDetails_Js();
        return instance;
    }
},{
    BalanceChart: function(){
        console.log("Chart Creating");
        var self = this;
        am4core.useTheme(am4themes_animated);
        am4core.useTheme(am4themes_dark);
        var chart = am4core.create("linechartdiv", am4charts.XYChart);
        chart.padding(0, 15, 0, 15);
        chart.colors.step = 3;

        //Get the index prices
        //TODO:  sdate and edate need to be dynamic, not this hardcoded nonsense
        $.post("index.php", {module:'PortfolioInformation', action:'BalanceDetails'}, function(response) {
            console.log(response);
            return;
            symbol_info = $.parseJSON(response);//Get index information
            var count = 0;
            var tmpSymbols = {};
            $.each(symbol_info, function(a, symbol){
                tmpSymbols[count] = symbol;//.push(symbol);
//                self.symbols["symbol_" + count] = symbol;
                var symbol_keys = {};
                $.each(symbol, function(k, v){
                    var parsed_date = $.datepicker.parseDate("yy-m-d", v.date);
                    var formatted = $.datepicker.formatDate( "mm-dd-yy", parsed_date);
                    if(typeof mydata[formatted] !== 'undefined') {
                        var tmp = mydata[formatted];
                        tmp['symbol_'+count] = v.value;
                        tmp['calculated_twr'] = 0;
                        mydata[formatted] = tmp;
                    }
                    var tmp_date = $.datepicker.parseDate("yy-m-d", v.date);
                    var tmp_formatted = $.datepicker.formatDate( "mm-dd-yy", tmp_date);
                    symbol_keys[tmp_formatted] = v;
                });
                tmpSymbols[count] = symbol_keys;
                count = count + 1;
            });
            self.symbols = tmpSymbols;
//            console.log(self.symbols);
            $.each(mydata, function(k, v){
                v.amcharts_date = new Date($.datepicker.parseDate("m-d-yy", v.date));
//                console.log(v.symbol_1);
                if(typeof(v.symbol_1) === 'undefined'){
                    v.symbol_1 = 0;
                    v.calculated_twr = 0;
                }
                final.push(v);
            });
            console.log('About to be done');

        }).done(function(){
            console.log('Done portion started');
            self.chartData = final;
            chart.data = final;

// the following line makes value axes to be arranged vertically.
            chart.leftAxesContainer.layout = "vertical";

// uncomment this line if you want to change order of axes
//chart.bottomAxesContainer.reverseOrder = true;
            var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
            dateAxis.renderer.grid.template.location = 0;
            dateAxis.renderer.ticks.template.length = 8;
            dateAxis.renderer.ticks.template.strokeOpacity = 0.1;
            dateAxis.renderer.grid.template.disabled = true;
            dateAxis.renderer.ticks.template.disabled = false;
            dateAxis.renderer.ticks.template.strokeOpacity = 0.2;
            dateAxis.renderer.minLabelPosition = 0.01;
            dateAxis.renderer.maxLabelPosition = 0.99;
            dateAxis.keepSelection = true;

            dateAxis.groupData = true;
            dateAxis.groupCount = 600;
            dateAxis.minZoomCount = 1;

//            console.log(dateAxis.mainBaseInterval);

// these two lines makes the axis to be initially zoomed-in
// dateAxis.start = 0.7;
// dateAxis.keepSelection = true;
            var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
            valueAxis.tooltip.disabled = true;
            valueAxis.zIndex = 1;
            valueAxis.renderer.baseGrid.disabled = true;
// height of axis
            valueAxis.height = am4core.percent(65);

            valueAxis.renderer.gridContainer.background.fill = am4core.color("#000000");
            valueAxis.renderer.gridContainer.background.fillOpacity = 0.05;
            valueAxis.renderer.inside = true;
            valueAxis.renderer.labels.template.verticalCenter = "bottom";
            valueAxis.renderer.labels.template.padding(2, 2, 2, 2);

//valueAxis.renderer.maxLabelPosition = 0.95;
            valueAxis.renderer.fontSize = "0.8em"

            var series1 = chart.series.push(new am4charts.LineSeries());
            series1.dataFields.dateX = "amcharts_date";
            series1.dataFields.valueY = "calculated_twr";
            series1.dataFields.valueX = "end_value";
//        series1.dataFields.valueYShow = "change";
//            series1.tooltipText = "{name}: {valueY.change.formatNumber('[#0c0]+#.00|[#c00]#.##|0')}%";
//            series1.tooltipText = "{name}: {valueY.formatNumber('[#0c0]+#.00|[#c00]#.##|0')}%, (${endValue.formatNumber('###,###.##')})";//";
//            series1.tooltipText = "{name}: {valueY.formatNumber('###,###.##')}%, (${valueX.formatNumber('###,###.##')})";//";
            series1.tooltipText = "{name}: {valueY.formatNumber('[#0c0]+#.00|[#c00]#.##|0')}%, (${valueX.formatNumber('###,###.##')})";
            series1.name = "Portfolio TWR";
            series1.tooltip.getFillFromObject = false;
            series1.tooltip.getStrokeFromObject = true;
            series1.tooltip.background.fill = am4core.color("#fff");
            series1.tooltip.background.strokeWidth = 2;
            series1.tooltip.label.fill = series1.stroke;
            series1.groupFields.valueY = "open";
            series1.dataItems.template.locations.dateX = 0;

            var series2 = chart.series.push(new am4charts.LineSeries());
            series2.dataFields.dateX = "amcharts_date";
            series2.dataFields.valueY = "symbol_0";
            series2.dataFields.valueYShow = "changePercent";
            series2.tooltipText = "{name}: {valueY.changePercent.formatNumber('[#0c0]+#.00|[#c00]#.##|0')}%, ({valueY.formatNumber('###,###.##')})";
            series2.name = "S&P 500";
            series2.tooltip.getFillFromObject = false;
            series2.tooltip.getStrokeFromObject = true;
            series2.tooltip.background.fill = am4core.color("#fff");
            series2.tooltip.background.strokeWidth = 2;
            series2.tooltip.label.fill = series2.stroke;
            series2.groupFields.valueY = "open";
            series2.dataItems.template.locations.dateX = 0;

            chart.cursor = new am4charts.XYCursor();
            /**
             * Set up external controls
             */

// Date format to be used in input fields
            var inputFieldFormat = "yyyy-MM-dd";

            function SetButtonColor(element, color){
                $(".amcharts-input").css('backgroundColor', 'lightgray');
                element.css('backgroundColor', color);
            }

            document.getElementById("lyr").addEventListener("click", function() {
                SetButtonColor($(this), 'lightgreen');
                zoomToDatesCustom("2022-01-01", "2022-12-31");
            });

            document.getElementById("b1m").addEventListener("click", function() {
                var d = GetDateMinusMonths(1);
                var start = chart.dateFormatter.format(d, "yyyy-MM-dd");
                var end = chart.dateFormatter.format(Date(), "yyyy-MM-dd");
                SetButtonColor($(this), 'lightgreen');
                zoomToDatesCustom(start, end);
                /*                var max = dateAxis.groupMax["day1"];
                                var date = new Date(max);
                                am4core.time.add(date, "month", -1);
                                zoomToDates(date);
                                */
            });

            document.getElementById("b3m").addEventListener("click", function() {
                var d = GetDateMinusMonths(3);
                var start = chart.dateFormatter.format(d, "yyyy-MM-dd");
                var end = chart.dateFormatter.format(Date(), "yyyy-MM-dd");
                SetButtonColor($(this), 'lightgreen');
                zoomToDatesCustom(start, end);
            });

            document.getElementById("b6m").addEventListener("click", function() {
                var d = GetDateMinusMonths(6);
                var start = chart.dateFormatter.format(d, "yyyy-MM-dd");
                var end = chart.dateFormatter.format(Date(), "yyyy-MM-dd");
                SetButtonColor($(this), 'lightgreen');
                zoomToDatesCustom(start, end);
            });

            document.getElementById("b1y").addEventListener("click", function() {
                var d = GetDateMinusMonths(12);
                var start = chart.dateFormatter.format(d, "yyyy-MM-dd");
                var end = chart.dateFormatter.format(Date(), "yyyy-MM-dd");
                SetButtonColor($(this), 'lightgreen');
                zoomToDatesCustom(start, end);
            });

            document.getElementById("bytd").addEventListener("click", function() {
                var d = GetDateMinusMonths(1);
                var year = d.getFullYear();
                var start = chart.dateFormatter.format(d, year + "-01-01");
                var end = chart.dateFormatter.format(Date(), "yyyy-MM-dd");
                SetButtonColor($(this), 'lightgreen');
                zoomToDatesCustom(start, end);
            });

            document.getElementById("bmax").addEventListener("click", function() {
                var min = new Date(dateAxis.groupMin.day1);//Get the minimum date from the chart
                var max = new Date(dateAxis.groupMax.day1);
                var start = chart.dateFormatter.format(min, "yyyy-MM-dd");
                var end = chart.dateFormatter.format(max, "yyyy-MM-dd");
                SetButtonColor($(this), 'lightgreen');
//                console.log(start);
//                console.log(end);
                zoomToDatesCustom(start, end);
//                console.log(chart.dateFormatter.format(min, "yyyy-MM-dd"));
                /*
                var min = dateAxis.groupMin["day1"];
                var date = new Date(min);
                zoomToDates(date);*/
            });

            dateAxis.events.on("selectionextremeschanged", function() {
                updateFields();
            });

//            dateAxis.events.on("extremeschanged", updateFields);

            $("#fromfield").datepicker({
                format: 'yyyy-mm-dd'
            }).on('changeDate', function(e){
                updateZoom();
                var start = $(this).val();
                var end = $("#tofield").val();
                zoomToDatesCustom(start, end);
            });

            $("#tofield").datepicker({
                format: 'yyyy-mm-dd'
            }).on('changeDate', function(e){
                updateZoom();
                var start = $("#fromfield").val();
                var end = $(this).val();
                zoomToDatesCustom(start, end);
            });

            //Returns the Date object of today - num months
            function GetDateMinusMonths(num_months){
                var dt = new Date();
                var month = dt.getMonth();
                month = month - num_months;
                dt.setMonth(month);
                return dt;
            }

            function updateFields() {
                var minZoomed = dateAxis.minZoomed + am4core.time.getDuration(dateAxis.mainBaseInterval.timeUnit, dateAxis.mainBaseInterval.count) * 0.5;
                document.getElementById("fromfield").value = chart.dateFormatter.format(minZoomed, inputFieldFormat);
                document.getElementById("tofield").value = chart.dateFormatter.format(new Date(dateAxis.maxZoomed), inputFieldFormat);
            }

            document.getElementById("fromfield").addEventListener("keyup", updateZoom);
            document.getElementById("tofield").addEventListener("keyup", updateZoom);

            var zoomTimeout;
            function updateZoom() {
                if (zoomTimeout) {
                    clearTimeout(zoomTimeout);
                }
                zoomTimeout = setTimeout(function() {
                    var start = document.getElementById("fromfield").value;
                    var end = document.getElementById("tofield").value;

                    if ((start.length < inputFieldFormat.length) || (end.length < inputFieldFormat.length)) {
                        return;
                    }
                    var startDate = chart.dateFormatter.parse(start, inputFieldFormat);
                    var endDate = chart.dateFormatter.parse(end, inputFieldFormat);

                    if (startDate && endDate) {
                        dateAxis.zoomToDates(startDate, endDate);
                    }
                }, 500);
            }

            function zoomToDates(date) {
                var min = dateAxis.groupMin["day1"];
                var max = dateAxis.groupMax["day1"];
                dateAxis.keepSelection = true;
                //dateAxis.start = (date.getTime() - min)/(max - min);
                //dateAxis.end = 1;

                dateAxis.zoom({start:(date.getTime() - min)/(max - min), end:1});
            }

            function zoomToDatesCustom(start, end){
                dateAxis.zoomToDates(start, end);
                self.CalculateTWR(start, end);
            }

//            chart.events.on("ready", function () {
            var d = GetDateMinusMonths(3);
            var start = chart.dateFormatter.format(d, "yyyy-MM-dd");
            var end = chart.dateFormatter.format(Date(), "yyyy-MM-dd");
            SetButtonColor($("#b3m"), "lightgreen");
            console.log('auto zoom');
            zoomToDatesCustom(start, end);
//            });

        });

        self.chart = chart;
    },

    FloatHead : function(){
        $('#IntervalTable').floatThead({
            position: 'fixed'
        });

        /*        $('a#change-dom').click(function(){ //click to remove
                    $(this).parent().remove();
                    //DOM has changed. must reflow floatThead
                    $demo1.floatThead('reflow');
                });*/
    },

    registerEvents : function() {
        this.ClickEvents();
//        this.FloatHead();
//        this.Clock();
        if (am4core.isReady) {
            console.log('Ready');
            this.BalanceChart();
        } else {
            console.log('Not Ready');
            am4core.ready(this.TimelineChart2());
        }
        var vtigerInstance = Vtiger_Index_Js.getInstance();
        vtigerInstance.registerEvents();
    }
});
//REMOVED THE READY REQUIREMENT SO THIS LOADS IN A WIDGET EVEN AFTER A REFRESH
jQuery(document).ready(function($) {
    var instance = IntervalsDaily_Js.getInstanceByView();
    $( window ).on( "load", function() {
        console.log('loaded');
        instance.registerEvents();
    });
});