<?php
require_once("libraries/Reporting/ReportCommonFunctions.php");
require_once("libraries/Reporting/ReportPerformance.php");
require_once("libraries/Reporting/ReportHistorical.php");
require_once("libraries/reports/pdf/cMpdf7.php");
require_once("libraries/reports/new/holdings_report.php");
require_once("libraries/Reporting/ProjectedIncomeModel.php");
require_once("modules/PortfolioInformation/models/NameMapper.php");
include_once("modules/PortfolioInformation/models/PrintingContactInfo.php");


class PortfolioInformation_GH2Report_View extends Vtiger_Index_View{

    /*    function preProcessTplName(Vtiger_Request $request) {
            return 'PortfolioReportsPerProcess.tpl';
        }*/
    
    public function postProcess(Vtiger_Request $request) {
        $moduleName = $request->getModule();
        $viewer = $this->getViewer($request);
        $viewer->view('PortfolioReportsPostProcess.tpl', $moduleName);
        
        parent::postProcess($request);
    }
    
    function process(Vtiger_Request $request) {
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        $custodianDB = $dbconfig['custodianDB'];

        $orientation = $request->get('orientation');
        $calling_module = $request->get('calling_module');
        $calling_record = $request->get('calling_record');
        $prepared_for = "";

//        if(strlen($request->get("account_number") > 0) || strlen($calling_module) >= 0){
        if(strlen($request->get("account_number") > 0) || strlen($calling_module) >= 0){
            $accounts = explode(",", $request->get("account_number"));
            $accounts = array_unique($accounts);

            $map = new NameMapper();
            $map->RenamePortfoliosBasedOnLinkedContact($accounts);

            if(strlen($request->get('report_start_date')) > 1) {
                $start_date =  $request->get("report_start_date");
            }
            else {
                $start_date = PortfolioInformation_Module_Model::ReportValueToDate("ytd", false)['start'];
            }

            if(strlen($request->get('report_end_date')) > 1) {
                $end_date = $request->get("report_end_date");
            }
            else {
                $end_date = PortfolioInformation_Module_Model::ReportValueToDate("ytd", false)['end'];
	    }
	    
 ##            $tmp_start_date = date("Y-m-d", strtotime("first day of " . $start_date));
##            $tmp_end_date = date("Y-m-d", strtotime("last day of " . $end_date));

            $start_date = date("Y-m-d", strtotime($start_date));
            $end_date = date("Y-m-d", strtotime($end_date));

//            PortfolioInformation_Module_Model::RemoveIntervals($accounts, $tmp_start_date, $tmp_end_date);
//            PortfolioInformation_Module_Model::CalculateMonthlyIntervalsForAccounts($accounts);
//            PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, $tmp_start_date, $tmp_end_date);
            $dif = cTDPositions::GetBalancesVsPositionsDifference($accounts, $start_date);
            if($dif > 10) {
                cTDPortfolios::CalculateAndWriteBalances($accounts, '1900-01-01', date("Y-m-d"));
                PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, '1900-01-01', $end_date, false);
            }
            PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, null, null, true);
//            PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, "2015-01-01", "2020-03-19");

            $tmp = array();
            foreach($accounts AS $k => $v){
                if (strtolower(PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($v)) == 'td'){
                    $query = "CALL TD_REC_TRANSACTIONS(?)";
                    $adb->pquery($query, array($v), true);
                };
                if(PortfolioInformation_Module_Model::DoesAccountHaveIntervalData($v, $start_date, $end_date))
                    $tmp[] = $v;
            }

            $accounts = $tmp;

            $ytd_performance = new Performance_Model($accounts, $start_date, $end_date);//GetFirstDayLastYear(), GetLastDayLastYear());

            if (sizeof($accounts) > 0) {
                PortfolioInformation_Reports_Model::GeneratePositionsValuesTable($accounts, $end_date);
                $new_pie = PortfolioInformation_Reports_Model::GetPositionValuesPie();
                $sector_pie = PortfolioInformation_Reports_Model::GetPositionSectorsPie();

                global $adb;
                $query = "SELECT @global_total as global_total";
                $result = $adb->pquery($query, array());
                if($adb->num_rows($result) > 0){
                    $global_total = $adb->query_result($result, 0, 'global_total');
                }
            };

            $unsettled_cash = PortfolioInformation_HoldingsReport_Model::GetFidelityFieldTotalAsOfDate($accounts, "unsettled_cash", $end_date);
            $margin_balance = PortfolioInformation_HoldingsReport_Model::GetFidelityFieldTotalAsOfDate($accounts, "margin_balance", $end_date);
            $net_credit_debit = PortfolioInformation_HoldingsReport_Model::GetFidelityFieldTotalAsOfDate($accounts, "net_credit_debit", $end_date);
            $date_options = PortfolioInformation_Module_Model::GetReportSelectionOptions("gh2_report");

