<?php
require_once("include/utils/omniscientCustom.php");
/**
 * Created by PhpStorm.
 * User: Ryan
 * Date: 2018-10-30
 * Time: 3:42 PM
 */
/*
function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
            // This error code is not included in error_reporting
            return;
        }
    throw new ErrorException($message, 0, $severity, $file, $line);
}*/

class PortfolioInformation_TotalBalances_Model extends Vtiger_Module{

    /**
     * Loop through all users and create values for any user that has not been created yet
     */
    static public function WriteAllUnknownUserTotals() {
        global $adb;
        $query = "SELECT id FROM vtiger_users WHERE id NOT IN (SELECT user_id FROM daily_user_total_balances) AND status = 'Active' ORDER BY id ASC";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $user_id = $x['id'];
                $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersForSpecificUser($user_id, false);

                $questions = generateQuestionMarks($account_numbers);
                $q = "INSERT INTO daily_user_total_balances
                          SELECT {$user_id}, SUM(account_value) AS total_value, COUNT(account_number) AS num_accounts, as_of_date
                          FROM consolidated_balances
                          WHERE account_number IN({$questions})
                          GROUP BY as_of_date
                      ON DUPLICATE KEY UPDATE total_balance = VALUES(total_balance), num_accounts = VALUES(num_accounts)";
                $adb->pquery($q, array($account_numbers));
            }
        }
    }

    /**
     * Write and update all balance values for the given user.  This essentially works as a reset, updating anything that already exists as well as adding anything that is missing
     * @param $user_id
     */
    static public function WriteAndUpdateAllForUser($user_id){
        global $adb;
        $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersForSpecificUser($user_id, false);
        $questions = generateQuestionMarks($account_numbers);
        $query = "INSERT INTO daily_user_total_balances (user_id, total_balance, num_accounts, balance_date, last_update)
                      SELECT {$user_id}, SUM(account_value) AS total_value, COUNT(account_number) AS num_accounts, as_of_date, NOW()
                      FROM consolidated_balances
                      WHERE account_number IN({$questions})
                      GROUP BY as_of_date
                  ON DUPLICATE KEY UPDATE total_balance = VALUES(total_balance), num_accounts = VALUES(num_accounts)";
        $adb->pquery($query, array($account_numbers), true);
    }

    /**
     * Fill in the consolidated_balances table.  Essentially just has the balance for each account for any given day
     * @param null $start
     * @param null $end
     */
    static public function ConsolidateBalances($start = null, $end = null){
        global $adb, $dbconfig;

        $db_name = $dbconfig['db_name'];
        if($start == null)
            $start = date('Y-m-d', strtotime('-7 days'));
        if($end == null)
            $end = date('Y-m-d');

        $query = "CALL CONSOLIDATE_BALANCES(?, ?, ?)";
        $adb->pquery($query, array($db_name, $start, $end));
    }

    /**
     * Loop through all users and create/update values for the last 7 days
     */
    static public function WriteAndUpdateLast7DaysForAllUsers(){
        global $adb;
        $ids = GetAllActiveUserIDs();
        $date = date('Y-m-d', strtotime('-7 days'));
        foreach($ids AS $k => $v){
            $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersForSpecificUser($v, false);
            $questions = generateQuestionMarks($account_numbers);
            $query = "SELECT {$v} AS user_id, SUM(account_value) AS total_value, COUNT(account_number) AS num_accounts, as_of_date, NOW() AS calculated_date
                          FROM consolidated_balances
                          WHERE account_number IN({$questions}) AND as_of_date >= ?
                          GROUP BY as_of_date";
            $result = $adb->pquery($query, array($account_numbers, $date));

            if($adb->num_rows($result) > 0){
                $query = "INSERT INTO daily_user_total_balances VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE total_balance = VALUES(total_balance), num_accounts = VALUES(num_accounts)";
                while($c = $adb->fetchByAssoc($result)) {
                    $adb->pquery($query, array($c));
                }
            }
        }
    }

    /**
     * Loop through all users and create/update values for the last 7 days
     */
    static public function WriteAndUpdateLastXDaysForAllUsers($days){
        global $adb;
        $ids = GetAllActiveUserIDs();
        $date = date('Y-m-d', strtotime("-{$days} days"));
        foreach($ids AS $k => $v){
            //First delete anything belonging to the user for the passed in date, it is about to be recalculated
            $query = "DELETE FROM daily_user_total_balances WHERE user_id = ? AND as_of_date >= ?";
            $adb->pquery($query, array($v, $date));

            $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersForSpecificUser($v, false);
            $questions = generateQuestionMarks($account_numbers);
            $query = "SELECT {$v} AS user_id, SUM(account_value) AS total_value, COUNT(account_number) AS num_accounts, as_of_date, NOW() AS calculated_date
                          FROM consolidated_balances
                          WHERE account_number IN({$questions}) AND as_of_date >= ?
                          GROUP BY as_of_date";
            $result = $adb->pquery($query, array($account_numbers, $date));

            if($adb->num_rows($result) > 0){
                $query = "INSERT INTO daily_user_total_balances VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE total_balance = VALUES(total_balance), num_accounts = VALUES(num_accounts)";
                while($c = $adb->fetchByAssoc($result)) {
                    $adb->pquery($query, array($c));
                }
            }
        }
    }

    /**
     * Loop through all users and create/update values for the last X number days
     */
    static public function WriteAndUpdateLastXDaysForAllUsersIntervals($num_days){
        global $adb;
        $ids = GetAllActiveUserIDs();
        $date = date('Y-m-d', strtotime('-' . $num_days . ' days'));
        foreach($ids AS $k => $v){
            $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersForSpecificUser($v, false);

            $questions = generateQuestionMarks($account_numbers);
            $query = "INSERT INTO daily_user_intervals_summed
                          SELECT {$v}, SUM(intervalEndValue) AS total_value, COUNT(accountNumber) AS num_accounts, intervalEndDate, SUM(investmentreturn) as investmentreturn, SUM(NetFlowAmount) AS netflowamount
                          FROM intervals_daily
                          WHERE AccountNumber IN({$questions}) AND intervalEndDate >= ?
                          GROUP BY intervalEndDate
                      ON DUPLICATE KEY UPDATE total_balance = VALUES(total_balance), num_accounts = VALUES(num_accounts), investment_return = VALUES(investment_return), net_flow_amount = VALUES(net_flow_amount)";
            $adb->pquery($query, array($account_numbers, $date));
        }
    }

    static public function GetOmniSettings($field){
        global $adb;
        $query = "SELECT {$field} FROM vtiger_omni_settings";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, $field);
        }
        return 0;
    }

    static public function WriteAndUpdateAssetAllocationUserDaily(){
        global $adb;
        $ids = GetAllActiveUserIDs();

        foreach($ids AS $k => $v){
            try {
                StatusUpdate::UpdateMessage("TDUPDATER", "Updating user id {$v}");
                $user_record = Users_Record_Model::getInstanceById($v, 'Users');
                $is_admin = ($user_record && $user_record->get('is_admin') === 'on');

                $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersForSpecificUser($v, false);
                if($is_admin || count($account_numbers) > 0) {
                    $query = "DELETE FROM vtiger_asset_class_totals_users WHERE user_id = ?";
                    $adb->pquery($query, array($v));

                    if($is_admin) {
                        $query = "SELECT {$v} AS user_id, SUM(value) AS value, CASE WHEN base_asset_class IS NULL OR base_asset_class = '' THEN 'Other' ELSE base_asset_class END AS base_asset_class 
                                  FROM vtiger_asset_class_totals ach
                                  WHERE value != 0
                                  GROUP BY base_asset_class";
                        $result = $adb->pquery($query, array());
                    } else {
                        $questions = generateQuestionMarks($account_numbers);
                        $query = "SELECT {$v} AS user_id, SUM(value) AS value, CASE WHEN base_asset_class IS NULL OR base_asset_class = '' THEN 'Other' ELSE base_asset_class END AS base_asset_class 
                                  FROM vtiger_asset_class_totals ach
                                  WHERE account_number IN ({$questions})  
                                  AND value != 0
                                  GROUP BY base_asset_class";
                        $result = $adb->pquery($query, array($account_numbers));
                    }
                    if($adb->num_rows($result) > 0){
                        $query = "INSERT INTO vtiger_asset_class_totals_users VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)";
                        while($c = $adb->fetchByAssoc($result)){
                            $adb->pquery($query, array($c));
                        }
                    }
                }
            }catch(Exception $e){
                StatusUpdate::UpdateMessage("TDUPDATER", "Error Encountered");
                $note = "Trying to run WriteAndUpdateAssetAllocationUserDaily for user {$v}  Likely caused by the users privilege file not existing";
                $query = "INSERT INTO vtiger_exceptions(message, date_time, code_notes) VALUES(?, NOW(), ?)";
                $adb->pquery($query, array($e->getMessage(), $note));
            }
        }
    }

    static public function ClosePositionsBasedOnTheirPortfolio(){
        global $adb;
        $query = "UPDATE vtiger_positioninformation pos
                  JOIN vtiger_positioninformationcf poscf USING (positioninformationid)
                  JOIN vtiger_portfolioinformation p ON p.account_number = pos.account_number
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  SET poscf.position_closed = 1
                  WHERE (p.accountclosed = 1 OR e.deleted = 1)";
        $adb->pquery($query, array());
    }
}