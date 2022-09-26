/**
 * Created by ryansandnes on 2017-05-24.
 */

function UpdateStatus(code, element){
    $.post("GetStatus.php", {code:code}, function(response) {
        $(element).html(response);
		setTimeout(UpdateStatus(code, element), 5000);
    });
}

jQuery.Class("FileAdministration_Module_Js",{
    warning: true,
    currentInstance : false,
    table: Array(),

    getInstanceByView : function(){
        var instance = new FileAdministration_Module_Js();
        return instance;
    },

},{
    ClickEvents: function(){
        var self = this;

        $("#PullRecalculate").click(function(e){
			
			var params = {}
			params.module = 'PortfolioInformation';
			params.parent = 'Settings';
			params.action = 'CustodianInteractions';
			params.todo = 'PullRecalculate';
			params.custodian = 'ALL';
			
			app.request.post({"data":params}).then(
				function(err,data) {
				
				}
			);
			
            UpdateStatus('TDUPDATER', '.current-status');
        });

        $("#add-row").click(function(e){
            self.table.addRow({});
            $(".tabulator-cell").css("height","27px");
        });

        $("#RecalculateHomepageWidgets").click(function(e){
            var consolidate = $("#consolidateDays").val();
			
			var params = {}
			params.module = 'PortfolioInformation';
			params.parent = 'Settings';
			params.action = 'CustodianInteractions';
			params.todo = 'RecalculateHomepageWidgets';
			params.consolidateDays = consolidate;
			
			app.request.post({"data":params}).then(
				function(err,data) {
					alert(data);
				}
			);
			
            UpdateStatus('TDUPDATER', '.current-status');
        });

        $("#ClearReconciledTransactions").click(function(e){
            
			
			var params = {};
			
			params.module = 'Transactions';
			params.parent = 'Settings';
			params.action = 'FixTransaction';
			params.todo = 'ClearReconciledTransactions';
			
			app.request.post({"data":params}).then(
				function(err,response) {
					alert(response + " transactions removed");
				}
			);
			


        });

        $("#RecalculateAllHistoricalBalances").click(function(e){
			
			var params = {}
			params.module = 'PortfolioInformation';
			params.parent = 'Settings';
			params.action = 'CustodianInteractions';
			params.todo = 'RecalculateAllHistoricalBalances';
			
			app.request.post({"data":params}).then(
				function(err,response) {
				
				}
			);
			
            UpdateStatus('TDBALANCEUPDATE', '.calculation-status');
			
        });

        $("#RecalculateXBalances").click(function(e){
            
			var numDays = $("#numDays").val();
            
			var params = {}
			params.module = 'PortfolioInformation';
			params.parent = 'Settings';
			params.action = 'CustodianInteractions';
			params.todo = 'RecalculateXBalances';
			params.days = numDays;
			
			
			app.request.post({"data":params}).then(
				function(err,response) {
					alert(response);
				}
			);
			

        });

    },

    parseButton : function(value, data, cell, row, options){
        
		var self = this;
        
		var id = value.getRow().getData().id;
		
        $(".parseData").on("click", function(e){
            
			e.stopPropagation();
            
			e.stopImmediatePropagation();
            
			var parseID = $(this).data('id');
            
			var num_days = $(this).siblings('.num_days').val();
			
			var params = {}
			params.module = 'PortfolioInformation';
			params.parent = 'Settings';
			params.action = 'CustodianInteractions';
			params.todo = 'ParseData';
			params.parseID = parseID;
			params.parse_type = 'parse_all';
			params.num_days = num_days;
			

			app.request.post({"data":params}).then(
				function(err,response) {
				
				}
			);
			
			
            UpdateStatus('MANUALPARSING', '.parse-status');
        });
        return "<button class='parseData' data-id="+id+">Parse Data</button> <input class='num_days' type='text' value='7' />";
    },

    deleteButton : function(value, data, cell, row, options){
        
		var self = this;

        var id = value.getRow().getData().id;
        
		$(".deleteRepCode").on("click", function(e){
            
			e.stopPropagation();
            
			e.stopImmediatePropagation();
			
            var deleteID = $(this).data('id');

			var params = {}
			params.module = 'PortfolioInformation';
			params.parent = 'Settings';
			params.action = 'FileAdministration';
			params.todo = 'DeleteRep';
			params.deleteID = deleteID;

			app.request.post({"data":params}).then(
				function(err,response) {
					 value.getRow().getPrevRow().delete();
				}
			);
		
		});
		
        return "<button class='deleteRepCode' data-id="+id+">Delete ID: " + id + "</button>";
    },

    RenderTable : function(){
        
		var self = this;
        
		
		var params = {}
		params.module = 'PortfolioInformation';
		params.parent = 'Settings';
		params.action = 'FileAdministration';
		params.todo = 'getlocations';
		
		app.request.post({"data":params}).then(
			function(err,response) {
			
				var table = new Tabulator("#file-locations-table", {
					data:$.parseJSON(response),
					addRowPos:"top",
					layout:"fitColumns",
					columns:[
						{title:"ID", field:"id", sorter:"number", formatter:self.deleteButton},
						{title:"Custodian", field:"custodian", editor:"select", editorParams:{values:{"TD":"TD", "Fidelity":"Fidelity", "Fidelity(FTP)":"FidelityFTP", "Schwab":"Schwab", "Pershing":"Pershing", "RaymondJames":"Raymond James", "Disabled":"Disabled"}}},
						{title:"Rep Code", field:"rep_code", editor:true},
						{title:"Omni Code", field:"omni_code", editor:true},
						{title:"Active", field:"currently_active", editor:"select", formatter:"tickCross", sorter:"number", editorParams:{values:{"0":"No", "1":"Yes"}}},
						{title:"Parse", formatter:self.parseButton}
					],
					cellEdited:function(cell){
						var row = cell.getRow();
						var data = row.getData();
						$.post("index.php", {module:'PortfolioInformation', action:'FileAdministration', todo:'UpdateFileField', RowData:data}, function(response){
							var id = response;
							if(id > 0) {
								row.update({id: id});
							}
						});
					}
				});
				self.table = table;
			}
		);
    },

    registerEvents : function() {
        this.RenderTable();
        this.ClickEvents();
        UpdateStatus('TDBALANCEUPDATE', '.calculation-status');
    }
});

jQuery(document).ready(function($) {
    var instance = FileAdministration_Module_Js.getInstanceByView();
    instance.registerEvents();
});
