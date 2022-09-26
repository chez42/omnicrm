<?php
require_once("libraries/Reporting/ReportCommonFunctions.php");

include_once "libraries/custodians/cCustodian.php";

require_once("libraries/Reporting/PerformanceReport.php");

require_once("libraries/reports/new/holdings_report.php");
include_once("modules/PortfolioInformation/models/PrintingContactInfo.php");

class PortfolioInformation_PerformanceReport_View extends Vtiger_Index_View{
	
	function __construct() {
		parent::__construct();
		$this->exposeMethod('viewForm');
		$this->exposeMethod('DownloadReport');
	}
	
	function viewForm(Vtiger_Request $request){
	    
	    $sourceModule = $request->getModule();
	    
	    $viewer = $this->getViewer($request);
		
		$cvId = $request->get('viewname');
		$selectedIds = $request->get('selected_ids');
		$excludedIds = $request->get('excluded_ids');
		
		$viewer->assign('CVID', $cvId);
		$viewer->assign('SELECTED_IDS', $selectedIds);
		$viewer->assign('EXCLUDED_IDS', $excludedIds);
		
		$searchKey = $request->get('search_key');
        $searchValue = $request->get('search_value');
		$operator = $request->get('operator');
        if(!empty($operator)) {
			$viewer->assign('OPERATOR',$operator);
			$viewer->assign('ALPHABET_VALUE',$searchValue);
            $viewer->assign('SEARCH_KEY',$searchKey);
		}
		
		$searchParams = $request->get('search_params');
        if(!empty($searchParams)) {
            $viewer->assign('SEARCH_PARAMS',$searchParams);
        }
	    
		$viewer->assign("TYPE", "PerformanceReport");
		
	    echo $viewer->view('PerformanceReportDownloadForm.tpl','PortfolioInformation',true);
	   
	}
	
	
	function DownloadReport(Vtiger_Request $request){
		set_time_limit(-1);

		global $adb;
		
		$record_ids = $this->getRecordsListFromRequest($request);
		
		$result = $adb->pquery("SELECT * FROM vtiger_portfolioinformation
        INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid
        WHERE vtiger_crmentity.deleted = 0 AND
        vtiger_portfolioinformation.portfolioinformationid IN (".generateQuestionMarks($record_ids).")",$record_ids);
		
		$viewer = $this->getViewer($request);
		
		$pdf_files = array();
		
		for($i = 0; $i < $adb->num_rows($result); $i++){
			
			$account_no = $adb->query_result($result, $i, "account_number");
			
			$file_content = $this->GenerateReport($account_no, $request);
		    
			file_put_contents("cache/$account_no" . '_' . date("Y-m-d") . ".pdf", $file_content);
			
			$pdf_files[]  = "cache/$account_no" . '_' . date("Y-m-d") . ".pdf";
		
		}
		
		$zipname  = 'cache/'.date('Y-m-d').'.zip';
        
		$files = implode("' '", $pdf_files);
		
		$files = "'" . $files . "'";
		
		$zip_password = strtotime(date("Y-m-d H:i:s"));
		
		@exec("zip -D -j $zipname $files");
		
		while(ob_get_level()) {
            ob_end_clean();
        }
		
        header('Content-Type: application/zip');
		
        header('Content-disposition: attachment; filename='.basename($zipname));
        
		readfile($zipname);
        
        foreach ($pdf_files as $file) {
            unlink($file);
        }
        
        unlink($zipname);
	
	}
	
	
	
	
	function preProcess(Vtiger_Request $request, $display=true) {
		
		global $adb;
		
		$mode = $request->get('mode');
		
		if(!$mode){
			
			parent::preProcess($request, false);
			
			$viewer = $this->getViewer($request);
			
			$portfolio_result = $adb->pquery("select * from vtiger_portfolioinformation
			inner join vtiger_portfolioinformationcf on 
			vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid			
			inner join vtiger_contactdetails  on contact_link = contactid 
			inner join vtiger_contactaddress on contactaddressid = contactid

			where account_number = ?", array($request->get("account_number")));
			
			$record_id = $adb->query_result($portfolio_result, 0, "portfolioinformationid");
			
			$recordModel = Vtiger_DetailView_Model::getInstance("PortfolioInformation", $record_id);
			
			$recordModel = $recordModel->getRecord();
			
			$viewer->assign('RECORD', $recordModel);
			
			$moduleName = $request->getModule();
			if($display) {
				$this->preProcessDisplay($request);
			}
			
		} else {
			return true;
		}
	}

	
    public function postProcess(Vtiger_Request $request) {
		
        $mode = $request->get('mode');
		
		if(!$mode){
			parent::postProcess($request);	
		} else {
			return true;
		}
	}

	
	public  function DetermineIntervalStartDate($account_number, $sdate){
		
		global $adb;
		
		$questions = generateQuestionMarks($account_number);

		$query = "SELECT DATE_ADD(MAX(intervalbegindate), INTERVAL 1 DAY) AS begin_date
              FROM intervals_daily 
              WHERE accountnumber IN ({$questions}) AND intervalbegindate <= ?";
			  
		$result = $adb->pquery($query, array($account_number, $sdate));
		
		if($adb->num_rows($result) > 0){
			
			$result = $adb->query_result($result, 0, 'begin_date');
			
			if(is_null($result))
				return $sdate;
				
			return $result;
		
		}
		
		return $sdate;
	
	}

     function process(Vtiger_Request $request) {
        
		global $adb, $current_user;
		
		$mode = $request->get('mode');
		
		if(!empty($mode)) {
			$this->invokeExposedMethod($mode, $request);
			return;
		}
		
		$viewer = $this->getViewer($request);
		
		$account_no = $request->get("account_number");
		
		$file_content = $this->GenerateReport($account_no, $request);
		
		$viewer->assign("BLOB_CONTENT", base64_encode($file_content));
		
		$viewer->assign("USER_DATE_FORMAT", $current_user->date_format);
		
		$viewer->view('PerformanceReport.tpl', "PortfolioInformation");
		
    }
	
	
	function GenerateReport($account_no,  Vtiger_Request $request){
		
		global $adb, $current_user;
		
		$report_start_date = DateTimeField::convertToDBFormat(str_replace("/", "-", $request->get("report_start_date")));
		
		$report_end_date = DateTimeField::convertToDBFormat(str_replace("/", "-", $request->get("report_end_date")));
		
		$viewer = $this->getViewer($request);
			
		$accounts = array($account_no);

		$portfolio_result = $adb->pquery("select * from vtiger_portfolioinformation 
		inner join vtiger_contactdetails  on contact_link = contactid 
		inner join vtiger_contactaddress on contactaddressid = contactid
		
		where account_number = ?", array($account_no));
		
		if($adb->num_rows($portfolio_result)){
			
			$viewer->assign("PREPARED_FOR", $adb->query_result($portfolio_result, 0, "firstname") . ' ' . 
			$adb->query_result($portfolio_result, 0, "lastname"));
			$viewer->assign("PREPARED_BY" , $current_user->first_name . ' ' . $current_user->last_name); 
			
			$address = $adb->query_result($portfolio_result, 0, "mailingstreet");
			$viewer->assign("CLIENT_ADDRESS", $address);
			
			$address2 = $adb->query_result($portfolio_result, 0, "mailingcity") . ', ' . $adb->query_result($portfolio_result, 0, "mailingstate") . ' ' .
			$adb->query_result($portfolio_result, 0, "mailingcountry");
			
			
			$viewer->assign("CLIENT_ADDRESS2", $address2);
			
			$viewer->assign("INCEPTION_DATE", date("m/d/Y", strtotime($adb->query_result($portfolio_result, 0, "inceptiondate")) ));
		
		}
			
			
		PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, null, null, true);
		
		if(strlen($report_end_date) > 1) {
			$end_date = date("Y-m-d",strtotime($report_end_date));
		}else {
			$end_date = DetermineIntervalEndDate($accounts, "2022-12-31");
		}
			
			
		$result = $adb->pquery("SELECT LAST_DAY(DATE_SUB(?, INTERVAL 1 MONTH)) as last_month_date", array($end_date));			
		$last_month_date = $adb->query_result($result, 0, "last_month_date");
		
		$last_month_performance = new PerformanceReport_Model($accounts, $this->DetermineIntervalStartDate($accounts, $last_month_date), $end_date);
		
		$year_to_date_performance = new PerformanceReport_Model($accounts, $this->DetermineIntervalStartDate($accounts, GetDateStartOfYear($end_date)), 
		$end_date);
		
		$result = $adb->pquery("SELECT LAST_DAY(DATE_SUB(?, INTERVAL 1 YEAR)) as last_month_date", array($end_date));			
		$last_year_date = $adb->query_result($result, 0, "last_month_date");
		
		$result = $adb->pquery("select * from vtiger_portfolioinformation where 
		account_number in (?)", array($account_no));
		$inception_date = $adb->query_result($result, 0, "inceptiondate");
		$since_inception_performance = new PerformanceReport_Model($accounts, $inception_date, $end_date);
		
		$bar_chart_data = array();
		
		$bar_chart_data[] = array(
			'category' => 'LAST MONTH',
			'PR' => $last_month_performance->get_portfolio_return(),
			'GSPC' => $this->GetIndex("GSPC", $account_no, 
			$last_month_date, $end_date), 
			'AGG' => $this->GetIndex("AGG", $account_no, 
			$last_month_date, $end_date),
		);
			
		$bar_chart_data[] = array(
			'category' => 'Year to Date',
			'PR' => $year_to_date_performance->get_portfolio_return(),
			'GSPC' => round($this->GetIndex("GSPC", $account_no, 
			GetDateStartOfYear($end_date), $end_date), 1), 
			'AGG' => round($this->GetIndex("AGG", $account_no, 
			GetDateStartOfYear($end_date), $end_date), 1) 
		);
			
			
		$date1 = date_create($end_date);
		$date2 = date_create($inception_date);
		$diff = date_diff($date1, $date2);
		$total_days = $diff->days;
		
		$bar_chart_data[] = array(
			'category' => 'Since Inception',
			'PR' => $since_inception_performance->get_portfolio_return(true, $total_days),
			'GSPC' => $this->GetIndex("GSPC", $account_no, 
			'since_inception', $end_date),
			'AGG' => $this->GetIndex("AGG", $account_no, 
			'since_inception', $end_date),
		);
			
		
		$viewer->assign("BAR_CHART_STRING", json_encode($bar_chart_data));
		$viewer->assign("PORTFOLIO_RETURNS_DATA", $bar_chart_data);
		
		
		$portfolio_result = $adb->pquery("select * from vtiger_portfolioinformation 
		inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid
		inner join vtiger_portfolioinformationcf on vtiger_portfolioinformation.portfolioinformationid = vtiger_portfolioinformationcf.portfolioinformationid
		where deleted = 0 and account_number = ?", array($account_no));
		
		$account_summary = array();
		
		for($index = 0; $index < $adb->num_rows($portfolio_result); $index++){
			
			if($adb->query_result($portfolio_result, $index, "inceptiondate") != ''){
				$inception_date = date("m/d/Y", strtotime($adb->query_result($portfolio_result, $index, "inceptiondate")));
			} else {
				$inception_date = '';
			}
			$account_summary[] = array(
				"account_number" => $adb->query_result($portfolio_result, $index, "account_number"),
				"account_type" => $adb->query_result($portfolio_result, $index, "cf_2549"),
				"account_title1" => $adb->query_result($portfolio_result, $index, "account_title1"),
				"current_value" => $last_month_performance->GetEndingValuesSummed()->value,
				"inception_date" => $inception_date,
				"last_month" => $last_month_performance->get_portfolio_return(),
				"year_to_date" => $year_to_date_performance->get_portfolio_return(),
				"since_inception" => $since_inception_performance->get_portfolio_return()
			);
		}
			
		$viewer->assign("ACCOUNT_SUMMARY" , $account_summary);
		
		$account = new CustodianAccess($account_no);
		
		$positions_data =  $account->GetPositions($end_date);
		
		$grand_total = 0;
		
		$consolidated_positions_data = array();
		
		foreach($positions_data as $p_data){
			
			if($p_data['aclass'] == 'Funds'){
				$a_class = 'Stocks';
			} else {
				if($p_data['security_sector'] != ''){
					$a_class = $p_data['security_sector'];
				} else {
					$a_class = $p_data['aclass'];
				}
			}
			
			if(isset($consolidated_positions_data[$a_class])){
				$consolidated_positions_data[$a_class] = $consolidated_positions_data[$a_class] + $p_data['market_value'];
			} else {
				$consolidated_positions_data[$a_class] = $p_data['market_value'];
			}
			
			$grand_total = $grand_total + $p_data['market_value'];
		
		}
			
		$chart_colors_result = $adb->pquery("SELECT * FROM `vtiger_chart_colors`");
		
		$chart_colors = array();
		
		for($index = 0; $index < $adb->num_rows($chart_colors_result); $index++){
			$chart_colors[$adb->query_result($chart_colors_result, $index, "title")] = $adb->query_result($chart_colors_result, $index, "color");
		}
		
		$sector_pie = array();
		
		$asset_total_percentage = 0;
		
		foreach($consolidated_positions_data as $title => $value){
			
			$asset_total_percentage = $asset_total_percentage + round(($value /$grand_total) * 100, 2);
			
			$sector_pie[] = array("value" => round($value), "title" => $title, 
			"percentage" => round(($value /$grand_total) * 100, 1), "color" => $chart_colors[$title]);
		
		}
			
		$holdings = array();
		
		foreach($positions_data as $p_data){
			
			if($p_data['aclass'] == 'Funds'){
				$a_class = 'Stocks';
			} else {
				if($p_data['security_sector'] != ''){
					$a_class = $p_data['security_sector'];
				} else {
					$a_class = $p_data['aclass'];
				}
			}
			
			$holdings[$a_class]['color'] = $chart_colors[$a_class];
			
			$holdings[$a_class]['weight'] = round(($consolidated_positions_data[$a_class] /$grand_total) * 100, 1);
			
			$holdings[$a_class]['current_value'] = round($consolidated_positions_data[$a_class], 2);
			
			$weight = round(($p_data['market_value'] / $grand_total) * 100, 1);
			
			
			if(round($p_data['quantity']) <= 0){
				$transaction_result = $adb->pquery("select quantity from vtiger_transactions 
				inner join vtiger_transactionscf on vtiger_transactions.transactionsid = vtiger_transactionscf.transactionsid
				inner join vtiger_crmentity on crmid = vtiger_transactions.transactionsid
				where deleted = 0  AND (
					transaction_type = 'Trade'
				) and security_symbol = ? and 
				( trade_date <= ?) and account_number = ? order by trade_date DESC limit 1", array($p_data['symbol'], $end_date, $account_no));
				
				if($adb->num_rows($transaction_result)){
					$p_data['quantity']	= $adb->query_result($transaction_result, 0, "quantity");
				}
			}
			
			//Find the latest income
			$transaction_result = $adb->pquery("select net_amount as total_amount from vtiger_transactions 
			inner join vtiger_transactionscf on vtiger_transactions.transactionsid = vtiger_transactionscf.transactionsid
			inner join vtiger_crmentity on crmid = vtiger_transactions.transactionsid
			where deleted = 0  AND (
				transaction_activity LIKE ('%dividend%') 
			) and security_symbol = ? and 
			( trade_date <= ?) and account_number = ? order by trade_date DESC limit 1", array($p_data['symbol'], $end_date, $account_no));
			
			if($p_data['aclass'] == 'Bonds'){
				$payment_frequency = 12;
			} else {
				$payment_frequency = 4;
			}
			
			$current_yield = 0;
			
			if($adb->num_rows($transaction_result)){
				
				$income = $adb->query_result($transaction_result, 0, "total_amount");
				
				$current_yield = ($income * $payment_frequency) / $p_data['quantity'];
				$current_yield = number_format (($current_yield / $p_data['closing_price']) * 100, 1);
			
			} else {
				
				$security_result = $adb->pquery("select dividend_share from vtiger_modsecurities 
				inner join vtiger_modsecuritiescf on vtiger_modsecuritiescf.modsecuritiesid = vtiger_modsecurities.modsecuritiesid
				where security_symbol = ?",array($p_data['symbol']), true);
				
				if($adb->num_rows($security_result)){
					$dividend = $adb->query_result($security_result, 0, "dividend_share");
					
					$current_yield = number_format (($dividend / $p_data['closing_price']) * 100, 1);
				}
			}
			
			if($p_data['symbol'] == '$CASH'){
				$p_data['quantity'] = '';
				$p_data['closing_price'] = '';
				$p_data['symbol'] = 'CASH';
			}
			
			if($p_data['security_name'] == 'Free Cash'){
				$p_data['security_name'] = 'MONEY MARKET';
			}
			
			$holdings[$a_class]['data'][] = array(
				"security_name" => $p_data['security_name'], 
				"symbol" => $p_data['symbol'], 
				"quantity" => $p_data['quantity'],
				"price" => $p_data['closing_price'], 
				"value" => $p_data['market_value'],
				"weight" => $weight,
				"current_yield" => $current_yield,
			);
		}
			
			
		$viewer->assign("HOLDINGSSECTORPIEARRAY", $sector_pie);
		
		$viewer->assign("HOLDINGSSECTORPIESTRING", json_encode($sector_pie));
		
		$viewer->assign("HOLDINGS", $holdings);
		
		$viewer->assign("ASSET_TOTAL_PERCENTAGE", round($asset_total_percentage));
		
		$viewer->assign("GRAND_TOTAL", $grand_total);
		
		$end_date = date("m/d/Y", strtotime($end_date));
		
		$viewer->assign("END_DATE", $end_date);		
		
		$viewer->assign("OVERVIEW_STYLE", 1);
		
		$viewer->assign("LAST_MONTH_PERFORMANCE", $last_month_performance);
		
		$viewer->assign("YEAR_TO_DATE_PERFORMANCE", $year_to_date_performance);
		
		$viewer->assign("SINCE_INCEPTION_PERFORMANCE", $since_inception_performance);

			
		$viewer->assign("SCRIPTS", $this->getHeaderScripts($request));
		
		$viewer->assign("ACCOUNT_NUMBER", $account_no);
		
		
		$portfolio_result = $adb->pquery("select * from vtiger_portfolioinformation
		where account_number = ?", array($account_no));
		
		
		global $site_URL;
		$coverpage = new FormattedContactInfo($adb->query_result($portfolio_result, 0, "portfolioinformationid"));
		$coverpage->SetTitle("Portfolio Review");
		$coverpage->SetLogo($site_URL . "layouts/hardcoded_images/lhimage.jpg");
		$viewer->assign("COVERPAGE", $coverpage);
		$viewer->assign("COVER_LOGO", $site_URL . "layouts/hardcoded_images/lhimage.jpg");
		
		$ispdf = $request->get('pdf');
		
		if($ispdf) {
			$viewer->assign("IS_PDF", $ispdf);
		}
		
		$screen_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/PerformanceReportContent.tpl', "PortfolioInformation");
		
		$js_content = '<script src="'.$site_URL.'layouts/v7/lib/jquery/jquery.min.js"></script>
		<script src="'.$site_URL.'libraries/amcharts4/core.js"></script>
		<script src="'.$site_URL.'libraries/amcharts4/charts.js"></script>
		<script src="'.$site_URL.'libraries/amcharts/amcharts/amcharts.js"></script>
		<script src="'.$site_URL.'libraries/amcharts/amcharts/pie.js"></script>
		<script src="'.$site_URL.'libraries/amcharts/amcharts/serial.js"></script>';
		
				
		$stylesheet  = '<link type="text/css" rel="stylesheet" href = "' . $site_URL . 'layouts/v7/lib/todc/css/bootstrap.min.css">';
		
		$screen_content = $stylesheet . $js_content . $screen_content;
		
		$fileDir = 'cache/PerformanceReport';
		
		if (!is_dir($fileDir)) {
			mkdir($fileDir);
		}
				
		$bodyFileName = $fileDir.'/PerformanceReport.html';
		
		$fb = fopen($bodyFileName, 'w');
		
		fwrite($fb, $screen_content);
		
		fclose($fb);
		
		$whtmltopdfPath = $fileDir.'/'. $account_no. '.pdf';
		
				
		 $footer ="<!doctype html>
		<html>
		
			<head>
				<meta charset='utf-8'>
				<script>
				function subst() {
						var vars = {};
						var x = document.location.search.substring(1).split('&');
						for (var i in x) {
							var z = x[i].split('=', 2);
							vars[z[0]] = unescape(z[1]);
						}
						var x = ['frompage', 'topage', 'page', 'webpage', 'section', 'subsection', 'subsubsection'];
						
						for (var i in x) {
							
							var y = document.getElementsByClassName(x[i]);
							
							for (var j = 0; j < y.length; ++j) y[j].textContent = vars[x[i]];

							if (vars['page'] == 1) {
								document.getElementById('FakeHeaders').style.display = 'none';
							}
						}
				 }
				 </script>
			</head>
			
			<body onload='subst()'>
				<div style='width:100%;'>
					<div style='width:100%; float:left;vertical-align:middle;line-height:30px;' id = 'FakeHeaders'>
						<p>
							Market values are obtained from sources believed to be reliable but are not guaranteed. No representation is made as to this review's accuracy or completeness.<br/>
							The performance data quoted represents past performance and does not guarantee future 
							results. The investment return and principal value of an investment will fluctuate thus an investor's shares, when redeemed, may be worth more or less than return data quoted herein.
						</p>
					</div>
				</div>
			</body>
		</html>";
		$footerFileName = $fileDir.'/footer_PR.html';
		$ff = fopen($footerFileName, 'w');
		$f = $footer;
		fwrite($ff, $f);
		fclose($ff);
		
		
		shell_exec("wkhtmltopdf -O landscape --javascript-delay 2000 -T 10.0 -B 25.0 -L 5.0 -R 5.0 --footer-html ".$footerFileName." --footer-font-size 10 " . $bodyFileName.' '.$whtmltopdfPath.' 2>&1');
		
		$file_contents = file_get_contents($whtmltopdfPath);
		
		unlink($whtmltopdfPath);
		
		unlink($bodyFileName);
		
		return $file_contents;
			
	}

    public function getHeaderScripts(Vtiger_Request $request) {
        
		$headerScriptInstances = parent::getHeaderScripts($request);
        
		$moduleName = $request->getModule();
        
		$jsFileNames = array(
            //"~/libraries/jquery/jquery-ui/js/jquery-ui-1.8.16.custom.min.js",
            "~/libraries/amcharts/amcharts/amcharts.js",
            "~/libraries/amcharts/amcharts/serial.js",
            "~/libraries/amcharts/amcharts/pie.js",
            //"~/libraries/jquery/acollaptable/jquery.aCollapTable.min.js",
            //"modules.$moduleName.resources.printing",
            //"modules.$moduleName.resources.jqueryIdealforms",
            //"modules.PortfolioInformation.resources.DateSelection",
            //"modules.$moduleName.resources.PerformanceReport",
        );
		
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
    }
	
	
	public function GetIndex($index, $account_number, $start_date = 'since_inception', $end_date){
        
		global $adb;
		
		if($start_date == 'since_inception'){
			
			$result = $adb->pquery("select * from vtiger_portfolioinformation where 
			account_number in (?)", array($account_number));
			
			$inception_date = $adb->query_result($result, 0, "inceptiondate");
			
			$start_date = date("Y-m-d", strtotime($inception_date . " - 1 DAY"));
		}
		
		return round($this->getReferenceReturn($index, $start_date, $end_date), 2);
	
	}
	
	
	function getReferenceReturn($symbol,$startDate,$endDate) {
		global $adb;

		$end = $start = array();
		$symbol = html_entity_decode($symbol);

		$result = $adb->pquery("SELECT to_days(date) as to_days, date AS price_date, close AS price from 
		   custodian_omniscient.custodian_prices_index where date <= ?
		   AND symbol = ? 
		   order by date DESC limit 1",array($startDate,$symbol));

		if($adb->num_rows($result) <= 0)
			return 0;

		while($v = $adb->fetchByAssoc($result))
			$start = $v;

		  $query = "SELECT to_days(date) as to_days, date AS price_date, close AS price 
					  FROM custodian_omniscient.custodian_prices_index WHERE date <= ?
					  AND symbol = ?
					  order by price_date desc limit 1";
			$end_result = $adb->pquery($query,array($endDate,$symbol));

		if($adb->num_rows($end_result) <= 0)
			return 0;

		while($v = $adb->fetchByAssoc($end_result))
			$end = $v;

		$intervalDays = $end['to_days'] - $start['to_days'];

		$guess = $end['price'] / $start['price'] - 1;

		if ($intervalDays >= 365)
			$irr = pow((1+$guess),(365/$intervalDays)) - 1;
		else
			$irr = $guess;

		return $irr * 100;
	}
	
	
	function getRecordsListFromRequest(Vtiger_Request $request) {
		$cvId = $request->get('viewname');
		$module = $request->get('module');
		if(!empty($cvId) && $cvId=="undefined"){
			$sourceModule = $request->get('sourceModule');
			$cvId = CustomView_Record_Model::getAllFilterByModule($sourceModule)->getId();
		}
		$selectedIds = $request->get('selected_ids');
		$excludedIds = $request->get('excluded_ids');

		if(!empty($selectedIds) && $selectedIds != 'all') {
			if(!empty($selectedIds) && count($selectedIds) > 0) {
				return $selectedIds;
			}
		}

		$customViewModel = CustomView_Record_Model::getInstanceById($cvId);
		if($customViewModel) {
            $searchKey = $request->get('search_key');
            $searchValue = $request->get('search_value');
            $operator = $request->get('operator');
            if(!empty($operator)) {
                $customViewModel->set('operator', $operator);
                $customViewModel->set('search_key', $searchKey);
                $customViewModel->set('search_value', $searchValue);
            }
			
			$customViewModel->set('search_params',$request->get('search_params'));
			return $customViewModel->getRecordIds($excludedIds,$module);
		}
	}
}