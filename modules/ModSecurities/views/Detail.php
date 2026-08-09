<?php

/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */
include_once('libraries/reports/new/nCommon.php');
require_once("libraries/EODHistoricalData/EODGuzzle.php");
require_once("libraries/Reporting/ReportCommonFunctions.php");

class ModSecurities_Detail_View extends Vtiger_Detail_View {

    public function preProcess(Vtiger_Request $request) {
        $security = ModSecurities_Record_Model::getInstanceById($request->get("record"));
        $notes = "";
        if(strlen($security->get("option_root_symbol")) > 0 && trim($security->get("option_root_symbol")) != '') {
            $symbol = $security->get("option_root_symbol");
            $notes = "This security is an option.  Showing information from the root symbol";
        } else {
            $symbol = $security->get("security_symbol");
        }

        $decoded_symbol = html_entity_decode($symbol);
        $security_type = $security->get('securitytype');

        $exchange = "US";
        $apiSymbol = $decoded_symbol;
        if (strtoupper($security_type) == "INDEX") {
            $mapping = ModSecurities_ConvertCustodian_Model::GetMappedIndexSymbolAndExchange($decoded_symbol);
            $apiSymbol = $mapping['symbol'];
            $exchange = $mapping['exchange'];
        }

        $guz = new cEodGuzzle($exchange);
        $eod = json_decode($guz->getSymbolRealTimePricing($apiSymbol, $exchange));
        
		try {
            $fund = json_decode($guz->getFundamentals($apiSymbol, $exchange));
        } catch(Exception $e){}

        $date = date("Y-m-d");

        $fiveYearsAgo = date("Y-m-d", strtotime("-5 years"));
        $db = PearDatabase::getInstance();
        if (strtoupper($security_type) == "INDEX") {
            $countResult = $db->pquery("SELECT COUNT(*) AS count FROM vtiger_prices_index WHERE symbol = ? AND date >= ?", array($apiSymbol, $fiveYearsAgo));
            $priceCount = $db->query_result($countResult, 0, 'count');
        } else {
            $countResult = $db->pquery("SELECT COUNT(*) AS count FROM vtiger_prices WHERE symbol = ? AND date >= ?", array($symbol, $fiveYearsAgo));
            $priceCount = $db->query_result($countResult, 0, 'count');
        }

        if ($priceCount < 1000) {
            $start = $fiveYearsAgo;
        } else {
            $start = date("Y-m-d", strtotime("-30 days"));
        }

        if (strtoupper($security_type) == "INDEX") {
            ModSecurities_ConvertCustodian_Model::UpdateIndexEOD($symbol, $start, $date);
        } else {
            ModSecurities_ConvertCustodian_Model::UpdateSecurityPriceFromEOD($symbol, $start, $date);
        }

		$guz = new cEodGuzzle($exchange);
		
		$rawData = $guz->getFundamentals($apiSymbol, $exchange);
		
		$result = json_decode($rawData);
		
		$dividendData = json_decode($guz->getDividends($apiSymbol, $exchange, $start, $date));
		
		ModSecurities_ConvertCustodian_Model::UpdateFromEODGuzzleResult($result, $dividendData, $symbol);
		
		ModSecurities_ConvertCustodian_Model::WriteRawEODData($symbol, $rawData);
		
        $change = isset($eod->change) ? $eod->change : 0;
        $close = (isset($eod->close) && $eod->close != 0) ? $eod->close : 1;
        $percentage = $change / $close * 100;

        date_default_timezone_set('America/Los_Angeles');
        if (is_object($eod)) {
            $eod->last_update = date("F d, Y h:i:s a", $eod->timestamp);
        }

        $viewer = $this->getViewer($request);

        $viewer->assign('EOD', $eod);
        $viewer->assign("FUND", $fund);
        $viewer->assign("SECURITY_DATA", $security->getData());
        $viewer->assign("CHANGE", $change);
        $viewer->assign("PERCENTAGE", $percentage);
        $viewer->assign("NOTES", $notes);
        
		if($fund && isset($fund->General->LogoURL) && strlen($fund->General->LogoURL) > 0)
            $viewer->assign("LOGO", URI_LOGOS . $fund->General->LogoURL);
        
		$viewer->assign("EXTRA_SCRIPTS", $this->getCustomScripts($request));
        
		$viewer->assign("EXTRA_STYLES", $this->getExtraHeaderCss($request));

        return parent::preProcess($request);
    }

    function process(Vtiger_Request $request) {
        require_once("libraries/EODHistoricalData/EODGuzzle.php");
        return parent::process($request);
    }
    
    public function postProcess(\Vtiger_Request $request) {
        parent::postProcess($request);
    }

    // Injecting custom javascript resources
    public function getCustomScripts(Vtiger_Request $request) {
        $headerScriptInstances = parent::getHeaderScripts($request);

        $jsFileNames = array(
            '~/layouts/v7/modules/ModSecurities/resources/ListViewRightClickPricing',
        );

        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        return $headerScriptInstances;
    }

    public function getExtraHeaderCss(Vtiger_Request $request) {
        $headerCssInstances = parent::getHeaderCss($request);
        $cssFileNames = array(
            '~/layouts/v7/modules/ModSecurities/css/DetailViewEODLatestPrice.css',
        );
        $cssInstances = $this->checkAndConvertCssStyles($cssFileNames);
        return $cssInstances;
    }
    
    public function getHeaderScripts(Vtiger_Request $request) {
        $headerScriptInstances = parent::getHeaderScripts($request);
        $moduleName = $request->getModule();
        
        $jsFileNames = array(
            'modules.'.$moduleName.'.resources.HistoricalDataList',
        );
        
        $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
        $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
        
        return $headerScriptInstances;
    }
}