            $tmp = $ytd_performance->ConvertPieToBenchmark($new_pie);
            $ytd_performance->SetBenchmark($tmp['Stocks'], $tmp['Cash'], $tmp['Bonds']);

            $viewer = $this->getViewer($request);

            $ytd_performance->CalculateIndividualTWRCumulative($start_date, $end_date);
#print_r($positions);
//            $ytd_performance->GetEstimatedIncome()->GetGrandTotal();exit;

            $start_date = date("m/d/Y", strtotime($start_date));
            $end_date = date("m/d/Y", strtotime($end_date));

            $viewer->assign("SCRIPTS", $this->getHeaderScripts($request));
            $viewer->assign('STYLES', self::getHeaderCss($request));
            $viewer->assign("ORIENTATION", $orientation);
            $viewer->assign("TODAY", date("M d, Y"));
            $viewer->assign("YTDPERFORMANCE", $ytd_performance);
            $viewer->assign("HOLDINGSPIEVALUES", json_encode($new_pie));
            $viewer->assign("HOLDINGSSECTORPIESTRING", json_encode($sector_pie));
            $viewer->assign("HOLDINGSSECTORPIEARRAY", $sector_pie);
            $viewer->assign("HOLDINGSPIEARRAY", $new_pie);
//            $viewer->assign("POSITIONS", $positions);
            $viewer->assign("GLOBALTOTAL", $global_total);
            $viewer->assign("UNSETTLED_CASH", $unsettled_cash);
            $viewer->assign("MARGIN_BALANCE", $margin_balance);
            $viewer->assign("NET_CREDIT_DEBIT", $net_credit_debit);
            $viewer->assign("UNSETTLED_CASH", $unsettled_cash);
            $viewer->assign("SETTLED_TOTAL", $global_total+$unsettled_cash+$margin_balance+$net_credit_debit);
            $viewer->assign("CALLING_RECORD", $calling_record);
            $viewer->assign("ACCOUNT_NUMBER", $request->get("account_number"));
            $viewer->assign("HEADING", "");
            $viewer->assign("DATE_OPTIONS", $date_options);
            $viewer->assign("SHOW_START_DATE", 1);
            $viewer->assign("SHOW_END_DATE", 1);
            $viewer->assign("START_DATE", $start_date);
            $viewer->assign("END_DATE", $end_date);

            if($calling_record) {
                $prepared_for = PortfolioInformation_Module_Model::GetPreparedForNameByRecordID($calling_record);
                $prepared_by = PortfolioInformation_Module_Model::GetPreparedByNameByRecordID($calling_record);
                $record = VTiger_Record_Model::getInstanceById($calling_record);
                $data = $record->getData();
                $module = $record->getModule();
                if($module->getName() == "Accounts") {
                    $policy = $data['cf_2525'];//Investment Policy Statement
                    $viewer->assign("POLICY", $policy);
                }
                $viewer->assign("PREPARED_FOR", $prepared_for);
                $viewer->assign("PREPARED_BY", $prepared_by);
            }

            $ispdf = $request->get('pdf');

