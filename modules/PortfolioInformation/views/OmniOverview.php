<?php
require_once("libraries/Reporting/ReportCommonFunctions.php");
require_once("libraries/Reporting/ReportPerformance.php");
require_once("libraries/Reporting/ReportHistorical.php");
require_once("libraries/reports/pdf/cMpdf7.php");
require_once("libraries/reports/new/holdings_report.php");
require_once("modules/PortfolioInformation/models/NameMapper.php");

class PortfolioInformation_OmniOverview_View extends Vtiger_Index_View{

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
        $calling_module = $request->get('calling_module');
        $calling_record = $request->get('calling_record');
        if(strlen($request->get("account_number") > 0) || strlen($calling_module) >= 0){
            $options = PortfolioInformation_Module_Model::GetReportSelectionOptions("gh_report");

            $accounts = explode(",", $request->get("account_number"));
            $accounts = array_unique($accounts);

            $map = new NameMapper();
            $map->RenamePortfoliosBasedOnLinkedContact($accounts);

//            $start = date('Y-m-d', strtotime('-7 days'));//No longer used, originally it was just for calculating intervals
            $end = date('Y-m-d');
#####            PortfolioInformation_Module_Model::CalculateMonthlyIntervalsForAccounts($accounts);
            PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, null, null, true);//Auto determine which intervals need calculated

/*            $t3_performance = new Performance_Model($accounts, GetDateMinusMonths(TRAILING_3), date("Y-m-d"));
            $t6_performance = new Performance_Model($accounts, GetDateStartOfYear(), date("Y-m-d"));
            $t12_performance = new Performance_Model($accounts, GetDateMinusMonths(TRAILING_12), date("Y-m-d"));*/
            if(strlen($request->get('report_end_date')) > 1) {
                $end_date = date("Y-m-d",strtotime($request->get("report_end_date")));
            }else {
                $end_date = DetermineIntervalEndDate($accounts, "2022-12-31");//date('Y-m-d'));
            }

            $t3_performance = new Performance_Model($accounts, DetermineIntervalStartDate($accounts, GetDateMinusMonths(TRAILING_3, $end_date)), $end_date);
            $t6_performance = new Performance_Model($accounts, DetermineIntervalStartDate($accounts, GetDateStartOfYear($end_date)), $end_date);
            $t12_performance = new Performance_Model($accounts, DetermineIntervalStartDate($accounts, GetDateMinusMonths(TRAILING_12, $end_date)), $end_date);
#            $t12_performance = new Performance_Model($accounts, "2016-04-13", "2017-07-02");
            $historical = new Historical_Model($accounts);
            $last_month = date('Y-m-d', strtotime('last day of previous month'));
            $last_year = date('Y-m-d', strtotime("{$last_month} - 1 year"));
//            $t12_balances = $historical->GetEndValuesWithoutDay($last_year, $end_date);
            $t12_balances = $historical->GetEndValues($last_year, $end_date);
            //Get a list of sub categories we can use to combine all the performance types into a table`

            $performance_summary_table['t3'] = $t3_performance->GetPerformanceSummed();
            $performance_summary_table['t6'] = $t6_performance->GetPerformanceSummed();
            $performance_summary_table['t12'] = $t12_performance->GetPerformanceSummed();

#            $holdings_pie = cHoldingsReport::CreatePieFromAssetClassGrouped($accounts);

            $tmp = array_merge_recursive($t3_performance->GetTransactionTypes(), $t6_performance->GetTransactionTypes(), $t12_performance->GetTransactionTypes());
            $table = $this->GenerateTableCategories($tmp);

            $tmp_end_date = date("Y-m-d", strtotime($end_date));
            if (sizeof($accounts) > 0) {
/*                PortfolioInformation_HoldingsReport_Model::GenerateEstimateTables($accounts);
                $categories = array("estimatedtype");
                $fields = array("security_symbol", "account_number", "cusip", "description", "quantity", "last_price", "weight", "current_value");
                $totals = array("current_value", "weight");
                $estimateTable = PortfolioInformation_Reports_Model::GetTable("Holdings", "Estimator", $fields, $categories);
                $estimateTable['TableTotals'] = PortfolioInformation_Reports_Model::GetTableTotals("Estimator", $totals);
                $holdings_pie = PortfolioInformation_Reports_Model::GetPieFromTable();
#            print_r($estimateTable['table_categories']);
#            echo "<br /><br />";
                $category_totals = PortfolioInformation_Reports_Model::GetTableCategoryTotals("Estimator", $categories, $totals);
                PortfolioInformation_reports_model::MergeTotalsIntoCategoryRows($categories, $estimateTable, $category_totals);

                global $adb;
                $query = "SELECT @global_total as global_total";
                $result = $adb->pquery($query, array());
                if($adb->num_rows($result) > 0){
                    $global_total = $adb->query_result($result, 0, 'global_total');
                }
*/
                $unsettled_cash = PortfolioInformation_HoldingsReport_Model::GetUnsettledCashTotal($accounts);
                $margin_balance = PortfolioInformation_HoldingsReport_Model::GetMarginBalanceTotal($accounts);
                $net_credit_debit = PortfolioInformation_HoldingsReport_Model::GetNetCreditDebitTotal($accounts);

                PortfolioInformation_Reports_Model::GeneratePositionsValuesTable($accounts, $tmp_end_date);
                $categories = array("aclass");
                $fields = array("symbol", "security_type", "account_number", "cusip", "description", "quantity", "price", "market_value");//, "weight", "current_value");
                $totals = array("market_value");
                $estimateTable = PortfolioInformation_Reports_Model::GetTable("Holdings", "PositionValues", $fields, $categories);
                $holdings_pie = PortfolioInformation_Reports_Model::GetPieFromTable("PositionValuesPie");
                $estimateTable['TableTotals'] = PortfolioInformation_Reports_Model::GetTableTotals("PositionValues", $totals);

                $category_totals = PortfolioInformation_Reports_Model::GetTableCategoryTotals("PositionValues", $categories, $totals);
                PortfolioInformation_reports_model::MergeTotalsIntoCategoryRows($categories, $estimateTable, $category_totals);

                global $adb;
                $query = "SELECT @global_total as global_total";
                $result = $adb->pquery($query, array());
                if($adb->num_rows($result) > 0) {
                    $global_total = $adb->query_result($result, 0, 'global_total');
                }


            };

