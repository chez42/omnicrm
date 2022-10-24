<?php
require_once("libraries/Reporting/ReportCommonFunctions.php");
require_once("libraries/Reporting/ReportPerformance.php");
require_once("libraries/Reporting/ReportHistorical.php");
require_once("libraries/reports/pdf/cMpdf7.php");
require_once("libraries/reports/new//holdings_report.php");
require_once("modules/PortfolioInformation/models/NameMapper.php");

class PortfolioInformation_GHReportActual_View extends Vtiger_Index_View{

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
        /*      ob_start();
                for ($i = 0; $i < 10; $i++)
                {
                    echo "$i\n";
                    ob_flush();
                    flush();
                    sleep(1);
                };
                exit;*/
#        echo "GH1 REPORT CURRENTLY LOADING...<br />";
#        ob_flush();
#        flush();
        global $adb;
        $query = "CALL TD_PRICING_TO_INDEX(?, ?);";
        $adb->pquery($query, array('AGG', '2019-01-01'));
        $adb->pquery($query, array('EEM', '2019-01-01'));

        $orientation = $request->get('orientation');
        $calling_module = $request->get('calling_module');
        $calling_record = $request->get('calling_record');

        $current_user = Users_Record_Model::getCurrentUserModel();

        if(strlen($request->get("account_number") > 0) || strlen($calling_module) >= 0){
            $accounts = explode(",", $request->get("account_number"));
            $accounts = array_unique($accounts);

            $map = new NameMapper();
            $map->RenamePortfoliosBasedOnLinkedContact($accounts);

            if(strlen($request->get('report_start_date')) > 1) {
                $start_date = $request->get("report_start_date");
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

            $tmp_start_date = date("Y-m-d", strtotime("first day of " . $start_date));
            $tmp_end_date = date("Y-m-d", strtotime("last day of " . $end_date));

            $start_date = date("Y-m-d", strtotime($start_date));
            $end_date = date("Y-m-d", strtotime($end_date));

//            PortfolioInformation_Module_Model::RemoveMonthlyIntervals($accounts);
//            PortfolioInformation_Module_Model::CalculateMonthlyIntervalsForAccounts($accounts);
            $dif = cTDPositions::GetBalancesVsPositionsDifference($accounts, $start_date);

            if($dif > 10) {
                cTDPortfolios::CalculateAndWriteBalances($accounts, '1900-01-01', date("Y-m-d"));
                PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, '1900-01-01', $end_date, false);
            }
            PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, $start_date, $end_date, true);

            $tmp = array();
            foreach($accounts AS $k => $v){
                if (strtolower(PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($v)) == 'td'){
                    $query = "CALL TD_REC_TRANSACTIONS(?)";
                    $adb->pquery($query, array($v), true);
                };
                if(PortfolioInformation_Module_Model::DoesAccountHaveIntervalData($v, $start_date, $end_date))
                    $tmp[] = $v;
#                else{
#                    echo "No interval data available";
#                    exit;
#                }
            }
#            if(empty($tmp)){
#                echo "No interval data available";
#                exit;
#            }
            $accounts = $tmp;

            $ytd_performance = new Performance_Model($accounts, $start_date, $end_date);//GetFirstDayLastYear(), GetLastDayLastYear());

            if (sizeof($accounts) > 0) {
                PortfolioInformation_HoldingsReport_Model::GenerateEstimateTables($accounts);
                $categories = array("estimatedtype");
                $fields = array("security_symbol", "account_number", "cusip", "description", "quantity", "last_price", "weight", "current_value");
                $totals = array("current_value", "weight");
                $estimateTable = PortfolioInformation_Reports_Model::GetTable("Holdings", "Estimator", $fields, $categories);
                $estimateTable['TableTotals'] = PortfolioInformation_Reports_Model::GetTableTotals("Estimator", $totals);
                $holdings_pie = PortfolioInformation_Reports_Model::GetPieFromTable();

                PortfolioInformation_Reports_Model::GeneratePositionsValuesTable($accounts, $end_date);
                $new_pie = PortfolioInformation_Reports_Model::GetPositionValuesPie();

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
            };

####            if(is_array($holdings_pie))//If the pie chart is going to be negative and isn't an array, prevent an error
####                $holdings_pie = PortfolioInformation_Reports_Model::AddPercentageTotalToPie($holdings_pie, $global_total);
            $unsettled_cash = PortfolioInformation_HoldingsReport_Model::GetFidelityFieldTotalAsOfDate($accounts, "unsettled_cash", $end_date);
            $margin_balance = PortfolioInformation_HoldingsReport_Model::GetFidelityFieldTotalAsOfDate($accounts, "margin_balance", $end_date);
            $net_credit_debit = PortfolioInformation_HoldingsReport_Model::GetFidelityFieldTotalAsOfDate($accounts, "net_credit_debit", $end_date);

            $options = PortfolioInformation_Module_Model::GetReportSelectionOptions("gh_report");

            $tmp = $ytd_performance->ConvertPieToBenchmark($new_pie);
            $ytd_performance->SetBenchmark($tmp['Stocks'], $tmp['Cash'], $tmp['Bonds']);

            $start_date = date("m/d/Y", strtotime($start_date));
            $end_date = date("m/d/Y", strtotime($end_date));

            $prepare_date = date("F d, Y");
            $viewer = $this->getViewer($request);

