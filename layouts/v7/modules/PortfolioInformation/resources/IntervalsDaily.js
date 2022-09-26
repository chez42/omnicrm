/**
 * Created by ryansandnes on 2017-06-23.
 */
jQuery.Class("IntervalsDaily_Js",{
    currentInstance : false,
    symbols : [],
    symbolData : [],
    chart : [],
    chartData : [],
    selectedCount : 0,
    getInstanceByView : function(){
        var instance = new IntervalsDaily_Js();
        return instance;
    }
},{
    CalculateTWR : function(sdate, edate){
        var self = this;
        var returns = new Array();
        var selected_elements = new Array();
        try {
            var count = 0;
            var average = 0;
            var annual = 0;
            var start = $.datepicker.parseDate("yy-mm-dd", sdate);
            var end = $.datepicker.parseDate("yy-mm-dd", edate);

            $('.data_end_date').each(function(i, obj) {
                var cur = $.datepicker.parseDate("m-d-yy", $(obj).text());
                var val = 1;
                if(cur <= end && cur >= start){
//                    $(this).closest('tr').children('td, th').css('background-color','#98FB98');
                    $(this).closest('tr').show();
                    var begin_value = $(obj).siblings('.data_begin_value');
                    var flow = $(obj).siblings('.data_net_flow');
                    var income = $(obj).siblings('.data_incomeamount');
                    var expense = $(obj).siblings('.data_expense_amount');
                    var investment = $(obj).siblings('.data_investmentreturn');
                    var end_value = $(obj).siblings('.data_end_value').data('end_value');
                    var net_return = $(obj).siblings('.data_net_return').data('net_return');
                    var twr = $(obj).siblings('.data_twr');

                    if(net_return > 1.15 || net_return < 0.85){
//                        console.log(val);
                        $(obj).siblings('.data_net_return').css('background-color','red');
                    }
                    var tmp = {begin_value: begin_value, flow: flow, income: income, expense: expense,
                        end_value: end_value, net_return: net_return, twr: twr};
                    returns.push(tmp);
                    selected_elements.push($(obj).parent());
//                    returns.push(val/100);
                }else{
                    $(this).closest('tr').hide();
                }
            });

            function CalculateReturn(r){
                var val = 1;
//                $(".data_twr").attr("data-calculated_twr",0);//Reset the calculated TWR number for the chart
                $(".data_twr").data("calculated_twr",0);

                $.each(r, function(k, v){
                    val = val * (v.net_return);
                    var tmp = (val - 1) * 100;
                    v.twr.attr("data-calculated_twr", tmp.toFixed(2));
                    v.twr.data("calculated_twr",tmp.toFixed(2));

                    v.twr.text(tmp.toFixed(2));
//                    console.log("Setting data-calculated_twr = " + tmp.toFixed(2));
//                    $(v.twr).data('calculated_twr', tmp.toFixed(2));
                    if(tmp > 0) {
                        v.twr.removeClass('red');
                        v.twr.addClass('green');
                    }
                    if(tmp < 0) {
                        v.twr.removeClass('green');
                        v.twr.addClass('red');
                    }
                    count+=1;
                });
                val = (val - 1) * 100;
                /*
                var val = 0;
                $.each(r, function(k, v){
                    console.log(v);
//                    val = val + v;
                    val = parseFloat(val) + parseFloat(v);
                    count+=1;
                });
                console.log("VALUE IS NOW " + val);*/
                return(val.toFixed(2));
            }
            selected_elements.reverse();
            returns.reverse();
            var r = CalculateReturn(returns);
            average = (r / count).toFixed(2);
            annual = (average * 12).toFixed(2);

            self.selectedCount = count;
            $(".selected_twr").text(r + "%");
            $(".average_return").text(average + "%");
            $(".annual_return").text(annual + "%");

            self.SetCalculatedText(selected_elements);
            self.SetChartTWR();

            self.DetermineColor($(".selected_twr"), r);
            self.DetermineColor($(".average_return"), average);

        }catch(err){
            console.log(err);
        }
    },

    /*This sets the line chart TWR to zero out based on zoom data*/
    SetChartTWR: function(){
        var chart = this.chart;
        var chartData = this.chartData;

        $('.data_end_date').each(function(i, obj){
            var date = $(this).data('date');
            var twr = $(this).siblings('.data_twr').data('calculated_twr');
            $.each(chartData, function(k,v){
                if(v.date == date){
                    v.calculated_twr = twr;
                }
            });
        });

        chart.data = chartData;
    },

    SetCalculatedText: function(elements){
        var self = this;
//        console.log(elements);
//        console.log(elements[0].find("td:eq(0)").data('date'));
        var begin_value = parseFloat(elements[0].children('.data_begin_value').data('begin_value'));
        var end_value = parseFloat(elements.slice(-1).pop().children('.data_end_value').data('end_value'));//Get last array element, slide it, and pop it from the stack
        var begin_date = elements[0].children('.data_end_date').data('date');
        var end_date = elements[elements.length-1].children('.data_end_date').data('date');
        var flow = 0;
        var income = 0;
        var expense = 0;
        var investment = 0;
        var twr = 0;

        $.each(elements, function(k, v){
            var tmp_begin = v.children('.data_begin_value').data('begin_value');
            var tmp_flow = parseFloat(v.children('.data_net_flow').data('net_flow'));
            var tmp_income = parseFloat(v.children('.data_incomeamount').data('incomeamount'));
            var tmp_expense = parseFloat(v.children('.data_expense_amount').data('expense_amount'));
            var tmp_investment = v.children('.data_investmentreturn').data('investmentreturn');
            var tmp_end = v.children('.data_end_value').data('end_value');
            var tmp_net_return = v.children('.data_net_return').data('net_return');
            var tmp_twr = v.children('.data_twr').data('twr');

            flow += tmp_flow;
            income += tmp_income;
            expense += tmp_expense;
        });

        $(".start_date_range").text(begin_date);
        $(".end_date_range").text(end_date);

        self.DetermineColor($(".begin_value"), begin_value);
        $(".begin_value").text("$" + begin_value.toLocaleString());//Set the begin value text
        self.DetermineColor($(".selected_flows"), flow);
        $(".selected_flows").text("$" + flow.toLocaleString());//Set the flow value text
        self.DetermineColor($(".selected_income"), income);
        $(".selected_income").text("$" + income.toLocaleString());//Set the income value text
        self.DetermineColor($(".selected_expenses"), expense);
        $(".selected_expenses").text("$" + expense.toLocaleString());//Set the expense value text
        self.DetermineColor($(".end_value"), end_value);
        $(".end_value").text("$" + end_value.toLocaleString());//Set the end value text

        /*        var tmp_date = $.datepicker.parseDate("mm-dd-yy", begin_date);
                var tmp_formatted = $.datepicker.formatDate( "mm-dd-yy", tmp_date);
                var begin_val = parseFloat(self.symbols[0][tmp_formatted].value).toFixed(2);//parseFloat(self.symbols[0][tmp_formatted].value).toFixed(2);*/

        var count = 0;
        $.each(self.symbolData, function(a, symbol){
            var begin_val = self.ConvertDateAndReturnValueFromSymbolObject(begin_date, "mm-dd-yy", count);
            var end_val = self.ConvertDateAndReturnValueFromSymbolObject(end_date, "mm-dd-yy", count);
            var calculated_index = ( ((end_val/begin_val) * 100) - 100).toFixed(2);
            var average_index = (calculated_index / self.selectedCount).toFixed(2);

            self.DetermineColor($(".begin_value_"+symbol.symbol_id), begin_val);
            $(".begin_value_"+symbol.symbol_id).text(Number(begin_val).toLocaleString());//Set the begin value text
            self.DetermineColor($(".end_value_"+symbol.symbol_id), end_val);
            $(".end_value_"+symbol.symbol_id).text(Number(end_val).toLocaleString());//Set the begin value text
            self.DetermineColor($(".twr_"+symbol.symbol_id), calculated_index);
            $(".twr_"+symbol.symbol_id).text(Number(calculated_index).toLocaleString() + "%");//Set the begin value text
            self.DetermineColor($(".average_return_"+symbol.symbol_id), average_index);
            $(".average_return_"+symbol.symbol_id).text(Number(average_index).toLocaleString() + "%");//Set the begin value text

            count++;
        });
/*
        self.DetermineColor($(".sp_begin_value"), begin_val);
        $(".sp_begin_value").text(Number(begin_val).toLocaleString());//Set the begin value text
        self.DetermineColor($(".sp_end_value"), end_val);
        $(".sp_end_value").text(Number(end_val).toLocaleString());//Set the begin value text
        self.DetermineColor($(".sp_twr"), calculated_index);
        $(".sp_twr").text(Number(calculated_index).toLocaleString() + "%");//Set the begin value text
        self.DetermineColor($(".sp_average_return"), average_index);
        $(".sp_average_return").text(Number(average_index).toLocaleString() + "%");//Set the begin value text
*/
    },

    ConvertDateAndReturnValueFromSymbolObject: function(date, format, id){
        var self = this;

        var tmp_date = $.datepicker.parseDate(format, date);
        var tmp_formatted = $.datepicker.formatDate( format, tmp_date);
        var val = parseFloat(self.symbols[id][tmp_formatted].value).toFixed(2);//parseFloat(self.symbols[0][tmp_formatted].value).toFixed(2);
        return val;
    },

    DetermineColor: function(element, val){
        element.removeClass('red');
        element.removeClass('green');

        if(val >= 0) {
            element.addClass('green');
            return;
        }
        element.addClass('red');
    },

    TimelineChart2: function(){
        console.log("Chart Creating");
        var self = this;
        am4core.useTheme(am4themes_animated);
        am4core.useTheme(am4themes_dark);
        var chart = am4core.create("linechartdiv", am4charts.XYChart);
        chart.padding(0, 15, 0, 15);
        chart.colors.step = 3;

        var mydata = {};
        var symbolData = $.parseJSON($("#selected_indexes").val());//new Array("GSPC", "SP500BDT");
        var symbols = [];

        $.each(symbolData, function(a, symbol){
            symbols.push(symbol.symbol);
        });

        self.symbolData = symbolData;

        console.log("Symbols");
        console.log(self.symbolData);

        var symbol_info;
        var final = [];
        var start_date = $("#start_date").val();
        var end_date = $("#end_date").val();

        $($(".data_net_return").get().reverse()).each(function(e) {
            var date = $(this).siblings(".data_end_date").data('date');
            var tmp = {
                date:$(this).siblings(".data_end_date").data('date'),
                twr:$(this).siblings(".data_twr").data('twr'),
                end_value:$(this).siblings(".data_end_value").data('end_value'),
                net_return:$(this).data('net_return')
            }
            mydata[date] = tmp;
        });

        //Get the index prices
        //TODO:  sdate and edate need to be dynamic, not this hardcoded nonsense
        $.post("index.php", {module:'ModSecurities', action:'PriceInteraction', todo:'getprice', symbol:symbols, sdate:start_date, edate:end_date}, function(response) {
            symbol_info = $.parseJSON(response);//Get index information
            console.log(symbol_info);
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
            console.log(self.symbols);
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
                setTimeout(
                function()
                {
                    zoomToDatesCustom(start, end);
                }, 200);
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

    ClickEvents : function(){
        var self = this;
        $("#ResetIntervals").click(function(){
            var account_numbers = $("#account_numbers").val();
                $.post("index.php", {module:'PortfolioInformation', action:'Tools', todo:'remove_intervals', account_numbers:account_numbers}, function(response) {
                    console.log(response);
                });
        });

        $("#CreateTransactions").click(function(){
            var account_numbers = $("#account_numbers").val();
            var date = $("input[name='createdate']:checked").val();
            $.post("index.php", {module:'PortfolioInformation', action:'CustodianInteractions', todo:'PositionsToTransactions', account_number:account_numbers, date:date}, function(response) {
                if(response == '1')
                    alert("Transactions Created for " + account_numbers + " based on " + date + ".  Refresh the page to confirm change! -- Clicking this button again will duplicate transactions");
                else
                    alert("Transactions Failed to create for " + account_numbers + ".  Make sure a date has been selected, likely the first one needs selected!");
                console.log(response);
            });
        });

        $(".ExportReport").click(function(e){
            e.stopImmediatePropagation();
            $("#start_date").val($("#fromfield").val());
            $("#end_date").val($("#tofield").val());

            self.chart.exporting.getImage("jpg").then(function(imgData){
                $("#line_image").val(encodeURIComponent(imgData));
                $("#IntervalForm").submit();
            });
        });
    },

    registerEvents : function() {
        this.ClickEvents();
//        this.FloatHead();
//        this.Clock();
        if (am4core.isReady) {
            console.log('Ready');
            this.TimelineChart2();
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


/*

<div id="reportrange" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; width: 100%">
    <i class="fa fa-calendar"></i>&nbsp;
    <span></span> <i class="fa fa-caret-down"></i>
</div>

<script type="text/javascript">
$(function() {

    var start = moment().subtract(29, 'days');
    var end = moment();

    function cb(start, end) {
        $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
    }

    $('#reportrange').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
           'Today': [moment(), moment()],
           'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           'Last 7 Days': [moment().subtract(6, 'days'), moment()],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, cb);

    cb(start, end);

});
</script>


 */