            $end_date = date("m/d/Y", strtotime($end_date));

            $viewer = $this->getViewer($request);

            $viewer->assign("UNSETTLED_CASH", $unsettled_cash);
            $viewer->assign("MARGIN_BALANCE", $margin_balance);
            $viewer->assign("NET_CREDIT_DEBIT", $net_credit_debit);
            $viewer->assign("SETTLED_TOTAL", $global_total+$unsettled_cash+$margin_balance+$net_credit_debit);
            $viewer->assign("DATE_OPTIONS", $options);
            $viewer->assign("OVERVIEW_STYLE", 1);
            $viewer->assign("ESTIMATE_TABLE", $estimateTable);
            $viewer->assign("T3PERFORMANCE", $t3_performance);
            $viewer->assign("T6PERFORMANCE", $t6_performance);
            $viewer->assign("T12PERFORMANCE", $t12_performance);
            $viewer->assign("TABLECATEGORIES", $table);
            $viewer->assign("HOLDINGSPIEVALUES", json_encode($holdings_pie));
            $viewer->assign("END_DATE", $end_date);
            $viewer->assign("T12BALANCES", json_encode($t12_balances));
            $viewer->assign("SCRIPTS", $this->getHeaderScripts($request));
            $viewer->assign("ACCOUNT_NUMBER", $request->get("account_number"));
            $viewer->assign("CALLING_RECORD", $calling_record);

            $ispdf = $request->get('pdf');

            if($ispdf) {
                if (strlen($request->get('pie_image')) > 0) {
                    $pie_image = cMpdf7::TextToImage($request->get('pie_image'));
                    $pie_image = '<img src="data:image/jpg;base64,' . base64_encode($pie_image) . '" />';
                    $viewer->assign("PIE_IMAGE", $pie_image);
                }
                if (strlen($request->get('graph_image')) > 0) {
                    $graph_image = cMpdf7::TextToImage($request->get('graph_image'));
                    $graph_image = '<img src="data:image/jpg;base64,' . base64_encode($graph_image) . '" />';
                    $viewer->assign("GRAPH_IMAGE", $graph_image);
                }

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
                $viewer->assign("PORTFOLIO_DATA", $portfolios);
                $viewer->assign("GLOBAL_TOTAL", $account_totals);

                $toc = array();
                $toc[] = array("title" => "#1", "name" => "Accounts Overview");
                $toc[] = array("title" => "#2", "name" => "Overview Performance");
                $toc[] = array("title" => "#3", "name" => "Individual Performance");
                $toc[] = array("title" => "#3", "name" => "Account Holdings");
                $viewer->assign("TOC", $toc);

                $logo = PortfolioInformation_Module_Model::GetLogo();//Set the logo
                $viewer->assign("LOGO", $logo);

                $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/TableOfContents.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/GroupAccounts.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/OmniOverviewPDF.tpl', $moduleName);
                $pdf_content .= '<div class="graph_image" style="width:240mm; height:80mm; display:block; margin-left:auto; margin-right:auto; margin-top:10mm;">
    ' . $graph_image . '
</div>';
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/IndividualPerformance.tpl', $moduleName);
$pdf_content .= '<div class="pie_image" style="width:120mm; height:80mm; display:block; margin-left:auto; margin-right:auto;">
<br />
    ' . $pie_image . '
</div>';
//                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/DynamicHoldings.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/disclaimer_landscape.tpl', $moduleName);
                $this->GeneratePDF($pdf_content, $logo, $calling_record);
            }else {
#                $viewer->view('OmniOverview.tpl', "PortfolioInformation");
                $screen_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/DateSelection.tpl', "PortfolioInformation");
                $screen_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/OmniOverview.tpl', "PortfolioInformation");
                $screen_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/IndividualPerformance.tpl', "PortfolioInformation");
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

        $stylesheet  = file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/GroupAccounts.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/TableOfContents.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/HoldingsSummary.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/BalancesTable.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/HoldingsCharts.css');

        $pdf->SetupFooter();
        $pdf->WritePDF($stylesheet, $content);
        $printed_date = date("mdY");

        $pdf->DownloadPDF( GetClientNameFromRecord($calling_record) . "_" . $printed_date . "_Overview.pdf");

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
#            "modules.PortfolioInformation.resources.DynamicChart",
#            "modules.PortfolioInformation.resources.DynamicPie",
            "modules.$moduleName.resources.printing",
            "modules.$moduleName.resources.jqueryIdealforms",
            "modules.PortfolioInformation.resources.DateSelection",
            "modules.$moduleName.resources.OmniOverview",
        );
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
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