            $viewer->assign("SCRIPTS", $this->getHeaderScripts($request));
            $viewer->assign('STYLES', self::getHeaderCss($request));
            $viewer->assign("ORIENTATION", $orientation);
            $viewer->assign("YTDPERFORMANCE", $ytd_performance);
            $viewer->assign("HOLDINGSPIEVALUES", json_encode($new_pie));
            $viewer->assign("HOLDINGSPIEARRAY", $new_pie);
            $viewer->assign("GLOBALTOTAL", $global_total);
            $viewer->assign("UNSETTLED_CASH", $unsettled_cash);
            $viewer->assign("MARGIN_BALANCE", $margin_balance);
            $viewer->assign("NET_CREDIT_DEBIT", $net_credit_debit);
            $viewer->assign("SETTLED_TOTAL", $global_total+$unsettled_cash+$margin_balance+$net_credit_debit);
            $viewer->assign("CALLING_RECORD", $calling_record);
            $viewer->assign("ACCOUNT_NUMBER", $request->get("account_number"));
            $viewer->assign("HEADING", "");
            $viewer->assign("USER_DATA", $current_user->getData());
            $viewer->assign("DATE_OPTIONS", $options);
            $viewer->assign("SHOW_START_DATE", 1);
            $viewer->assign("SHOW_END_DATE", 1);
            $viewer->assign("START_DATE", $start_date);
            $viewer->assign("END_DATE", $end_date);
            $viewer->assign("PREPARE_DATE", $prepare_date);
            $viewer->assign("ACCOUNTS", $accounts);


            if($calling_record) {
                $prepared_for = PortfolioInformation_Module_Model::GetPreparedForNameByRecordID($calling_record);
                $prepared_by = PortfolioInformation_Module_Model::GetPreparedByFormattedByRecordID($calling_record);
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

            $logo = PortfolioInformation_Module_Model::GetLogo();//Set the logo
            $viewer->assign("LOGO", $logo);

            if($ispdf) {
                $personal_notes = $request->get('personal_notes');
                $moduleName = $request->getModule();

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
                    $pie_image = '<img style="display:block; width:45%; height:30%" src=data:image/jpg;base64,' . base64_encode($pie_image) . ' />';
                    $viewer->assign("PIE_IMAGE", $pie_image);
                }
//echo $pie_image;exit;
                $viewer->assign("PORTFOLIO_DATA", $portfolios);
                $viewer->assign("GLOBAL_TOTAL", $account_totals);
                $viewer->assign("PERSONAL_NOTES", $personal_notes);

                $toc = array();
                $toc[] = array("title" => "#1", "name" => "Accounts Overview");
                $toc[] = array("title" => "#2", "name" => "Portfolio Performance");
                $viewer->assign("TOC", $toc);

                /*                $logo = $current_user->getImageDetails();

                                if(isset($logo['user_logo']) && !empty($logo['user_logo'])){
                                    if(isset($logo['user_logo'][0]) && !empty($logo['user_logo'][0])){
                                        $logo = $logo['user_logo'][0];
                                        $logo = $logo['path']."_".$logo['name'];
                                    } else
                                        $logo = 0;
                                } else
                                    $logo = "";

                                if($logo == "_")
                                    $logo = "test/logo/Omniscient Logo small.png";
                                $viewer->assign("LOGO", $logo);*/

                /*                $pdf_content = $viewer->fetch('layouts/vlayout/modules/PortfolioInformation/pdf/TableOfContents.tpl', $moduleName);
                                $pdf_content .= $viewer->fetch('layouts/vlayout/modules/PortfolioInformation/pdf/GroupAccounts.tpl', $moduleName);
                                $pdf_content .= $viewer->fetch('layouts/vlayout/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);*/

                $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/GHReportNewActualPDF.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/disclaimer.tpl', $moduleName);

                $this->GeneratePDF($pdf_content, $logo, $orientation, $calling_record);
            }else {
                $screen_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/DateSelection.tpl', "PortfolioInformation");
/*                if($current_user->isAdminUser())
                    $screen_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/Administration.tpl', "PortfolioInformation");*/
                $screen_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/GHReportNewActual.tpl', "PortfolioInformation");
                echo $screen_content;
            }
        } else
            return "<div class='ReportBottom'></div>";
    }

    public function GeneratePDF($content, $logo = false, $orientation = 'LETTER', $calling_record){
        #       $pdf = new cNewPDFGenerator('c',$orientation,'8','Arial');
        $pdf = new cMpdf7(['orientation' => 'P', 'margin-top' => '200mm', 'margin-header' => '0', 'border' => '0', ]);
        if($logo)
            $pdf->logo = $logo;

        $stylesheet  = file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/GroupAccounts.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/TableOfContents.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/HoldingsSummary.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/BalancesTable.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/pdf/HoldingsCharts.css');
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/GHReportPDF.css');
#        $pdf->SetupHeader();
        $pdf->SetupFooter();
        $pdf->WritePDF($stylesheet, $content);
        $printed_date = date("mdY");
        $pdf->DownloadPDF( GetClientNameFromRecord($calling_record) . "_" . $printed_date . "_GH(Actual).pdf");
#        $pdf->DownloadView();
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
#            "modules.PortfolioInformation.resources.DynamicChart",
//            "modules.PortfolioInformation.resources.DynamicPie",
            "modules.$moduleName.resources.printing",
            "modules.$moduleName.resources.jqueryIdealforms",
//            "modules.$moduleName.resources.OmniOverview",
//            "modules.$moduleName.resources.MonthSelection",
            "modules.$moduleName.resources.GHReport",
            "modules.PortfolioInformation.resources.DateSelection",
            "modules.$moduleName.resources.Administration",
        );
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
    }

    public function getHeaderCss(Vtiger_Request $request) {
        $headerCssInstances = parent::getHeaderCss($request);
        $cssFileNames = array(
            '~/layouts/v7/modules/PortfolioInformation/css/GHReportPDF.css',
            '~/layouts/v7/modules/PortfolioInformation/css/GHReport.css',
            '~/layouts/v7/modules/PortfolioInformation/css/Administration.css',
            '~/libraries/shield/css/shield_all.min.css'
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
