<?php

/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Contacts_Detail_View extends Accounts_Detail_View {

    function __construct() {
        parent::__construct();
    }
    

	public function showModuleDetailView(Vtiger_Request $request) {
		$recordId = $request->get('record');
		$moduleName = $request->getModule();

		// Getting model to reuse it in parent 
		if (!$this->record) {
			$this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
		}
		$recordModel = $this->record->getRecord();
		$viewer = $this->getViewer($request);
		$viewer->assign('IMAGE_DETAILS', $recordModel->getImageDetails());

		$contactModuleModel = $recordModel->getModule();
		
		$selectedPortalModulesInfo = array();
		if($recordId){
		    global $adb;
		    $selectedPortalInfo = $adb->pquery("SELECT * FROM vtiger_contact_portal_permissions WHERE crmid = ?",array($recordId));
		    if($adb->num_rows($selectedPortalInfo)){
		        $selectedPortalModulesInfo = $adb->query_result_rowdata($selectedPortalInfo);
		    }
		} 
		$viewer->assign('SELECTED_PORTAL_MODULES', $selectedPortalModulesInfo);
		
		$portfolioModel = Vtiger_Module_Model::getInstance('PortfolioInformation');
		$viewer->assign('REPORT_PERMISSION',$portfolioModel->isActive());
		
		return parent::showModuleDetailView($request);
	}
	
	public function getHeaderScripts(Vtiger_Request $request) {
	    $headerScriptInstances = parent::getHeaderScripts($request);
	    $moduleName = $request->getModule();
	    
	    $jsFileNames = array(
	        '~/libraries/jquery/bootstrapswitch/js/bootstrap-switch.min.js',
	    );
	    
	    $jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
	    $headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
	    return $headerScriptInstances;
	}
	
	public function getHeaderCss(Vtiger_Request $request) {
	    $headerCssInstances = parent::getHeaderCss($request);
	    $cssFileNames = array(
	        '~/libraries/jquery/bootstrapswitch/css/bootstrap3/bootstrap-switch.min.css',
	    );
	    $cssInstances = $this->checkAndConvertCssStyles($cssFileNames);
	    $headerCssInstances = array_merge($headerCssInstances, $cssInstances);
	    return $headerCssInstances;
	}
	
}
