jQuery.Class("GH2Report_Js",{
    currentInstance : false,
    valuePieChart : [],
    assetPieChart : [],
    getInstanceByView : function(){
        var instance = new GH2Report_Js();
        return instance;
    }
},{
    ValuePieChart: function(){
        var self = this;
        am4core.options.commercialLicense = true;
        var chart = am4core.create("dynamic_pie_holder", am4charts.PieChart3D);
        var chartData = $.parseJSON($("#holdings_values").val());

        chart.data = chartData;

// Add and configure Series
        var pieSeries = chart.series.push(new am4charts.PieSeries3D());
        pieSeries.slices.template.stroke = am4core.color("#555354");
        pieSeries.dataFields.value = "value";
        pieSeries.dataFields.category = "title";
        pieSeries.fontSize = 14;

        pieSeries.slices.template.strokeWidth = 2;
        pieSeries.slices.template.strokeOpacity = 1;

        pieSeries.labels.horizontalCenter = 'middle';
        pieSeries.labels.verticalCenter = 'middle';

        pieSeries.labels.template.disabled = true;
        /*
        pieSeries.alignLabels = true;
        pieSeries.labels.template.bent = true;
        pieSeries.labels.template.radius = 3;
        pieSeries.labels.template.padding(0,0,0,0);*/

        pieSeries.ticks.template.disabled = true;
//        pieSeries.labels.template.disabled = true;
//        pieSeries.ticks.template.disabled = true;

//        pieSeries.slices.template.states.getKey("hover").properties.shiftRadius = 0;
//        pieSeries.slices.template.states.getKey("hover").properties.scale = 1.1;

        var colorSet = new am4core.ColorSet();
        var colors = [];

        if(chartData === null) { return; }

        var defaultPalette = ["#292447", "#f27b0c", "#9bb556", "#eab378", "#407fe5", "#478899", "#5cafc4", "#ff1a1a", "#2b4224"];

        $.each(chartData,function(key, value){
            var element = jQuery(this);
            var col = element["0"].color;
            if(!col || col === "null" || col === "#ffffff" || col === "#fff") {
                col = defaultPalette[key % defaultPalette.length];
            }
            colors.push(col);
        });

        colorSet.list = colors.map(function(color) {
            return new am4core.color(color);
        });
        pieSeries.colors = colorSet;

        self.valuePieChart = chart;
    },

    AssetPieChart: function(){
        var self = this;
        am4core.options.commercialLicense = true;
        var chart = am4core.create("sector_pie_holder", am4charts.PieChart3D);
        var chartData = $.parseJSON($("#sector_values").val());

        chart.data = chartData;

        chart.depth = 10;
        chart.angle = 10;
// Add and configure Series
        var pieSeries = chart.series.push(new am4charts.PieSeries3D());
        pieSeries.slices.template.stroke = am4core.color("#555354");
        pieSeries.dataFields.value = "value";
        pieSeries.dataFields.category = "title";
        pieSeries.fontSize = 14;

        pieSeries.slices.template.strokeWidth = 2;
        pieSeries.slices.template.strokeOpacity = 1;

        pieSeries.labels.horizontalCenter = 'middle';
        pieSeries.labels.verticalCenter = 'middle';

        pieSeries.labels.template.disabled = true;

        pieSeries.ticks.template.disabled = true;

        var colorSet = new am4core.ColorSet();
        var colors = [];

        if(chartData === null) { return; }

        var defaultPalette = ["#292447", "#f27b0c", "#9bb556", "#eab378", "#407fe5", "#478899", "#5cafc4", "#ff1a1a", "#2b4224"];

        $.each(chartData,function(key, value){
            var element = jQuery(this);
            var col = element["0"].color;
            if(!col || col === "null" || col === "#ffffff" || col === "#fff") {
                col = defaultPalette[key % defaultPalette.length];
            }
            colors.push(col);
        });

        colorSet.list = colors.map(function(color) {
            return new am4core.color(color);
        });
        pieSeries.colors = colorSet;

        self.assetPieChart = chart;
    },

    ClickEvents: function(){
        var self = this;

        $(".ExportReport").click(function(e){
            e.stopImmediatePropagation();
            self.valuePieChart.exporting.getImage("jpg").then(function(imgData){
                $("#pie_image").val(encodeURIComponent(imgData));
                self.assetPieChart.exporting.getImage("jpg").then(function(imgData){
                    $("#sector_pie_image").val(encodeURIComponent(imgData));
                    $("#export").submit();
//                console.log(imgData);
                });
            });
            /*
                        var imgData = self.chartInfo.exporting.getImage("png");
                        $("#pie_image").val(imgData);
                        console.log($("#pie_image").val());
            //            $("#export").submit();*/
        });
    },

    registerEvents : function() {
        this.ClickEvents();
        this.ValuePieChart();
        this.AssetPieChart();
    }
});

jQuery(document).ready(function($) {
    var instance = GH2Report_Js.getInstanceByView();
    instance.registerEvents();
/*
    var pie = DynamicPie_Js.getInstanceByView();
    pie.registerEvents();
//    var chart = DynamicChart_JS.getInstanceByView();

    pie.CreatePie("dynamic_pie_holder", "holdings_values");
    pie.CreatePie("sector_pie_holder", "sector_values", false);
    pie.CreateGraph("dynamic_chart_holder", "t12_balances", "intervalenddateformatted", "intervalendvalue");

//    chart.CreateChart("dynamic_chart_holder", "t12_balances");*/
});