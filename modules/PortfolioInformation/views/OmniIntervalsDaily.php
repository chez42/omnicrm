<?php
require_once("libraries/reports/pdf/cMpdf7.php");

class PortfolioInformation_OmniIntervalsDaily_View extends Vtiger_Index_View{

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
        if($request->get('ispdf') == 1)
            $this->PDFView($request);
        else
            $this->ScreenView($request);
    }

    function PDFView(Vtiger_Request $request){
        $current_user = Users_Record_Model::getCurrentUserModel();

        $viewer = $this->getViewer($request);
        if (strlen($request->get('line_image')) > 0) {
            $line_image = cMpdf7::TextToImage($request->get('line_image'));
            $line_image = '<img style="display:block; width:100%; height:18%" src="data:image/jpg;base64, ' . base64_encode($line_image) . '" />';
            $viewer->assign("LINE_IMAGE", $line_image);
        }

        $module = $request->get('calling_module');
        $calling_record = $request->get('calling_record');
        $account_numbers = $request->get('account_numbers');
//        if($module == "PortfolioInformation") {
        $accounts = explode(",", $account_numbers);
        $accounts = PortfolioInformation_Module_Model::ReturnValidAccountsFromArray($accounts);
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
#        PortfolioInformation_Module_Model::CalculateMonthlyIntervalsForAccounts($accounts);
#        PortfolioInformation_Module_Model::AutoDetermineIntervalCalculationDates($accounts);
//        PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, $start_date, $end_date, true);
//        $intervals = PortfolioInformation_Module_Model::GetDailyIntervalsForAccountsPreCalculated($accounts, '1900-01-01', date("Y-m-d"));
        $intervals = PortfolioInformation_Module_Model::GetDailyIntervalsForAccountsPreCalculated($accounts, $start_date, $end_date);
        foreach($accounts AS $k => $v){
            $tmp_date = PortfolioInformation_Module_Model::GetFirstIntervalEndDate($v);
            if($tmp_date < $start_date)
                $start_date = $tmp_date;
        }

        $logo = $current_user->getImageDetails();
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
        $viewer->assign("LOGO", $logo);


        $viewer->assign("CURRENT_USER", $current_user);
#        $viewer->assign("LINE_IMAGE", $line_image);
        $viewer->assign('INTERVALS', $intervals);
        $viewer->assign("ACCOUNT_NUMBERS", implode(",", $accounts));
#        $viewer->assign('SCRIPTS', self::getHeaderScripts($request));
#        $viewer->assign('STYLES', self::getHeaderCss($request));
        $viewer->assign('START_DATE', $start_date);
        $viewer->assign('END_DATE', date("Y-m-d"));
        $viewer->assign("SOURCE_RECORD", $calling_record);
        $viewer->assign("SOURCE_MODULE", $module);

        $pdf_content = $viewer->fetch('layouts/v7/modules/PortfolioInformation/pdf/IntervalViewDailyPDF.tpl', "PortfolioInformation");
        $this->GeneratePDF($pdf_content, $logo, $calling_record);
#        $viewer->view('IntervalViewDailyPDF.tpl', "PortfolioInformation");
    }

    function ScreenView(Vtiger_Request $request){
        $module = $request->get('calling_module');
        $calling_record = $request->get('calling_record');
        $account_numbers = $request->get('account_number');
//        if($module == "PortfolioInformation") {
        $accounts = explode(",", $account_numbers);
        $accounts = PortfolioInformation_Module_Model::ReturnValidAccountsFromArray($accounts);
        $start_date = date("Y-m-d");
        $selected_indexes = PortfolioInformation_Indexes_Model::GetSelectedIndexes();
#        PortfolioInformation_Module_Model::CalculateMonthlyIntervalsForAccounts($accounts);
#        PortfolioInformation_Module_Model::AutoDetermineIntervalCalculationDates($accounts);
        PortfolioInformation_Module_Model::CalculateDailyIntervalsForAccounts($accounts, null, null, false);
//        $intervals = PortfolioInformation_Module_Model::GetDailyIntervalsForAccountsPreCalculated($accounts, '1900-01-01', date("Y-m-d"));
        $intervals = PortfolioInformation_Module_Model::GetDailyIntervalsForAccountsPreCalculated($accounts, '1900-01-01', date("Y-m-d"));
        foreach($accounts AS $k => $v){
            $tmp_date = PortfolioInformation_Module_Model::GetFirstIntervalEndDate($v);
            if($tmp_date < $start_date)
                $start_date = $tmp_date;
        }
        $current_user = Users_Record_Model::getCurrentUserModel();
//print_r($selected_indexes);
        $viewer = $this->getViewer($request);
        $viewer->assign("CURRENT_USER", $current_user);
        $viewer->assign('INTERVALS', $intervals);
        $viewer->assign("SELECTED_INDEXES", $selected_indexes);
        $viewer->assign("SELECTED_INDEXES_ENCODED", json_encode($selected_indexes));
        $viewer->assign("ACCOUNT_NUMBERS", implode(",", $accounts));
        $viewer->assign('SCRIPTS', self::getHeaderScripts($request));
        $viewer->assign('STYLES', self::getHeaderCss($request));
        $viewer->assign('START_DATE', $start_date);
        $viewer->assign('END_DATE', date("Y-m-d"));
        $viewer->assign("SOURCE_RECORD", $calling_record);
        $viewer->assign("SOURCE_MODULE", $module);
        $viewer->view('IntervalViewDaily.tpl', "PortfolioInformation");
    }

    public function GeneratePDF($content, $logo = false, $calling_record){
#        $pdf = new cNewPDFGenerator('c','LETTER-L','8','Arial');
        $pdf = new cMpdf7(array('orientation' => 'L'));

        if($logo)
            $pdf->logo = $logo;

        $stylesheet = file_get_contents('layouts/v7/modules/PortfolioInformation/css/IntervalViewDaily.css');

        $pdf->SetupFooter();
        $pdf->WritePDF($stylesheet, $content);
        $printed_date = date("mdY");
        $pdf->DownloadPDF( "TESTING.pdf");//GetClientNameFromRecord($calling_record) . "_" . $printed_date . "_GH2.pdf");
    }

    public function getHeaderScripts(Vtiger_Request $request) {
        $headerScriptInstances = parent::getHeaderScripts($request);
        $moduleName = $request->getModule();
        $jsFileNames = array(
            /*            "~/libraries/amcharts4_9/core.js",
                        "~/libraries/amcharts4_9/charts.js",*/
            "~/libraries/amcharts4_9/themes/dark.js",
            "~/libraries/floathead/jquery.floatThead.min.js",
            "modules.PortfolioInformation.resources.IntervalsDaily", // . = delimiter
        );
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
    }

    public function getHeaderCss(Vtiger_Request $request) {
        $headerCssInstances = parent::getHeaderCss($request);
        $cssFileNames = array(
            "~/libraries/amcharts/amstockchart/plugins/export/export.css",
            '~/layouts/v7/modules/PortfolioInformation/css/IntervalViewDaily.css',
        );
        $cssInstances = $this->checkAndConvertCssStyles($cssFileNames);
        $headerCssInstances = array_merge($headerCssInstances, $cssInstances);

        return $headerCssInstances;
    }
}