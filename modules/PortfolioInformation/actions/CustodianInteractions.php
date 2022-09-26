<?php
include_once("libraries/custodians/cCustodian.php");

class PortfolioInformation_CustodianInteractions_Action extends Vtiger_BasicAjax_Action{
	public function process(Vtiger_Request $request) {
		switch($request->get('todo')){
            case "RecalculateAllHistoricalBalances":
                $rep_codes = PortfolioInformation_Module_Model::GetRepCodeListFromUsersTable();
                //2012 set for no particular reason, 'true' finds the earliest date as is
#                PortfolioInformation_Module_Model::TDBalanceCalculationsRepCodes($rep_codes, '2012-01-01', date("Y-m-d"), true);
                PortfolioInformation_Module_Model::TDBalanceCalculationsRepCodes($rep_codes, '2012-01-01', date("Y-m-d"), true);
                break;
            case "RecalculateXBalances":
                $rep_codes = PortfolioInformation_Module_Model::GetRepCodeListFromUsersTable();
                $days = $request->get('days');
                $start = date("Y-m-d", strtotime('-' . $days . ' days'));
                $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersFromRepCode($rep_codes);
                StatusUpdate::UpdateMessage("TDBALANCEUPDATE", "Calculating Balances");
                    cTDPortfolios::CalculateAndWriteBalances($account_numbers, $start, date('Y-m-d'));
                StatusUpdate::UpdateMessage("TDBALANCEUPDATE", "Finished");
//                PortfolioInformation_Module_Model::TDBalanceCalculationsRepCodes($rep_codes, $start, date("Y-m-d"), false);
                break;
            case "ParseData":
                $parseID = $request->get('parseID');
                $parse_type = $request->get("parse_type");
                $num_days = $request->get('num_days');
                $locations = new cFileHandling();
                $data = $locations->GetLocationDataFromID($parseID);

                $parse = new FileParsing($data->custodian, $parse_type, $num_days, 0, $data->rep_code);
                $parse->parseFiles();
#                StatusUpdate::UpdateMessage("MANUALPARSING", "finished");
                break;
            case 'RecalculateHomepageWidgets':
                $consolidateDays = $request->get('consolidateDays');
                if(is_nan($consolidateDays) || $consolidateDays == 0)
                    $consolidateDays = 10;
                include("cron/modules/InstanceOnly/HomepageWidgets.service");
                echo 'Finished Running Homepage Widgets and consolidating back ' . $consolidateDays . ' days';
                break;
            case "PullRecalculate":{
                include("cron/modules/Custodian/DataPull.service");
/*
                $cust = $request->get('custodian');
                switch(strtoupper($cust)){
                    case "ALL":
                        $custodians = array("TD", "Fidelity", "Schwab", "Pershing");
                        break;
                    default:
                        $custodians = array($cust);
                }
                foreach($custodians AS $k => $custodian){
                    $updateClass = "c".$custodian."Updater";
                    $update = new $updateClass(array());
                    $update->UpdateAll();
                }*/
            }break;
            case "GetUpdateStatus":{
                echo StatusUpdate::ReadMessage("TDUPDATER");
            }break;
			case "AuditTDPositions": {
/*				global $adb;
				$query = "TRUNCATE TABLE vtiger_audit_results";//DELETE FROM vtiger_audit_results WHERE account_number = ?";
				$adb->pquery($query, array());

				$directory = "/mnt/lanserver2n/Fidelity/CONCERT Wealth Central/";
				$file = "fi121515.pos";

				$position_files = glob("custodian/TD/*.POS");
				foreach($position_files AS $k => $v) {
					PortfolioInformation_CustodianInteractions_Model::AuditTDPositions($v);
				}
				PortfolioInformation_CustodianInteractions_Model::UpdateSecurityTypeIDs();
				echo "TD Copied";*/
				$trade = new Trading_Ameritrade_Model();
				$tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api");
				$accounts = array();
				foreach($tmp['model']['getAccountsJson']['account'] AS $k => $v){
					$accounts[] = $v['accountNumber'];
				}

				$info = $trade->GetBalances("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", $accounts);
				PortfolioInformation_CustodianInteractions_Model::RemoveCustodianPortfolios("TD");
				PortfolioInformation_CustodianInteractions_Model::AuditTDPortfolios($info);
/*				foreach($info['model']['getBalancesJson']['balance'] AS $k => $v){
					print_r($v);exit;
				}
*/
//				$tmp = $trade->GetUsers("https://veoapi.advisorservices.com/InstitutionalAPIv2/api");
//				$tmp = $trade->GetQuote("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", "SIRI");
//				print_r($tmp);exit;
			}break;
			case "AuditFidelityPositions": {
				global $adb;
				$query = "TRUNCATE TABLE vtiger_audit_results";//DELETE FROM vtiger_audit_results WHERE account_number = ?";
				$adb->pquery($query, array());

//				$directory = "/mnt/lanserver2n/Fidelity/CONCERT Wealth Central/";
//				$file = "fi121515.pos";
//				$position_files = glob($directory . $file);//"custodian/Fidelity/*.pos");
				$position_files = array();
				$portfolio_files = array();

				PortfolioInformation_CustodianInteractions_Model::LatestFidelityFiles($position_files, $portfolio_files);
				foreach($position_files AS $k => $v)
				{
					PortfolioInformation_CustodianInteractions_Model::AuditFidelityPositions($v);
				}

				PortfolioInformation_CustodianInteractions_Model::UpdateSecurityTypeIDs();

				PortfolioInformation_CustodianInteractions_Model::RemoveCustodianPortfolios("Fidelity");
				foreach($portfolio_files AS $k => $v) {
					PortfolioInformation_CustodianInteractions_Model::AuditFidelityPortfolios($v);
				}

				echo "Fidelity Copied";
			}break;
			case "AuditSchwabPositions":{
				global $adb;
				$query = "TRUNCATE TABLE vtiger_audit_results";//DELETE FROM vtiger_audit_results WHERE account_number = ?";
				$adb->pquery($query, array());
				$position_files = array();
				$portfolio_files = array();
				PortfolioInformation_CustodianInteractions_Model::LatestSchwabFiles($position_files, $portfolio_files);
				foreach($position_files AS $k => $v) {
					PortfolioInformation_CustodianInteractions_Model::AuditSchwabPositions($v);
				}
				PortfolioInformation_CustodianInteractions_Model::UpdateSecurityTypeIDs();

				PortfolioInformation_CustodianInteractions_Model::RemoveCustodianPortfolios("Schwab");
				foreach($portfolio_files AS $k => $v) {
					PortfolioInformation_CustodianInteractions_Model::AuditSchwabPortfolios($v);
				}
				echo "Schwab Copied";
			}break;
			case "AuditPershingPositions":{
				global $adb;
				$query = "TRUNCATE TABLE vtiger_audit_results";//DELETE FROM vtiger_audit_results WHERE account_number = ?";
				$adb->pquery($query, array());
				$position_files = array();
				$portfolio_files = array();
				PortfolioInformation_CustodianInteractions_Model::LatestPershingFiles($position_files, $portfolio_files);
				foreach($position_files AS $k => $v) {
					PortfolioInformation_CustodianInteractions_Model::AuditPershingPositions($v);
				}
				PortfolioInformation_CustodianInteractions_Model::UpdateSecurityTypeIDs();

				PortfolioInformation_CustodianInteractions_Model::RemoveCustodianPortfolios("Pershing");
				foreach($portfolio_files AS $k => $v) {
					PortfolioInformation_CustodianInteractions_Model::AuditPershingPortfolios($v);
				}

				echo "Pershing Copied";
			}break;
			case "EmptyPortfoliosTable":{
				PortfolioInformation_CustodianInteractions_Model::EmptyPortfoliosTable();
			}break;
			case "CompareToCSV": {
				$account_number = $request->get('account_number');
				echo json_encode(PortfolioInformation_CustodianInteractions_Model::CompareToCSV($account_number));
			}break;
			case "ResetAccount":{
				$interaction = new PortfolioInformation_ManualInteractions_Model();
				require_once("include/utils/cron/cTransactionsAccess.php");
				$result = $interaction->ResetAccountTransactions($request->get('account_number'));
				if(is_numeric($result)) {
					$asset_allocation = new PortfolioInformation_AssetAllocation_Action();
					$crmid = PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($interaction->ConfirmAccountNumber($request->get('account_number')));
					$asset_allocation->UpdateIndividualAccount($crmid);
					PortfolioInformation_CustodianInteractions_Model::UpdateSecurityTypeIDs($request->get('account_number'));
					echo 1;
				}else{
					echo "Error: {$result}";
				}
			}break;
			case "AutoRepairAccounts":{
				$bad_accounts = PortfolioInformation_CustodianInteractions_Model::GetBadCSVPortfolioAccountList();
				$interaction = new PortfolioInformation_ManualInteractions_Model();
				require_once("include/utils/cron/cTransactionsAccess.php");
				$counter = 0;
				set_time_limit (0);
				foreach($bad_accounts AS $k => $v) {
					if($counter < 300) {
						$result = $interaction->ResetAccountTransactions($v);
						if (is_numeric($result)) {
							$asset_allocation = new PortfolioInformation_AssetAllocation_Action();
							$crmid = PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($interaction->ConfirmAccountNumber($v));
							if ($crmid) {
								$asset_allocation->UpdateIndividualAccount($crmid);
								PortfolioInformation_CustodianInteractions_Model::UpdateSecurityTypeIDs($v);
							}
						}
						$counter++;
					}
				}
				echo "Accounts Finished Resetting";
			}break;
		    case "PositionsToTransactions":{
		        $account_number = $request->get('account_number');
		        $date = $request->get('date');
                $start = DateTime::createFromFormat('m-d-Y', $date);
                $date = $start->format("Y-m-d");
		        if(strlen($date) < 5 || strlen($account_number) < 3) {
                    echo 0;
                }
		        else {
                    PortfolioInformation_CustodianInteractions_Model::CreateTransactionsFromPositions($account_number, $date);
                    echo 1;
                }

		        $account_number = array($account_number);
                $tmp = new CustodianClassMapping($account_number);
                $tmp->transactions::CreateNewTransactionsForAccounts($account_number);
                if(PortfolioInformation_Module_Model::getInstanceSetting("update_transactions", 1) == 1)
                    $tmp->transactions::UpdateTransactionsForAccounts($account_number);
            }break;
		}
//		$interaction = new PortfolioInformation_ManualInteractions_Model();
//		switch($request->get('todo')){

//		}
	}
}

?>
