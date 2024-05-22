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

class PortfolioInformation_Detail_View extends Vtiger_Detail_View {

    public function preProcess(Vtiger_Request $request) {
        
		$portfolio = PortfolioInformation_Record_Model::getInstanceById($request->get('record'));
        
		$account_number = array($portfolio->get('account_number'));

		if(
			$portfolio->get("origination") != 'MANUAL' && 
			$portfolio->get("origination") != 'Millenium' && 
			$portfolio->get("origination") != 'EQUITY'
		){
			/*$integrity = new cIntegrity($account_number);
			$differences = $integrity->GetDifferences();

			foreach($differences AS $k => $v) {
				if (!empty($differences) && abs($v['dif']) > 10)
					$integrity->RepairDifferences();
			}*/
			$tmp = new CustodianClassMapping($account_number);
			$tmp->portfolios::UpdateAllPortfoliosForAccounts($account_number);
			$tmp->positions::CreateNewPositionsForAccounts($account_number);
			$tmp->positions::UpdateAllCRMPositionsAtOnceForAccounts($account_number);
			$tmp->transactions::CreateNewTransactionsForAccounts($account_number);
			if(PortfolioInformation_Module_Model::getInstanceSetting("update_transactions", 1) == 1)
				$tmp->transactions::UpdateTransactionsForAccounts($account_number);

			$weight = new cWeight($portfolio->get('account_number'));
			$weight->UpdatePortfolioWeight();
			$weight->UpdateContactWeightAndValue();
			$weight->UpdateHouseholdWeightAndValue();
		}
		
        return parent::preProcess($request);
    }

    public function process(Vtiger_Request $request){
        $crmid = $request->get('record');
        $record = Vtiger_Record_Model::getInstanceById($crmid, 'PortfolioInformation');
        $custodian = $record->get("origination");
        $date = date("Y-m-d", strtotime("today -1 Weekday"));
        $date_difference = 0;

        $viewer = $this->getViewer($request);

        $clist = array("fidelity", "schwab", "td", "pershing");
        if(in_array(strtolower($custodian), $clist)) {
            if ($custodian) {
                if (PositionInformation_ConvertCustodian_Model::IsTherePositionDataForDate($custodian, $date) == 0)
                    $date = date("Y-m-d", strtotime("today - 2 Weekday"));

                $integrity = PortfolioInformation_ConvertCustodian_Model::IntegrityCheck($custodian, $date, $record->get('account_number'));
                $last_date = PortfolioInformation_ConvertCustodian_Model::GetLastPositionDateForAccounts($custodian, $record->get('account_number'));
                if ($last_date) {
                    $date_difference = PortfolioInformation_ConvertCustodian_Model::GetDateDifference($last_date);
                    $last_date = date("m/d", strtotime($last_date . "+1 Weekday"));
                    $viewer->assign('INTEGRITY', $integrity);
                    $viewer->assign('LAST_DATE', $last_date);
                    $viewer->assign('DATE_DIFFERENCE', $date_difference);
                }else{
                    $viewer->assign('LAST_DATE', "Unknown");
                }

            } else {
                $viewer->assign('LAST_DATE', "Unknown");
            }
        }else{
            $viewer->assign('LAST_DATE', 'Unknown');
        }
        return parent::process($request);
    }

    /**
     * Function to get activities
     * @param Vtiger_Request $request
     * @return <List of activity models>
     */
    public function getActivities(Vtiger_Request $request) {
        $moduleName = 'Calendar';
        $moduleModel = Vtiger_Module_Model::getInstance($moduleName);

        $currentUserPriviligesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
        if($currentUserPriviligesModel->hasModulePermission($moduleModel->getId())) {
            $moduleName = $request->getModule();
            $recordId = $request->get('record');

            $pageNumber = $request->get('page');
            if(empty ($pageNumber)) {
                $pageNumber = 1;
            }
            $pagingModel = new Vtiger_Paging_Model();
            $pagingModel->set('page', $pageNumber);
            $pagingModel->set('limit', 10);

            if(!$this->record) {
                $this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
            }
            $recordModel = $this->record->getRecord();
            $moduleModel = $recordModel->getModule();

            $relatedActivities = $moduleModel->getCalendarActivities('', $pagingModel, 'all', $recordId);

            $viewer = $this->getViewer($request);
            $viewer->assign('RECORD', $recordModel);
            $viewer->assign('MODULE_NAME', $moduleName);
            $viewer->assign('PAGING_MODEL', $pagingModel);
            $viewer->assign('PAGE_NUMBER', $pageNumber);
            $viewer->assign('ACTIVITIES', $relatedActivities);

            return $viewer->view('RelatedActivities.tpl', $moduleName, true);
        }
    }
}