            if($ispdf) {
                $personal_notes = $request->get('personal_notes');
                $moduleName = $request->getModule();
                $current_user = Users_Record_Model::getCurrentUserModel();

                $account_totals = PortfolioInformation_Module_Model::GetAccountSumTotals($accounts);
                $account_totals['global_total'] = $account_totals['total'];

                if(is_array($accounts)){
                    $portfolios = array();
                    foreach($accounts AS $k => $v) {
                        $crmid = PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($v);
                        if($crmid) {
                            $p = PortfolioInformation_Record_Model::getInstanceById($crmid);
                            $portfolios[] = $p->getData();
                        }
                    }
                }

                if (strlen($request->get('pie_image')) > 0) {
                    $pie_image = cMpdf7::TextToImage($request->get('pie_image'));
                    $pie_image = '<img style="display:block; width:27%; height:18%" src="data:image/jpg;base64, ' . base64_encode($pie_image) . '" />';
                    $viewer->assign("PIE_IMAGE", $pie_image);
                }

                if (strlen($request->get('sector_pie_image')) > 0) {
                    $sector_pie_image = cMpdf7::TextToImage($request->get('sector_pie_image'));
                    $sector_pie_image = '<img style="width:100%;" src="data:image/jpg;base64, ' . base64_encode($sector_pie_image) . '" />';
                    $viewer->assign("SECTOR_PIE_IMAGE", $sector_pie_image);
                }

                $viewer->assign("PORTFOLIO_DATA", $portfolios);
                $viewer->assign("GLOBAL_TOTAL", $account_totals);
                $viewer->assign("PERSONAL_NOTES", $personal_notes);

                $toc = array();
                $toc[] = array("title" => "#1", "name" => "Accounts Overview");
                $toc[] = array("title" => "#2", "name" => "Portfolio Performance");
                $viewer->assign("TOC", $toc);

                $logo = PortfolioInformation_Module_Model::GetLogo();//Set the logo
                $viewer->assign("LOGO", $logo);

                $coverpage = new FormattedContactInfo($calling_record);
                $coverpage->SetTitle("Portfolio Review");
                $coverpage->SetLogo("layouts/hardcoded_images/lhimage.jpg");
#        $output = $coverpage->GetFormattedLogo();
#                $viewer = new Vtiger_Viewer();
                $viewer->assign("COVERPAGE", $coverpage);
#                $output = $viewer->view('Reports/LighthouseCover.tpl', 'PortfolioInformation', true);

#                $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/lighthouse.tpl', $moduleName);
                $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/Reports/LighthouseCover.tpl', $moduleName);
#                $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/Reports/CoverPage.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/GH2ReportPDF.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/AllocationTypesPDF.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/disclaimer_landscape.tpl', $moduleName);
                $this->GeneratePDF($pdf_content, $logo, $calling_record);
            }else {
                $screen_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/DateSelection.tpl', "PortfolioInformation");
                $screen_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/GH2Report.tpl', "PortfolioInformation");
                $screen_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/AllocationTypes.tpl', "PortfolioInformation");
                echo $screen_content;
            }
        } else
            return "<div class='ReportBottom'></div>";
    }

    public function GeneratePDF($content, $logo = false, $calling_record){
#        $pdf = new cNewPDFGenerator('c','LETTER-L','8','Arial');
        $pdf = new cMpdf7(array('orientation' => 'L'));

        if($logo)
            $pdf->logo = $logo;

        $stylesheet  = file_get_contents('layouts/vlayout/modules/PortfolioInformation/css/pdf/GroupAccounts.css');
        $stylesheet .= file_get_contents('layouts/vlayout/modules/PortfolioInformation/css/pdf/TableOfContents.css');
        $stylesheet .= file_get_contents('layouts/vlayout/modules/PortfolioInformation/css/pdf/HoldingsSummary.css');
        $stylesheet .= file_get_contents('layouts/vlayout/modules/PortfolioInformation/css/pdf/BalancesTable.css');
        $stylesheet .= file_get_contents('layouts/vlayout/modules/PortfolioInformation/css/pdf/HoldingsCharts.css');

        $pdf->SetupFooter();
        $pdf->WritePDF($stylesheet, $content);
        $printed_date = date("mdY");
        $pdf->DownloadPDF( GetClientNameFromRecord($calling_record) . "_" . $printed_date . "_GH2.pdf");
    }

    public function getHeaderScripts(Vtiger_Request $request) {
        $headerScriptInstances = parent::getHeaderScripts($request);
        $moduleName = $request->getModule();
        $jsFileNames = array(
            "~/libraries/jquery/jquery-ui/js/jquery-ui-1.8.16.custom.min.js",
            "~/libraries/amcharts/amcharts/amcharts.js",
            "~/libraries/amcharts/amcharts/serial.js",
            "~/libraries/amcharts/amcharts/pie.js",
            "~/libraries/amcharts/amcharts/plugins/export/export.js",
#            "~/libraries/amcharts/2.0.5/amcharts/javascript/raphael.js",
            "~/libraries/jquery/acollaptable/jquery.aCollapTable.min.js",
//            "~/libraries/shield/shieldui-all.min.js",
            "modules.PortfolioInformation.resources.DynamicChart",
            "modules.PortfolioInformation.resources.DynamicPie",
            "modules.$moduleName.resources.printing",
            "modules.$moduleName.resources.jqueryIdealforms",
#            "modules.$moduleName.resources.OmniOverview",
            "modules.$moduleName.resources.GH2Report",
//            "modules.$moduleName.resources.MonthSelection",
            "modules.PortfolioInformation.resources.DateSelection",
        );
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
    }

    public function getHeaderCss(Vtiger_Request $request) {
        $headerCssInstances = parent::getHeaderCss($request);
        $cssFileNames = array(
            '~/libraries/shield/css/shield_all_no_footer.min.css'
        );
        $cssInstances = $this->checkAndConvertCssStyles($cssFileNames);
        $headerCssInstances = array_merge($headerCssInstances, $cssInstances);
        return $headerCssInstances;
    }


    private function GenerateTableCategories($merged_transaction_types){
        $table = array();
        foreach($merged_transaction_types AS $k => $v){
#                print_r($v);
            $vals = array_unique($v);
            $table[$k] = $vals;
        }
        return $table;
    }
}
