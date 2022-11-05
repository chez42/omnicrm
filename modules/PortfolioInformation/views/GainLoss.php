<?php
/**
 * Created by PhpStorm.
 * User: ryansandnes
 * Date: 2018-08-29
 * Time: 5:09 PM
 */
set_time_limit(180);
//ini_set('display_errors','on'); version_compare(PHP_VERSION, '5.5.0') <= 0 ? error_reporting(E_WARNING & ~E_NOTICE & ~E_DEPRECATED) : error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);   // DEBUGGING
require_once("libraries/Reporting/ReportCommonFunctions.php");
#include_once("libraries/reports/pdf/cNewPDFGenerator.php");
require_once("libraries/reports/pdf/cMpdf7.php");
include_once("include/utils/omniscientCustom.php");
include_once("modules/PortfolioInformation/models/PrintingContactInfo.php");

class PortfolioInformation_GainLoss_View extends Vtiger_Index_View{

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
        global $adb;
        $is_pdf = $request->get('pdf');

        $calling_module = $request->get('calling_module');
        $calling_record = $request->get('calling_record');
        if(strlen($request->get("account_number")) > 0){
            $accounts = explode(",", $request->get("account_number"));
            $accounts = array_unique($accounts);

            foreach($accounts AS $k => $v){
                PortfolioInformation_Module_Model::AutoGenerateTransactionsForGainLossReport($v);
            }
            PortfolioInformation_GainLoss_Model::CreateGainLossTables($accounts);//Create combined gain loss table

            $categories = array("security_symbol");
            $fields = array('account_number', 'description', 'trade_date', 'security_price', 'transaction_activity', 'quantity', 'position_current_value', 'net_amount', 'ugl', 'ugl_percent', 'days_held', 'system_generated', 'transactionsid');//, "weight", "current_value");
            $totals = array("quantity", "net_amount", "position_current_value", "ugl");//Totals needs to have the same names as the fields to show up properly!!!
            $hidden_row_fields = array("description");//We don't want description showing on every row, just the category row
            $comparison_table = PortfolioInformation_Reports_Model::GetTable("Positions", "TEMPORARY_TRANSACTIONS", $fields, $categories, $hidden_row_fields);

            $comparison_table['TableTotals'] = PortfolioInformation_Reports_Model::GetTableTotals("COMPARISON", $totals);

            $add_on_fields = array("description", "ugl", "ugl_percent");
            $category_totals = PortfolioInformation_Reports_Model::GetTableCategoryTotals("COMPARISON", $categories, $totals, $add_on_fields);

            PortfolioInformation_reports_model::MergeTotalsIntoCategoryRows($categories, $comparison_table, $category_totals);

            $viewer = $this->getViewer($request);
#print_r($comparison_table);exit;
#            $viewer->assign("CATEGORY_TOTALS", $category_totals);
            $viewer->assign("COMPARISON_TABLE", $comparison_table);
#            $viewer->assign("TABLECATEGORIES", $table);
#            $viewer->assign("STYLES", $this->getHeaderCss($request));
            $viewer->assign("SCRIPTS", $this->getHeaderScripts($request));
            $viewer->assign("ACCOUNT_NUMBER", $request->get("account_number"));
            $viewer->assign("CALLING_RECORD", $calling_record);

            $current_user = Users_Record_Model::getCurrentUserModel();

            $logo = PortfolioInformation_Module_Model::GetLogo();//Set the logo
            $viewer->assign("LOGO", $logo);


            /* === END : Changes For Report Logo 2016-12-07 === */

            if($is_pdf) {
                $coverpage = new FormattedContactInfo($calling_record);
                $coverpage->SetTitle("Gain/Loss");
                $coverpage->SetLogo("layouts/hardcoded_images/lhimage.jpg");
                $viewer->assign("COVERPAGE", $coverpage);

                $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/Reports/CoverPage.tpl',"PortfolioInformation");
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', "PortfolioInformation");
#                $pdf_content  = $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/MailingInfo.tpl', $moduleName);
#                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/TitlePage.tpl', $moduleName);
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/GainLoss.tpl', "PortfolioInformation");
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/page_break.tpl', "PortfolioInformation");
                $pdf_content .= $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/disclaimer.tpl', "PortfolioInformation");

                $this->GeneratePDF($pdf_content, $logo, $calling_record);
            }
            else {
                $screen_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/GainLoss.tpl', "PortfolioInformation");
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
        $stylesheet .= file_get_contents('layouts/v7/modules/PortfolioInformation/css/GainLoss.css');

        $pdf->SetupFooter();
        $pdf->WritePDF($stylesheet, $content);
        $printed_date = date("mdY");
        $pdf->DownloadPDF( GetClientNameFromRecord($calling_record) . "_" . $printed_date . "_GainLoss.pdf");

    }

    public function getHeaderScripts(Vtiger_Request $request) {
        $headerScriptInstances = parent::getHeaderScripts($request);
        $moduleName = $request->getModule();
        $jsFileNames = array(
            "~/libraries/jquery/jquery-ui/js/jquery-ui-1.8.16.custom.min.js",
#            "~/libraries/amcharts/2.0.5/amcharts/javascript/raphael.js",
            "~/libraries/jquery/acollaptable/jquery.aCollapTable.min.js",
            "~/libraries/magnificPopup/magnificPopup.js",
            "~/layouts/v7/modules/PortfolioInformation/resources/GainLoss.js",
        );
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
    }

    public function getHeaderCss(Vtiger_Request $request) {
        $headerCssInstances = parent::getHeaderCss($request);
        $cssFileNames = array(
            "~/libraries/amcharts/amcharts/plugins/export/export.css",
            "~/libraries/magnificPopup/css/magnificPopup.css",
            "~/layouts/v7/modules/PortfolioInformation/css/GainLoss.css",

//          "~/libraries/amcharts/amcharts_3.20.9/amcharts/plugins/export/export.css",
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
