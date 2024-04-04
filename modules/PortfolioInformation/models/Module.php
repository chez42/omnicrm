<?php
include_once "include/utils/omniscientCustom.php";
include_once("libraries/Stratifi/StratifiAPI.php");
require_once("libraries/custodians/cCustodian.php");

include_once("libraries/Reporting/Reporting.php");

class PortfolioInformation_Module_Model extends Vtiger_Module_Model
{
    /**
     * Sets all production numbers that are currently null or empty
     * @global type $adb
     */
    static public function SetAllProductionNumbers()
    {
        global $adb;
        $query = "DROP TABLE IF EXISTS tmp_production;";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tmp_production
        SELECT p.account_number, adv.pc_name FROM vtiger_portfolioinformation p
        JOIN vtiger_portfolioinformationcf pcf ON p.portfolioinformationid = pcf.portfolioinformationid
        JOIN vtiger_portfolios por ON p.account_number = por.portfolio_account_number
        JOIN vtiger_pc_advisors adv ON adv.pc_id = por.advisor_id
        WHERE pcf.production_number = '' 
        OR pcf.production_number IS NULL;";
        $adb->pquery($query, array());

        $query = "UPDATE vtiger_portfolioinformation p
        JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
        JOIN tmp_production t ON p.account_number = t.account_number
        SET cf.production_number = t.pc_name
        WHERE p.account_number = t.account_number;";
        $adb->pquery($query, array());
    }

    static public function TestPositionsAgainstTotalForAccount($account_number)
    {
        $total = PositionInformation_Module_Model::GetTotalvalueForAccountNumberUsingPositions($account_number);
        $id = PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($account_number);
        $record = PortfolioInformation_Record_Model::getInstanceById($id);
#        if($total >= $record->get('total_value')+10 || $total <= $record->get('total_value')-10){
        if ($total != $record->get('total_value')) {
            $positions = PositionInformation_Module_Model::GetPositionsForAccountNumber($account_number);
            $origination = $record->get('origination');
            $symbols = array();
            foreach ($positions AS $k => $v) {
                $symbols[] = $v['security_symbol'];
            }
            switch ($origination) {
                case stristr($origination, 'schwab'):
                    if (sizeof($symbols) > 0)
                        ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsSchwab($symbols);
                    break;
                case stristr($origination, 'fidelity'):
                    ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsFidelity($symbols, true);
                    break;
            }
//                ModSecurities_ConvertCustodian_Model::UpdateSecurityType($record->get('origination'), $v['security_symbol']);
//            }
        }
    }

    /**
     * Get a list of all rep codes
     */
    static public function GetRepCodeList(){
        global $adb;
        $query = "SELECT production_number, origination
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  GROUP BY production_number";
        $result = $adb->pquery($query, array());
        $rep_codes = array();
        if($adb->num_rows($result) > 0){
            while ($v = $adb->fetchByAssoc($result)) {
                $rep_codes[] = array("rep_code" => $v['production_number'],
                    "custodian" => $v['origination']);
            }
        }else{
            return array();
        }
        return $rep_codes;
    }
    
    // Have to Give Default Argument as per php version 7.2
    static public function AssignPortfolioBasedOnRepCodes($account_number = null)
    {
        global $adb;
#        $questions = generateQuestionMarks($account_number);
#        $params = array();


        $query = "DROP TABLE IF EXISTS UpdatePortfolios";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE UpdatePortfolios
                    SELECT crmid, production_number, master_production_number, omniscient_control_number, 0 AS user_id
                    FROM vtiger_crmentity e
                    JOIN vtiger_portfolioinformation p ON p.portfolioinformationid = e.crmid
                    JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = e.crmid
                    WHERE (e.smownerid = 1 OR p.origination = 'td' OR p.origination = 'schwab' OR e.smownerid = '')
                    AND e.deleted = 0
                    AND (CHAR_LENGTH(production_number) > 1 OR CHAR_LENGTH(master_production_number) > 1 OR CHAR_LENGTH(omniscient_control_number) > 1)";
        $adb->pquery($query, array());
#WHERE advisor_control_number RLIKE (CONCAT('[[:<:]]',p.production_number,'[[:>:]]'))


        $query = "UPDATE UpdatePortfolios p
                    SET user_id = (SELECT id FROM vtiger_users u
                               WHERE omniscient_control_number LIKE (CONCAT('%',p.omniscient_control_number,'%'))
                               AND CHAR_LENGTH(omniscient_control_number) > 1 LIMIT 1)
                    WHERE p.user_id = 0
                    AND CHAR_LENGTH(p.omniscient_control_number) > 1";
        $adb->pquery($query, array());

        $query = "UPDATE UpdatePortfolios p
                    SET user_id = (SELECT id FROM vtiger_users u
                               WHERE advisor_control_number LIKE (CONCAT('%',p.production_number,'%'))
                               AND CHAR_LENGTH(advisor_control_number) > 1 LIMIT 1)
                    WHERE CHAR_LENGTH(p.production_number) > 1";
        $adb->pquery($query, array());
#WHERE advisor_control_number RLIKE (CONCAT('[[:<:]]',p.master_production_number,'[[:>:]]'))

        $query = "UPDATE UpdatePortfolios p
                    SET user_id = (SELECT id FROM vtiger_users u
                               WHERE advisor_control_number LIKE (CONCAT('%',p.master_production_number,'%'))
                               AND CHAR_LENGTH(advisor_control_number) > 1 LIMIT 1)
                    WHERE p.user_id = 0
                    AND CHAR_LENGTH(p.master_production_number) > 1";
        $adb->pquery($query, array());

        $query = "UPDATE vtiger_crmentity e
                    JOIN UpdatePortfolios up ON e.crmid = up.crmid
                    SET e.smownerid = up.user_id
                    WHERE up.user_id > 0";
        $adb->pquery($query, array());

    }

    static public function InvalidatePortfolioAndSetDeleted($pid){
        global $adb;
        $query = "UPDATE vtiger_portfolios SET isvalid = 0, account_closed = 1 WHERE portfolio_id = ?";
        $adb->pquery($query, array($pid));
    }

    static public function UpdatePortfolioTableSSNFromAccountNumber($ssn, $account_number){
        global $adb;
        $query = "UPDATE vtiger_portfolios SET portfolio_tax_id = ? WHERE portfolio_account_number = ?";
        $adb->pquery($query, array($ssn, $account_number));
    }

    static public function GetAccountNumbersFromCrmid($crmid)
    {
        global $adb;
        $instance = Vtiger_Record_Model::getInstanceById($crmid);
        $params = array();
        $ssn = array();
        $params[] = $crmid;
        $params[] = $crmid;
        $params[] = $crmid;

        switch ($instance->getModuleName()) {
            case "Contacts":
                $ssn[] = $instance->get('ssn');
                $params[] = $ssn;
                $questions = generateQuestionMarks($ssn);
                $or = " OR (tax_id IN ({$questions}) AND tax_id != '' AND tax_id != 0) ";
                break;
            case "Accounts":
                $ssn[] = GetSSNsForHousehold($crmid);
                $params[] = $ssn;
                $questions = generateQuestionMarks($ssn);
                $or = " OR (tax_id IN ({$questions}) AND tax_id != '' AND tax_id != 0) ";
                break;
        }

        $query = "SELECT p.account_number FROM vtiger_portfolioinformation p 
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  JOIN vtiger_crmentity e ON p.portfolioinformationid = e.crmid
                  WHERE (e.crmid = ? OR p.contact_link = ? OR household_account = ? {$or})
                  AND e.deleted = 0";

        $result = $adb->pquery($query, $params);
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $accounts[] = $v['account_number'];
            }
            return $accounts;
        }
        return 0;
    }

    static public function GetAccountNumberFromCrmid($crmid)
    {
        global $adb;
        $instance = Vtiger_Module_Model::getInstance($crmid);

        $query = "SELECT p.account_number FROM
                  vtiger_portfolioinformation p 
                  JOIN vtiger_crmentity e ON p.portfolioinformationid = e.crmid
                  WHERE e.crmid = ? OR p.contact_link = ? OR household_account = ?
                  AND e.deleted = 0 AND p.accountclosed = 0";
        $result = $adb->pquery($query, array($crmid, $crmid, $crmid));
        if ($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'account_number');
        return 0;
    }

    static public function UpdateCustodianNameDirectly($account_number, $custodian)
    {
        global $adb;
        $query = "UPDATE vtiger_portfolioinformation SET origination = ? WHERE account_number = ?";
        $adb->pquery($query, array($custodian, $account_number));
    }

    static public function GetHouseholdEntityFromAccountNumber($account_number)
    {
        if (is_array($account_number))
            $account_number = $account_number[0];

        $crmid = PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($account_number);
        if ($crmid) {
            $p = PortfolioInformation_Record_Model::getInstanceById($crmid);
            $household_id = $p->get('household_account');
            if ($household_id) {
                $household_instance = Accounts_Record_Model::getInstanceById($household_id);
                return $household_instance;
            }
        }
        return 0;
    }

    static public function GetContactEntityFromAccountNumber($account_number)
    {
        if (is_array($account_number))
            $account_number = $account_number[0];

        $crmid = PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($account_number);
        if ($crmid) {
            $p = PortfolioInformation_Record_Model::getInstanceById($crmid);
            $contact_id = $p->get('contact_link');
            if ($contact_id) {
                $contact_instance = Contacts_Record_Model::getInstanceById($contact_id);
                return $contact_instance;
            }
        }
        return null;
    }

    static public function CheckCloudForAccountNumber($custodian, $custodianDB, $account_number)
    {
        global $adb;
        $query = "SELECT account_number FROM {$custodianDB}.custodian_portfolios_{$custodian} WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number)) or die(mysql_error());
        if ($adb->num_rows($result) > 0)
            return 1;
        return 0;
    }

    static public function GetCrmidFromAccountNumber($account_number, $ignore_closed = false)
    {
        global $adb;
        if ($ignore_closed)
            $and = " AND accountclosed = 0 ";

        $query = "SELECT e.crmid 
                  FROM vtiger_crmentity e
                  JOIN vtiger_portfolioinformation p ON p.portfolioinformationid = e.crmid
                  WHERE p.account_number = ?
                  AND e.deleted = 0 {$and} ";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'crmid');
        return null;
    }

    static public function GetCustodianFromAccountNumber($account_number)
    {
        global $adb;
        $query = "SELECT origination 
                  FROM vtiger_portfolioinformation p
                  WHERE p.account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'origination');
        return 0;
    }


    static public function GetTransactionFlowValueBeforeDate($account_number, $date, array $transaction_type){
        $transactions_value = self::GetTransactionValuesByTypeOnDate($account_number, $date, $transaction_type, true);
        return $transactions_value;
        /*
                return array("date" => $trade_date,
                             "value" => $transactions_value);
        */
    }

    static public function GetFirstTransactionDate($account_number){
        global $adb;
        $query = "SELECT MIN(trade_date) AS trade_date FROM vtiger_transactions WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        $trade_date = null;

        if($adb->num_rows($result) > 0) {
            $trade_date = $adb->query_result($result, 0, 'trade_date');
            return $trade_date;
        }else{
            return 0;
        }
    }

    /**
     * Get all transaction values summed of type passed in ON the passed in date.  Less than true makes it less than date, rather than on date
     * @param $account_number
     * @param $typesfGetTransactionValuesByTypeOnDate
     */
    static public function GetTransactionValuesByTypeOnDate($account_number, $trade_date, array $types, $less_than = false){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($types);

        $params[] = $account_number;
        $params[] = $types;
        $params[] = $trade_date;

        if($less_than == true)
            $symbol = "<";
        else
            $symbol = "=";

        $query = "SELECT * FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  WHERE account_number = ?
                  AND transaction_type IN ({$questions})
                  AND trade_date {$symbol} ?
                  AND e.deleted = 0";
        $value = 0;
        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while ($t = $adb->fetchByAssoc($result)) {
                $val = $t['operation'] . $t['net_amount'];
                $value += $val;
            }
        }
        return $value;
    }

    /**
     * Get the first balance and date from the cloud for the provided account/custodian
     * @param $account_number
     * @return array
     * @throws Exception
     */
    static public function GetFirstBalanceInfo($account_number){
        global $adb;
        #1 Get balance start date and start value
        $custodian = PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($account_number);
        $query = "CALL GET_FIRST_BALANCE(?, ?, @beginningNet, @beginningDate)";
        $adb->pquery($query, array($account_number, $custodian));

        $query = "SELECT @beginningnet AS net, @beginningdate AS date";
        $result = $adb->pquery($query, array());

        $start_value = $adb->query_result($result, 0, 'net');
        $start_date = $adb->query_result($result, 0, 'date');

        $info = array("value" => $start_value,
            "date" => $start_date);
        return $info;
    }

    /**
     * Determine if an account has any intervals between begin date and end date
     * @param $account_number
     * @param $sdate
     * @param $edate
     * @return bool
     */
    static public function DoesAccountHaveIntervalData($account_number, $sdate, $edate){
        global $adb;//We use interval end date as that is technically the date we want to start with and this holds that day's beginning AND ending values
        $query = "SELECT COUNT(*) as count
                  FROM intervals_daily 
                  WHERE IntervalEndDate >= ? AND IntervalEndDate <= ?
                  AND AccountNumber = ?";
        $result = $adb->pquery($query, array($sdate, $edate, $account_number));
        if($adb->num_rows($result) > 0){
            if($adb->query_result($result, 0, 'count') > 0)
                return true;
        }
        return false;
    }

    static public function CheckIfAccountExists($account_number)
    {
        global $adb;
        $account_number = str_replace("-", "", $account_number);
        $query = "SELECT * FROM vtiger_portfolioinformation WHERE REPLACE(account_number, '-', '') = ?";
        $r = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($r) > 0)
            return $adb->num_rows($r);
        return 0;
    }

    static public function SetAccountAsDeleted($accounts)
    {
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $query = "UPDATE vtiger_crmentity e
                  JOIN vtiger_portfolioinformation p ON p.portfolioinformationid = e.crmid
                  SET e.deleted = 1, p.accountclosed=1
                  WHERE p.account_number IN ({$questions})";
        $adb->pquery($query, array($accounts));
    }

    static public function SetAccountAsUnDeleted($accounts)
    {
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $query = "UPDATE vtiger_crmentity e
                  JOIN vtiger_portfolioinformation p ON p.portfolioinformationid = e.crmid
                  SET e.deleted = 0, p.accountclosed = 0
                  WHERE p.account_number IN ({$questions})";
        $adb->pquery($query, array($accounts));
    }

    static public function SetAccountTaxID($account_number, $tax_id)
    {
        global $adb;
        $account_number = str_replace('-', '', $account_number);
        $query = "UPDATE vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  SET cf.tax_id = ?
                  WHERE p.account_number = ?";
        $adb->pquery($query, array($tax_id, $account_number));
    }

    static public function GetAllActiveAccountNumbers($manual_only = true)
    {
        global $adb;
        $and = "";
        if ($manual_only == true)
            $and = "AND p.account_number LIKE ('M%')";
#        else
#            $and = "AND origination NOT IN ('fidelity', 'schwab', 'pershing', 'td', 'tdameritrade', 'tda')";

        $query = "SELECT account_number FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 {$and}";
        $result = $adb->pquery($query, array());
        $accounts = array();
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $accounts[] = $v['account_number'];
            }
        }
        return $accounts;
    }

    static public function GetAccountNumbersFromContactID($contact_id){
        global $adb;
        $query = "SELECT account_number 
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON (p.portfolioinformationid = cf.portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE contact_link = ? AND p.accountclosed = 0 AND e.deleted = 0";
        $result = $adb->pquery($query, array($contact_id));
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetch_array($result))
                $accounts[] = $v['account_number'];
        }
        return $accounts;
    }

    static public function GetAccountNumbersFromHouseholdID($contact_id){
        global $adb;
        $query = "SELECT account_number 
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON (p.portfolioinformationid = cf.portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE household_account = ? AND p.accountclosed = 0 AND e.deleted = 0";
        $result = $adb->pquery($query, array($contact_id));
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetch_array($result))
                $accounts[] = $v['account_number'];
        }
        return $accounts;
    }

    static public function GetUniqueContactIDsFromPortfolioModule(){
        global $adb;

        $query = "SELECT DISTINCT(contact_link) AS contact_id 
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND p.accountclosed = 0 AND contact_link IS NOT NULL";
        $result = $adb->pquery($query, array());
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $contacts[] = $v['contact_id'];
            }
        }
        return $contacts;
    }

    static public function GetUniqueHouseholdIDsFromPortfolioModule(){
        global $adb;

        $query = "SELECT DISTINCT(household_account) AS household_account 
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND p.accountclosed = 0 AND household_account IS NOT NULL";
        $result = $adb->pquery($query, array());
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $households[] = $v['household_account'];
            }
        }
        return $households;
    }

    static public function GetAccountsWithoutLastMonthIntervalCalculated(){
        global $adb;

        $query = "SELECT account_number FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND accountclosed = 0 
                  AND p.account_number NOT IN ( SELECT AccountNumber FROM intervals_daily 
                                                WHERE intervaltype = 'monthly'
                                                AND IntervalEndDate >= CURRENT_DATE() - INTERVAL 1 MONTH)";
        $result = $adb->pquery($query, array());
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $accounts[] = $v['account_number'];
            }
        }
        return $accounts;
    }

    static public function GetAllOpenAccountNumbers()
    {
        global $adb;

        $query = "SELECT account_number FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND accountclosed = 0 ";
        $result = $adb->pquery($query, array());
        $accounts = array();
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $accounts[] = $v['account_number'];
            }
        }
        return $accounts;
    }

    static public function GetAccountsToCalculateTWR()
    {
        global $adb;

        $query = "SELECT account_number FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND accountclosed = 0 
                  AND year(last_twr_calculated) <= year(curdate()) 
                  AND month(last_twr_calculated) < month(curdate())";
        $result = $adb->pquery($query, array());
        $accounts = array();
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $accounts[] = $v['account_number'];
            }
        }
        return $accounts;
    }

    static public function GetFreezeDateForAccount($account_number)
    {
        global $adb;
        $query = "SELECT stated_value_date FROM vtiger_portfolioinformationcf JOIN vtiger_portfolioinformation USING (portfolioinformationid) WHERE account_number = ? AND frozen = 1";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0) {
            $date = $adb->query_result($result, 0, 'stated_value_date');
            return $date;
        }
        return 0;
    }

    static public function ResetPortfolioValues($accounts)
    {
        global $adb;
        $accounts = RemoveDashes($accounts);
        $questions = generateQuestionMarks($accounts);
        $query = "UPDATE vtiger_portfolioinformation JOIN vtiger_portfolioinformationcf USING (portfolioinformationid)
			      SET total_value = 0, market_value = 0, cash_value = 0, cash = 0, equities = 0, fixed_income = 0, unsettled_cash = 0, 
			      	  other_value = 0, unclassified_value = 0, dividend_accrual = 0, short_market_value = 0, short_balance = 0, securities = 0
			      WHERE dashless IN ({$questions})";

        $adb->pquery($query, array($accounts));
    }

    static public function GetAllDashlessAndCustodian()
    {
        global $adb;
        echo "This needs to be done better, otherwise the script times out";
        exit;
        $query = "SELECT dashless, origination FROM vtiger_portfolioinformation p WHERE origination IN ('millenium', 'fidelity', 'schwab', 'td', 'pershing')";
        $result = $adb->pquery($query, array());
        $accounts = array();
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $accounts[] = array("account_number" => $v['dashless'],
                    "custodian" => $v['origination']);
            }
        }
        return $accounts;
    }

    static public function UpdateAllPortfolioInceptionDates()
    {
        global $adb;
        $query = "DROP TABLE IF EXISTS tmp_inception;";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tmp_inception
        SELECT p.portfolio_account_number, t.portfolio_id, t.trade_date
        FROM vtiger_pc_transactions t FORCE INDEX (TradeDate)
        JOIN vtiger_portfolios p ON t.portfolio_id = p.portfolio_id
        WHERE t.portfolio_id IN (select portfolio_id from vtiger_portfolioinformation pin
                                                         JOIN vtiger_portfolios p ON p.portfolio_account_number = pin.account_number)
        group by t.portfolio_id
        ORDER BY t.trade_date ASC;";
        $adb->pquery($query, array());

        $query = "update vtiger_portfolioinformation p
        JOIN tmp_inception i ON p.account_number = i.portfolio_account_number
        SET p.inceptiondate = i.trade_date
        WHERE p.account_number = i.portfolio_account_number;";
        $adb->pquery($query, array());

        return 1;
    }

    static public function FindAndFixEmptyInceptionDates()
    {
        global $adb;
        $query = "SELECT p.account_number
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE p.inceptiondate is null";
        $result = $adb->pquery($query, array());

        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                self::UpdateInceptionDate($v['account_number']);
            }
        }
    }

    static public function UpdateInceptionDate($account_number)
    {
        global $adb;
        $query = "SELECT portfolio_id FROM vtiger_portfolios WHERE portfolio_account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0) {
            $portfolio_id = $adb->query_result($result, 0, 'portfolio_id');
            $query = "SELECT trade_date from vtiger_pc_transactions
                      WHERE portfolio_id = ?
                      ORDER BY trade_date ASC";
            $result = $adb->pquery($query, array($portfolio_id));
            if ($adb->num_rows($result) > 0) {
                $trade_date = $adb->query_result($result, 0, 'trade_date');
                $query = "UPDATE vtiger_portfolioinformation SET inceptiondate = ? WHERE account_number = ?";
                $adb->pquery($query, array($trade_date, $account_number));
                return 1;
            }
        }
        return 0;
    }

    static public function GetAccountNumbersFromSSN($ssn)
    {
        global $adb;
        if (!is_array($ssn))
            $ssns[] = $ssn;
        $ssns = $ssn;
        foreach ($ssns AS $k => $v)
            $ssns[$k] = str_replace('-', '', $v);

        $questions = generateQuestionMarks($ssns);

        $query = "SELECT account_number FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                  WHERE REPLACE(cf.tax_id, '-', '') IN ({$questions}) AND REPLACE(cf.tax_id, '-', '') != '' AND accountclosed = 0";
        $result = $adb->pquery($query, array($ssns));
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $t[] = $v['account_number'];
            }
            return $t;
        }
        return 0;
    }

    static public function GetAccountNumbersFromOmniscientControlNumber($ccn, $limit = null)
    {
        global $adb;
        if (strlen($limit) > 0)
            $limit = " LIMIT {$limit} ";
        $questions = generateQuestionMarks($ccn);

        $query = "SELECT account_number FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid 
                  WHERE omniscient_control_number IN ({$questions}) AND e.deleted = 0 AND p.accountclosed = 0 AND stated_value_date >= '2018-11-01' {$limit}";
        $result = $adb->pquery($query, array($ccn));
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $t[] = $v['account_number'];
            }
            return $t;
        }
        return 0;
    }

    /**
     * Get a list of account numbers whether or not they have been marked closed/deleted
     * @param $ccn
     * @param null $limit
     * @return array|int
     */
    static public function GetAccountNumbersFromRepCodeOpenAndClosed($ccn, $limit = null)
    {
        global $adb;
        if (strlen($limit) > 0)
            $limit = " LIMIT {$limit} ";
        $questions = generateQuestionMarks($ccn);

        $query = "SELECT account_number FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                  WHERE production_number IN ({$questions})";//We don't want CRMEntity as a requirement, it can cause issues here when the portfolio account number exists but the entity doesn't
        $result = $adb->pquery($query, array($ccn));

        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $t[] = $v['account_number'];
            }
            return $t;
        }
        return array();
    }

    /**
     * Get a list of account numbers whether or not they have been marked closed/deleted
     * @param $ccn
     * @param null $limit
     * @return array|int
     */
    static public function GetAllAccountNumbersInCRM($ccn, $limit = null)
    {
        global $adb;

        $query = "SELECT account_number FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid";
        $result = $adb->pquery($query, array());

        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $t[] = $v['account_number'];
            }
            return $t;
        }
        return null;
    }

    static public function GetAccountNumbersFromRepCode($ccn, $limit = null)
    {
        global $adb;
        if (strlen($limit) > 0)
            $limit = " LIMIT {$limit} ";
        $questions = generateQuestionMarks($ccn);

        $query = "SELECT account_number FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid 
                  WHERE production_number IN ({$questions}) AND e.deleted = 0 AND p.accountclosed = 0 {$limit} ";//AND stated_value_date >= '2019-10-01' {$limit}";
        $result = $adb->pquery($query, array($ccn));

        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $t[] = $v['account_number'];
            }
            return $t;
        }
        return 0;
    }

    static public function GetChartColorForTitle($title){
        global $adb;
        $query = "SELECT color FROM vtiger_chart_colors WHERE title = ?";
        $result = $adb->pquery($query, array($title));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'color');
        }
        return 0;
    }

    static public function GetRecordIDFromAccountNumber($account_number)
    {
        global $adb;
        $query = "SELECT portfolioinformationid FROM vtiger_portfolioinformation WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0) {
            return $adb->query_result($result, 0, 'portfolioinformationid');
        }
        return 0;
    }

    /**
     * Update the specified PortfolioInformation field.  Months represents the number of months to calculate
     * @param $field
     * @param $months
     */
    static public function UpdatePortfolioInformationIntervalValue($field, $num_months)
    {
        global $adb;
        $query = "DROP TABLE IF EXISTS CalculatedIntervals";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS LatestIntervals";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS TrailingIntervals";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS CalculatedTrailing";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS TrailingPercent";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE TrailingIntervals
                  SELECT i.* FROM intervals i
                  WHERE i.IntervalEndDate >= DATE_SUB(NOW(), INTERVAL {$num_months} MONTH) 
                  AND i.IntervalEndDate <= NOW()
                  GROUP BY AccountNumber, IntervalEndDate
                  ORDER BY AccountNumber";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE CalculatedTrailing
                  SELECT AccountNumber, IntervalEndDate, (1 + NetReturnAmount/100) AS NetReturnAmount FROM TrailingIntervals";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE TrailingPercent 
                  SELECT AccountNumber, ((exp(sum(log(coalesce(NetReturnAmount,1))))) - 1) * 100 AS TrailingPercent FROM CalculatedTrailing GROUP BY AccountNumber";
        $adb->pquery($query, array());

        $query = "UPDATE TrailingPercent i
                  JOIN vtiger_portfolioinformation p ON p.account_number = i.AccountNumber
                  JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = p.portfolioinformationid
                  SET cf.{$field} = i.TrailingPercent";
        $adb->pquery($query, array());
    }


    static public function GetAccountSumTotals($accounts)
    {
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $params[] = $accounts;

        $query = "SELECT SUM(total_value) AS total_value, SUM(securities) AS securities_total, SUM(cash) AS cash_total 
				  FROM vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  WHERE account_number IN ({$questions})
				  AND e.deleted = 0 AND (p.accountclosed = 0 OR p.accountclosed IS NULL)";
        $result = $adb->pquery($query, $params);
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $totals = array("total" => $v['total_value'],
                    "securities_total" => $v['securities_total'],
                    "cash_total" => $v['cash_total']);
            }
            return $totals;
        }
        return 0;
    }

    static public function GetAccountIndividualTotals($accounts)
    {
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $params[] = $accounts;

        $query = "SELECT * FROM vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  WHERE account_number IN ({$questions})
				  AND e.deleted = 0 AND (p.accountclosed = 0 OR p.accountclosed IS NULL)";
        $result = $adb->pquery($query, $params);
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $account_info[] = array("account_number" => $v['account_number'],
                    "total" => $v['total_value'],
                    "securities" => $v['securities'],
                    "cash" => $v['cash']);
            }
            return $account_info;
        }
        return 0;
    }

    static public function GetAllChartColors()
    {
        global $adb;
        $query = "SELECT title, color FROM vtiger_chart_colors";
        $result = $adb->pquery($query, array());
        $colors = array();
        if ($adb->num_rows($result) > 0) {
            foreach ($result AS $k => $v) {
                $colors[$v['title']] = $v['color'];
            }
            return $colors;
        }
        return 0;
    }

    static public function RecalculatePortfolio($account_number)
    {
        $crmid = self::GetCrmidFromAccountNumber($account_number);
        $record = Vtiger_Record_Model::getInstanceById($crmid, 'PortfolioInformation');
        if (strlen($record->get('origination') == 0)) {
            $custodian = PortfolioInformation_ConvertCustodian_Model::DetermineCustodian($record->get('account_number'));
            if ($custodian != "")
                PortfolioInformation_Module_Model::UpdateCustodianNameDirectly($record->get('account_number'), $custodian);
        }

        $nodata = 0;
        $asset_allocation = new PortfolioInformation_AssetAllocation_Action();
        $custodian = $record->get("origination");
        $date = date("Y-m-d", strtotime("today - 1 Weekday"));
        $clist = array("fidelity", "schwab", "td", "pershing", "manual");

        if (!$record->get('contact_link') && $record->get('account_number') != '') {//A contact hasn't been set, check if one exists
            PortfolioInformation_ConvertCustodian_Model::LinkContactsToPortfolios($record->get('account_number'));
            PortfolioInformation_ConvertCustodian_Model::LinkHouseholdsToPortfolios($record->get('account_number'));
        }
        //contact_link    household_account
#		print_r($data);
#		echo $record->get('contact_link');

        if (in_array(strtolower($custodian), $clist)) {
            if ($custodian) {
                if (PositionInformation_ConvertCustodian_Model::IsTherePositionDataForDate($custodian, $date, $record->get('account_number')) == 0) {
#					$date = date("Y-m-d", strtotime("today - 2 Weekday"));
                    $date = date("Y-m-d", strtotime("today - 2 Weekday"));
                    if (PositionInformation_ConvertCustodian_Model::IsTherePositionDataForDate($custodian, $date, $account_number) == 0) {
                        $date = date("Y-m-d", strtotime("today - 3 Weekday"));
                        if (PositionInformation_ConvertCustodian_Model::IsTherePositionDataForDate($custodian, $date, $account_number) == 0) {
                            $nodata = 1;
                        }
                    }
                }

                $freeze = PortfolioInformation_Module_Model::GetFreezeDateForAccount($account_number);
                if ($freeze) {
                    $date = $freeze;
                    $nodata = 0;
                }

                if ($nodata) {
                    PositionInformation_Module_Model::ResetPositionValues($record->get('account_number'));
                }

                switch (true) {
                    case stristr($custodian, 'Fidelity'):
                        if (!$nodata) {
                            PortfolioInformation_Module_Model::ResetPortfolioValues($record->get('account_number'));
                            PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFidelity($date, $record->get('account_number'));
                            $symbols = PositionInformation_Module_Model::GetSymbolsForAccountNumber($record->get('account_number'));
                            if (sizeof($symbols) > 0)
                                ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsFidelity($symbols, true);
//                            PortfolioInformation_ConvertCustodian_Model::UpdateAllFidelityPortfoliosWithLatestInfoForAccount($record->get('account_number'));
                            PositionInformation_ConvertCustodian_Model::UpdatePositionInformationFidelity($date, $record->get('account_number'), 1);
//							PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFromPositions($custodian, $record->get('account_number'));
                        }
                        break;
                    case stristr($custodian, "td"):
                        if (!$nodata) {
                            PortfolioInformation_Module_Model::ResetPortfolioValues($record->get('account_number'));
                            PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesTD($date, $record->get('account_number'));
                            $symbols = PositionInformation_Module_Model::GetSymbolsForAccountNumber($record->get('account_number'));
                            if (sizeof($symbols) > 0)
                                ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsTD($symbols, true);
                            PositionInformation_ConvertCustodian_Model::UpdatePositionInformationTD($date, $record->get('account_number'), 1);
//							PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFromPositions($custodian, $record->get('account_number'));
                        }
                        break;
                    case stristr($custodian, "schwab"):
                        if (!$nodata) {
                            PortfolioInformation_Module_Model::ResetPortfolioValues($record->get('account_number'));
                            #					PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesSchwab(null, $record->get('account_number'));
                            $symbols = PositionInformation_Module_Model::GetSymbolsForAccountNumber($record->get('account_number'), 8);
                            if (sizeof($symbols) > 0)
                                ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsSchwab($symbols);
                            $symbols = PositionInformation_Module_Model::GetSymbolsForAccountNumber($record->get('account_number'));
                            if (sizeof($symbols) > 0)
                                ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsSchwab($symbols);
                            PositionInformation_ConvertCustodian_Model::UpdatePositionInformationSchwab($date, $record->get('account_number'), 1);
//							PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFromPositions($custodian, $record->get('account_number'));
                            PortfolioInformation_ConvertCustodian_Model::UpdateAllSchwabPortfoliosWithLatestInfoForAccount($record->get('account_number'));
                        }
                        break;
                    case stristr($custodian, "pershing"):
                        if (!$nodata) {
                            /*							PortfolioInformation_Module_Model::ResetPortfolioValues($record->get('account_number'));
                                                        PositionInformation_Module_Model::ResetPositionValues($record->get('account_number'));
                                                        $symbols = PositionInformation_Module_Model::GetSymbolsForAccountNumber($record->get('account_number'));
                                                        PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesPershing($date, $record->get('account_number'));
                                                        if(sizeof($symbols) > 0)
                                                            ModSecurities_ConvertCustodian_Model::UpdateSecurityFieldsPershing($symbols, true);
                                                        PositionInformation_ConvertCustodian_Model::UpdatePositionInformationPershing($date, $record->get('account_number'), 1);*/
#							PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFromPositions($custodian, $record->get('account_number'));
                        }
                        break;
                    case stristr($custodian, "manual"):
                        PortfolioInformation_Module_Model::ResetPortfolioValues($record->get('account_number'));
                        PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFromPositions($custodian, $record->get('account_number'));
                        break;
                    default:
                        if ($asset_allocation->IsInPC($record->get('account_number')))
                            $asset_allocation->UpdateIndividualAccount($crmid, $record->get('account_number'));
                        break;
                }
            }
        } else {
            PortfolioInformation_Module_Model::ResetPortfolioValues($record->get('account_number'));
            PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFromPositions($custodian, $record->get('account_number'));
            if ($asset_allocation->IsInPC($record->get('account_number'))) {
#				echo "TESTING!";
#				exit;
                $asset_allocation->UpdateIndividualAccount($crmid, $record->get('account_number'));
            }
        }
    }

    /**
     * Function to get relation query for particular module with function name
     * @param <record> $recordId
     * @param <String> $functionName
     * @param Vtiger_Module_Model $relatedModule
     * @return <String>
     */
    public function getRelationQuery($recordId, $functionName, $relatedModule)
    {

        $relatedModuleName = $relatedModule->get('name');

        $query = parent::getRelationQuery($recordId, $functionName, $relatedModule);
        return $query;///BOTTOM SECTION NO LONGER NEEDED SINCE VTIGER 7 (left in place just in case)
        /*		if($relatedModuleName == "PositionInformation"){

                    $query = explode("FROM", $query);

                    $selectedColumns = array_map('trim',explode(",",$query[0]));

                    $qtyKey = array_search('vtiger_positioninformation.quantity', $selectedColumns);

                    if($qtyKey)
                        $selectedColumns[$qtyKey] = 'SUM(vtiger_positioninformation.quantity) AS quantity';
                    else
                        $selectedColumns[] = 'SUM(vtiger_positioninformation.quantity) AS quantity';

                    $current_value_key = array_search('vtiger_positioninformation.current_value', $selectedColumns);

                    if($current_value_key)
                        $selectedColumns[$current_value_key] = 'SUM(vtiger_positioninformation.current_value) AS current_value';
                    else
                        $selectedColumns[] = 'SUM(vtiger_positioninformation.current_value) AS current_value';

                    $costKey = array_search('vtiger_positioninformation.cost_basis', $selectedColumns);

                    if($costKey)
                        $selectedColumns[$costKey] = 'SUM(vtiger_positioninformation.cost_basis) AS cost_basis';
                    else
                        $selectedColumns[] = 'SUM(vtiger_positioninformation.cost_basis) AS cost_basis';

                    $glKey = array_search('vtiger_positioninformation.unrealized_gain_loss', $selectedColumns);

                    if($glKey)
                        $selectedColumns[$glKey] = 'SUM(vtiger_positioninformation.unrealized_gain_loss) AS unrealized_gain_loss';
                    else
                        $selectedColumns[] = 'SUM(vtiger_positioninformation.unrealized_gain_loss) AS unrealized_gain_loss';

                    $wtKey =  array_search('vtiger_positioninformation.weight', $selectedColumns);

                    if($wtKey)
                        $selectedColumns[$wtKey] = 'SUM(current_value)/@global_total*100 AS weight';
                    else
                        $selectedColumns[] = 'SUM(current_value)/@global_total*100 AS weight';

                    $query[0] = implode(",", $selectedColumns);

                    $query = implode(" FROM ", $query);
                }*/

        return $query;
    }

    static public function GetEndValuesForAccounts($accounts, $start = null, $end = null, $intervalType = 'Monthly')
    {
        global $adb;
        $params = array();
        $and = "";
        $questions = generateQuestionMarks($accounts);
        $params[] = $accounts;

        if ($start) {
            $and .= " AND IntervalEndDate >= ? ";
            $params[] = $start;
        }
        if ($end) {
            $and .= " AND IntervalEndDate <= ? ";
            $params[] = $end;
        }

        $query = "DROP TABLE IF EXISTS IntervalTemp";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE IntervalTemp SELECT i.* FROM intervals_daily i WHERE AccountNumber IN ({$questions}) AND IntervalType = ?";
        $adb->pquery($query, array($accounts, $intervalType));

//        $query = "SELECT DATE_FORMAT(IntervalEndDate, '%m-%d-%Y') AS IntervalEndDate, SUM(IntervalEndValue) AS IntervalEndValue, SUM(NetFlowAmount) AS NetFlowAmount, SUM(IntervalEndValue) - (SUM(IntervalBeginValue) + SUM(NetFlowAmount)) AS InvestmentReturn
        $query = "SELECT DATE_FORMAT(IntervalEndDate, '%m-%d-%Y') AS IntervalEndDate, SUM(IntervalEndValue) AS IntervalEndValue, SUM(NetFlowAmount) AS NetFlowAmount, SUM(IntervalEndValue) - SUM(NetFlowAmount) - SUM(IntervalBeginValue) AS InvestmentReturn, (SUM(IntervalEndValue) - SUM(NetFlowAmount) - SUM(IntervalBeginValue)) / SUM(IntervalBeginValue) * 100 AS periodreturn
                  FROM IntervalTemp
                  WHERE AccountNumber IN ({$questions}) {$and} GROUP BY IntervalEndDate";
        $result = $adb->pquery($query, $params);
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $intervals[] = array(
                    "end_date" => $v['intervalenddate'],
                    "end_value" => $v['intervalendvalue'],
                    "net_flow" => $v['netflowamount'],
                    "investment_return" => $v['investmentreturn'],
                    "period_return" => $v['periodreturn']);
            }
            return $intervals;
        }
        return 0;
    }

    static public function CreateIntervalTempTable($accounts, $start = null, $end = null, &$and, $intervaltype)
    {
        global $adb;
        $params = array();
        $and = " AND intervaltype = '{$intervaltype}' ";
        $questions = generateQuestionMarks($accounts);
        $params[] = $accounts;

        if ($start) {
            $and .= " AND IntervalEndDate >= ? ";
            $params[] = $start;
        }
        if ($end) {
            $and .= " AND IntervalEndDate <= ? ";
            $params[] = $end;
        }

        $query = "DROP TABLE IF EXISTS IntervalTemp";
        $adb->pquery($query, array());

        $query = "DROP TABLE IF EXISTS HitAgainst";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE IntervalTemp SELECT i.* FROM Intervals_daily i WHERE AccountNumber IN ({$questions}) {$and} ";
        $adb->pquery($query, $params);

        $query = "CREATE TEMPORARY TABLE HitAgainst SELECT * FROM IntervalTemp";
        $adb->pquery($query, array());

        return $params;
    }

    static private function GetIntervalBeginValue()
    {
        global $adb;
        $query = "SELECT SUM(IntervalBeginValue) AS BeginValue, DATE_FORMAT(IntervalBeginDate, '%m-%d-%Y') AS IntervalBeginDate FROM IntervalTemp WHERE IntervalEndDate = (SELECT MIN(IntervalEndDate) FROM HitAgainst)";
        $result = $adb->pquery($query, array());
        $begin = array("begin_value" => $adb->query_result($result, 0, 'beginvalue'),
            "begin_date" => $adb->query_result($result, 0, 'intervalbegindate'));
        return $begin;
    }

    static private function GetFlowAmount()
    {
        global $adb;
        $query = "SELECT SUM(NetFlowAmount) AS netflowamount FROM IntervalTemp";
        $result = $adb->pquery($query, array());
        return $adb->query_result($result, 0, 'netflowamount');
    }

    static private function GetInvestmentReturn()
    {
        return (self::GetIntervalEndValue()['end_value'] - self::GetFlowAmount() - self::GetIntervalBeginValue()['begin_value']);
        /*      FLAW WITH THE LOGIC BELOW... It is summing all of the begin and end values in the table when we need the start and end dates only
                global $adb;
                $query = "SELECT SUM(IntervalEndValue) - SUM(NetFlowAmount) - SUM(IntervalBeginValue) AS InvestmentReturn FROM IntervalTemp";
                $result = $adb->pquery($query, array());
                $r = $adb->query_result($result, 0, 'investmentreturn');
                echo $r;exit;*/
    }

    static private function GetIntervalEndValue()
    {
        global $adb;
        $query = "SELECT SUM(IntervalEndValue) AS EndValue, DATE_FORMAT(IntervalEndDate, '%m-%d-%Y') AS IntervalEndDate FROM IntervalTemp WHERE IntervalEndDate = (SELECT MAX(IntervalEndDate) FROM HitAgainst)";
        $result = $adb->pquery($query, array());
        $end = array("end_value" => $adb->query_result($result, 0, 'endvalue'),
            "end_date" => $adb->query_result($result, 0, 'intervalenddate'));
        return $end;
    }

    static public function GetSummerizedIntervalInfo(array $accounts, $start = null, $end = null, $intervaltype = 'monthly')
    {
        $unused = null;
        self::CreateIntervalTempTable($accounts, $start, $end,$unused, $intervaltype);

        $begin = self::GetIntervalBeginValue();
        $flow = self::GetFlowAmount();
        $investment = self::GetInvestmentReturn();
        $end = self::GetIntervalEndValue();

        $summary = array("begin_value" => $begin['begin_value'],
            "begin_date" => $begin['begin_date'],
            "flow_value" => $flow,
            "investment_return_value" => $investment,
            "end_value" => $end['end_value'],
            "end_date" => $end['end_date']);
        return $summary;
    }

    static public function ReturnValidAccountsFromArray(array $accounts)
    {
        global $adb;
        $account_numbers = array();
        $params = array();
        $params[] = $accounts;
        $questions = generateQuestionMarks($accounts);
        $query = "SELECT account_number FROM vtiger_portfolioinformation WHERE account_number IN ({$questions}) AND accountclosed=0";
        $result = $adb->pquery($query, $params);
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $account_numbers[] = $v['account_number'];
            }
            return $account_numbers;
        }
        return 0;
    }

    static public function RemoveMonthlyIntervals(array $accounts){
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $query = "DELETE FROM intervals_daily WHERE AccountNumber IN ({$questions}) AND IntervalType = 'Monthly'";
        $adb->pquery($query, array($accounts));
    }

    static public function RemoveIntervals(array $accounts, $start = null, $end = null){
        global $adb;
        $and = "";
        $params = array();
        $params[] = $accounts;
        if($start != null){
            $and .= " AND intervalenddate >= ? ";
            $params[] = $start;
        }
        if($start != null){
            $and .= " AND intervalenddate <= ? ";
            $params[] = $end;
        }

        $questions = generateQuestionMarks($accounts);
        $query = "DELETE FROM intervals_daily WHERE AccountNumber IN ({$questions}) {$and}";
        $adb->pquery($query, $params);

        $query = "DELETE FROM vtiger_interval_calculations WHERE account_number IN ({$questions})";
        $adb->pquery($query, array($accounts));
    }

    static public function CalculateMonthlyIntervalsForAccounts(array $accounts, $start = null, $end = null)
    {
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        if (!$start)
            $start = '1900-01-01';
        if (!$end)
            $end = date("Y-m-d");

        foreach ($accounts AS $k => $v) {
            $custodian = PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($v);
            $query = "CALL CALCULATE_MONTHLY_INTERVALS_LOOP(?, ?, ?, ?, ?)";
#            CALL CALCULATE_MONTHLY_INTERVALS_LOOP("34300882", "1900-01-01", "2017-10-12", "schwab", {$db_name});
            $adb->pquery($query, array($v, $start, $end, $custodian, $db_name));
        }
    }

    static public function UpdateIntervalFlow($uid, $value){
        global $adb;

        $query = "UPDATE intervals_daily 
                  SET NetFlowAmount = ? 
                  WHERE uid = ?";
        $adb->pquery($query, array($value, $uid), 'true');
    }

    static public function UpdateIntervalsFixMe($account_number, $value, $uid){
        global $adb;

        $query = "UPDATE intervals_daily 
                  SET fixme = ? 
                  WHERE accountnumber = ?
                  AND uid = ?";
        $adb->pquery($query, array($value, $account_number, $uid), 'true');
    }

    static public function SetTransactionTradeDatesByTypeBeforeDate($account_number, $date, $types){
        global $adb;
        $params = array();
        $params[] = $date;
        $params[] = $account_number;
        $params[] = $types;
        $params[] = $date;
        $questions = generateQuestionMarks($types);

        $query = "UPDATE vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  SET t.proper_date = t.trade_date, t.trade_date = ?
                  WHERE account_number = ?
                  AND transaction_type IN ({$questions})
                  AND trade_date < ?
                  AND e.deleted = 0";
        $adb->pquery($query, $params, true);
    }

    /**
     * Get all transaction values summed of type passed in BEFORE the passed in date
     * @param $account_number
     * @param $typesfGetTransactionValuesByTypeBeforeDate
     */
    static public function GetTransactionValuesByTypeBeforeDate($account_number, $date, $types){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($types);

        $query = "SELECT * FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  WHERE account_number = ?
                  AND transaction_type IN ({$questions})
                  AND trade_date < ?
                  AND e.deleted = 0";
        $params[] = $account_number;
        $params[] = $types;
        $params[] = $date;

        $value = 0;
        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while ($t = $adb->fetchByAssoc($result)) {
                $val = $t['operation'] . $t['net_amount'];
                $value += $val;
            }
        }
        return $value;
    }

    static public function GetFirstIntervalEndDate($account_number){
        global $adb;
        $query = "SELECT IntervalEndDate 
                  FROM intervals_daily 
                  WHERE AccountNumber = ? 
                  ORDER BY intervalenddate ASC LIMIT 1";
        $result = $adb->pquery($query, array($account_number), 'true');
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'intervalenddate');
        }
        return false;
    }

    static public function CreateReconciliationTransactionFromBeginningValueIntervals($account_number){
        global $adb;

        $query = "SELECT * 
                  FROM intervals_daily 
                  WHERE AccountNumber = ? 
                  AND intervalbeginvalue = 0 
                  AND netflowamount != intervalendvalue
                  AND IntervalEndValue != 0
                  ORDER BY intervalenddate ASC";
        $result = $adb->pquery($query, array($account_number), 'true');

        $first_date = self::GetFirstIntervalEndDate($account_number);

        //Check for transactions before the very first date that are flows or expenses and return their value
        $before_value = self::GetTransactionValuesByTypeBeforeDate($account_number, $first_date, array('flow','expense'));

        if($adb->num_rows($result) > 0){
            self::SetTransactionTradeDatesByTypeBeforeDate($account_number, $first_date, array('flow', 'expense'));
            while ($t = $adb->fetchByAssoc($result)) {
                $begin_value = $t['intervalbeginvalue'];
                $end_value = $t['intervalendvalue'];
                $net_flow = $t['netflowamount'];
                $uid = $t['uid'];
                $transaction_amount = $end_value - $net_flow - $before_value;
                echo "Account: " . $account_number . '<br />';
                echo "END: " . $end_value . '<br />';
                echo "Net: " . $net_flow . '<br />';
                echo "Before: " . $before_value . '<br />';
                echo 'Transaction Amount: ' . $transaction_amount  . '<br />';
                echo 'Date entering for: ' . $t['intervalenddate'] . '<br />' . '<br />';
#exit;
                if($begin_value == 0 && $end_value != 0 && $transaction_amount != 0){
                    $transaction_amount = $end_value - $net_flow - $before_value;
//                    echo "creating transaction for $" . $transaction_amount . ' - Account #' . $account_number . '<br />';
                    $record = Vtiger_Record_Model::getCleanInstance("Transactions");
                    $data = $record->getData();
                    $data['security_symbol'] = 'CRMRECON';
                    $data['description'] = 'System Generated Reconciliation Transaction (b)';
                    $data['account_number'] = $account_number;
                    $data['quantity'] = $transaction_amount;
                    $data['net_amount'] = $transaction_amount;
                    $data['transaction_type'] = 'Flow';
                    $data['trade_date'] = $t['intervalenddate'];
                    $data['transaction_activity'] = 'Reconciliation Transaction';
                    $data['system_generated'] = 1;
                    if($transaction_amount < 0)
                        $data['operation'] = '-';
                    $record->set('mode','create');
                    $record->setData($data);
                    $record->save();
                    self::UpdateIntervalFlow($uid, $transaction_amount + $net_flow);
                }
            }
        }
    }

    static public function CreateReconciliationTransactionFromEndValueIntervals($account_number){
        global $adb;

        $query = "SELECT * 
                      FROM intervals_daily 
                      WHERE AccountNumber = ? 
                      AND IntervalEndValue = 0 
                      AND intervalbeginvalue != 0
                      AND netflowamount != intervalbeginvalue";
        $result = $adb->pquery($query, array($account_number), 'true');

        if($adb->num_rows($result) > 0){
            while ($t = $adb->fetchByAssoc($result)) {
                $begin_value = $t['intervalbeginvalue'];
                $end_value = $t['intervalendvalue'];
                $net_flow = $t['netflowamount'];
                $uid = $t['uid'];
                if($end_value == 0 && $begin_value != 0){
                    $transaction_amount = ($begin_value + $net_flow)*-1;

                    $record = Vtiger_Record_Model::getCleanInstance("Transactions");
                    $data = $record->getData();
                    $data['security_symbol'] = 'CRMRECON';
                    $data['description'] = 'System Generated Reconciliation Transaction (e)';
                    $data['account_number'] = $account_number;
                    $data['quantity'] = $transaction_amount;
                    $data['net_amount'] = $transaction_amount;
                    $data['transaction_type'] = 'Flow';
                    $data['trade_date'] = $t['intervalenddate'];
                    $data['transaction_activity'] = 'Reconciliation Transaction';
                    $data['system_generated'] = 1;
                    if($transaction_amount < 0)
                        $data['operation'] = '-';
                    $record->set('mode','create');
                    $record->setData($data);
                    $record->save();
                    self::UpdateIntervalFlow($uid, $transaction_amount);
                }
            }
        }
    }

    /**
     * Returns the last date calculated.  2010-01-01 if never calculated
     * @param $account_number
     * @return string
     */
    static public function AutoDetermineIntervalCalculationDates($account_number){
        global $adb;
        $tmp = self::GetCustodianFromAccountNumber($account_number);
        $tmpClass = "c".$tmp."Portfolios";
        if (class_exists($tmpClass)) {
            $earliest_balance_date = $tmpClass::GetEarliestBalanceAndDate(array($account_number));
            $earliest_interval = self::GetEarliestIntervalDateForAccount($account_number);

            if($earliest_interval > $earliest_balance_date[$account_number]['as_of_date']){
                return $earliest_balance_date[$account_number]['as_of_date'];//We need to recalculate based on the earliest balance!
            }
        }

        $query = "SELECT last_calculated FROM vtiger_interval_calculations WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result)> 0){
            while ($v = $adb->fetchByAssoc($result)) {
                return $v['last_calculated'];
            }
        }
        return '2010-01-01';
    }

    static public function UpdateIntervalCalculationDate($account_number){
        global $adb;
        $query = "INSERT INTO vtiger_interval_calculations (account_number, last_calculated)
                  SELECT AccountNumber, MAX(IntervalEndDate)
                  FROM intervals_daily WHERE AccountNumber = ?
                  ON DUPLICATE KEY UPDATE last_calculated = VALUES(last_calculated)";
        $adb->pquery($query, array($account_number));
    }

    /**
     * Returns the number of intervals an account has calculated
     * @param $account_number
     * @return int|string|string[]|null
     * @throws Exception
     */
    static public function GetNumberOfAccountIntervals($account_number){
        global $adb;
        $query = "SELECT COUNT(*) as count FROM intervals_daily WHERE accountnumber = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "count");
        }
        return 0;
    }

    /**
     * Returns the earliest date available for passed in account number that is in intervals_daily.  If nothing, it returns '2010-01-01'
     * @param $account_number
     * @return string|string[]|null
     * @throws Exception
     */
    static public function GetEarliestIntervalDateForAccount($account_number){
        global $adb;
        $query = "SELECT intervalenddate 
                  FROM intervals_daily 
                  WHERE accountnumber = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "intervalenddate");
        }
        return '2010-01-01';
    }

    /**
     * If auto is set to true, the start date will be ignored and auto determined from when the laster interval was run
     * @param array $accounts
     * @param null $start
     * @param null $end
     * @param bool $auto
     */
    static public function CalculateDailyIntervalsForAccounts(array $accounts, $start = null, $end = null, $auto=false)
    {
        foreach($accounts AS $k => $v){
            $intervals = new cIntervals($v);
            $intervals->CalculateIntervals('1900-01-01', date("Y-m-d"));
        }
        return;
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        if (!$start)
            $start = '1900-01-01';
        if (!$end)
            $end = date("Y-m-d");

        foreach ($accounts AS $k => $v) {
            if($auto == true){
                $start = self::AutoDetermineIntervalCalculationDates($v);
                $end = date("Y-m-d");//If auto is on, but start/end dates were provided, we override those dates
            }

            $custodian = PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($v);

            /*
            1) Get the first balance date/value
            2) Get the first transaction date for the account (do nothing with it yet)
            3) Is first transaction trade date < balance start date?
                        #3a) No, we're good to go, do nothing else
                        #3b) Yes, proceed with 4
            4) Get the sum of all transaction flows before the balance date
             4a) Get sum of all transaction flows ON the balance date (needed to subtract from final balance total)
            5) Balance = Balance_value(#1) - transaction_flow_on_first_day_value (#4a) - transaction_expense_value
            5a) Transaction amount = Balance amount (#5) - Transaction Flows (#4)
            6) Does 5a = 0?
             6a) Yes, #7
             6b) No, we need to create a transaction for the first transaction date (#2)
            7) Create new balance in cloud using transaction date (#2) using the balance amount
            */

            //#1
            $first_balance = self::GetFirstBalanceInfo($v, $custodian);
            /*            echo "Balance Info: " . "<br />";
                        print_r($first_balance);
                        echo "<br />";*/

            //#2  #intervals as excel sheet, remove monthly intervals from menu
            $transaction_date = self::GetFirstTransactionDate($v);

            //#3
            if($transaction_date < $first_balance['date'] AND $transaction_date != null) {
                #4
                $transaction_flow_value = self::GetTransactionValuesByTypeOnDate($v, $first_balance['date'], array("flow"), true);
                $transaction_expense_value = self::GetTransactionValuesByTypeOnDate($v, $first_balance['date'], array("expense"), true);

                $transaction_flow_on_first_day_value = self::GetTransactionValuesByTypeOnDate($v, $first_balance['date'], array("flow"), false);
                $transaction_expenses_on_first_day_value = self::GetTransactionValuesByTypeOnDate($v, $first_balance['date'], array("expense"), false);
                /*
                                echo "Flow before balance date: $" . $transaction_flow_value . '<br />';
                                echo "Expense before balance date: $" . $transaction_expense_value . '<br /><br />';

                                echo "Transaction Flows on balance date: $" . $transaction_flow_on_first_day_value . '<br />';
                                echo "Transaction Expenses on balance date: $" . $transaction_expenses_on_first_day_value . '<br />';
                */
                #5
                $bamount = $first_balance['value'] - $transaction_flow_on_first_day_value - $transaction_expense_value;//Calculated balance is first known balance - flows for that day
                #5a                                                                       //(If no flows that day, it is the same number)
                $tamount = $bamount - $transaction_flow_value;

#                echo "<br /><br />" . "Balance Amount to insert: " . $bamount . '<br />';
#                echo "Transaction amount to insert: " . $tamount . '<br />';
                #6
                if($tamount != 0) {#6b
//                    echo "Need to create a balance for {$custodian}, #{$v} for $" . $bamount . ' on ' . $transaction_date;

                    //$custodian, $account_number, $balance, $date
                    $record = Vtiger_Record_Model::getCleanInstance("Transactions");
                    $data = $record->getData();
                    $data['security_symbol'] = 'CRMRECON';
                    $data['description'] = 'System Generated Reconciliation Transaction (e)';
                    $data['account_number'] = $v;
                    $data['quantity'] = ABS($tamount);
                    $data['net_amount'] = ABS($tamount);
                    $data['transaction_type'] = 'Flow';
                    $data['trade_date'] = $transaction_date;
                    $data['transaction_activity'] = 'Reconciliation Transaction';
                    $data['system_generated'] = 1;
                    if($tamount < 0)
                        $data['operation'] = '-';
                    $record->set('mode','create');
                    $record->setData($data);
                    $record->save();
                }

                #7
                self::CreateBalanceInCustodian("custodian_omniscient", $custodian, $v, $bamount, $transaction_date);
            }

            $params = array($v, $start, $end, $custodian, $db_name);
            $query = "CALL CALCULATE_DAILY_INTERVALS_LOOP(?, ?, ?, ?, ?)";
            $adb->pquery($query, $params, 'true');
//            self::CreateReconciliationTransactionFromBeginningValueIntervals($v);

            if($auto == true)//We only want to update with the latest date possible, auto guarantees us this
                self::UpdateIntervalCalculationDate($v);


//            self::CreateReconciliationTransactionFromEndValueIntervals($v);
            /*Old way of calculating intervals was monthly.. we have now moved to daily*/
#            CALL CALCULATE_MONTHLY_INTERVALS_LOOP("34300882", "1900-01-01", "2017-10-12", "schwab", "live_omniscient");
        }
    }

    static public function GetIntervalsForAccounts(array $accounts, $start = null, $end = null){
        global $adb;
        $and = "";
        $questions = generateQuestionMarks($accounts);
        $params = array();
#        $params = self::CreateIntervalTempTable($accounts, $start, $end, $and);

        $query = "CALL MONTHLY_INTERVALS_CALCULATED(\"{$questions}\")";
        $adb->pquery($query, array($accounts));

        if (strlen($start) > 0 && strlen($end) > 0) {
            $where = " WHERE IntervalEndDate >= ? AND IntervalEndDate <= ? ";
            $params[] = $start;
            $params[] = $end;
        }
        $query = "SELECT AccountNumber, DATE_FORMAT(IntervalBeginDate, '%m-%d-%Y') AS IntervalBeginDateFormatted, 
                         DATE_FORMAT(IntervalEndDate, '%m-%d-%Y') AS IntervalEndDateFormatted, IntervalBeginValue, IntervalEndValue, NetFlowAmount, 
                         InvestmentReturn, periodreturn 
                  FROM calculated_intervals {$where} 
                  GROUP BY IntervalEndDate 
                  ORDER BY IntervalEndDate DESC";
        $result = $adb->pquery($query, $params);

        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $intervals[] = array("account_number" => $v['accountnumber'],
                    "begin_date" => $v['intervalbegindateformatted'],
                    "end_date" => $v['intervalenddateformatted'],
                    "begin_value" => $v['intervalbeginvalue'],
                    "end_value" => $v['intervalendvalue'],
                    "net_flow" => $v['netflowamount'],
                    "period_return" => $v['periodreturn'],
                    "investment_return" => $v['investmentreturn']);
            }
            return $intervals;
        }

        return 0;
    }

    static public function GetDailyIntervalsForAccountsWithDateFilter(array $accounts, $start = null, $end = null)
    {
        global $adb;
        $and = "";
        $questions = generateQuestionMarks($accounts);
        $params = array();
        $where = "WHERE AccountNumber IN ({$questions}) ";
        $params[] = $accounts;
        if (strlen($start) > 0 && strlen($end) > 0) {
            $where .= " AND IntervalEndDate >= ? AND IntervalEndDate <= ? ";
            $params[] = $start;
            $params[] = $end;
        }

        $where .= " AND IntervalType = 'Daily' ";
        $query = "SELECT AccountNumber, DATE_FORMAT(IntervalBeginDate, '%m-%d-%Y') AS IntervalBeginDateFormatted, 
                         DATE_FORMAT(IntervalEndDate, '%m-%d-%Y') AS IntervalEndDateFormatted, IntervalBeginValue, IntervalEndValue, NetFlowAmount, 
                         InvestmentReturn, (SUM(IntervalEndValue) - SUM(NetFlowAmount) - SUM(IntervalBeginValue)) / SUM(IntervalBeginValue) * 100 AS periodreturn 
                  FROM intervals_daily {$where} 
                  GROUP BY IntervalEndDate 
                  ORDER BY IntervalEndDate DESC";
        $result = $adb->pquery($query, $params);

        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $intervals[] = array("account_number" => $v['accountnumber'],
                    "begin_date" => $v['intervalbegindateformatted'],
                    "end_date" => $v['intervalenddateformatted'],
                    "begin_value" => $v['intervalbeginvalue'],
                    "end_value" => $v['intervalendvalue'],
                    "net_flow" => $v['netflowamount'],
                    "period_return" => $v['periodreturn'],
                    "investment_return" => $v['investmentreturn']);
            }
            return $intervals;
        }

        return 0;
    }

    static public function GetDailyIntervalsForAccountsPreCalculated(array $accounts, $start_date, $end_date){
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $query = "SELECT SUM(intervalEndValue) / (SUM(intervalBeginValue) + (SUM(NetFlowAmount) + SUM(expenseamount))) AS netreturnamount, 
                     SUM(investmentreturn) AS investmentreturn, IntervalEndDate,
                     SUM(intervalBeginValue) AS intervalBeginValue, SUM(intervalEndValue) AS intervalEndValue,
                     SUM(NetFlowAmount) AS netflowamount,
                     SUM(expenseamount) AS expenseamount,
                     SUM(incomeamount) AS incomeamount
              FROM intervals_daily 
              WHERE AccountNumber IN ({$questions}) 
              AND IntervalEndDate BETWEEN ? AND ?
              GROUP BY intervalEndDate";

        $twr = 1;
        $result = $adb->pquery($query, array($accounts, $start_date, $end_date));

        if ($adb->num_rows($result) > 0) {
            while ($x = $adb->fetchByAssoc($result)) {
                if($x['netreturnamount'] == 0 || is_null($x['netreturnamount']))
                    $x['netreturnamount'] = 1;

                if ($x['netreturnamount'] != 1) {
                    $twr *= $x['netreturnamount'];
#                    echo $x['intervalenddate'] . '... ' . $x['intervalbeginvalue'] . ' - ' . $x['netflowamount'] . ' - ' . $x['incomeamount'] . ' - ' . $x['expenseamount'] . ' - ' . $x['investmentreturn'] . ' - ' . $x['intervalendvalue'] . ' - ' . (($x['netreturnamount'] - 1) * 100) . ' -- ' . ($twr - 1) * 100 . '<br />';
#                    echo $twr . ' - ' . $x['intervalenddate'] . ' = ' . ($twr-1)*100 . '(investment return - ' . $x['investmentreturn'] . ')<br />';
                } else
                    $twr *= $x['netreturnamount'];
                $intervals[] = array("account_number" => $x['accountnumber'],
                    "begin_date" => date("m-d-Y", strtotime($x['intervalbegindate'])),
                    "end_date" => date("m-d-Y", strtotime($x['intervalenddate'])),
                    "begin_value" => $x['intervalbeginvalue'],
                    "end_value" => $x['intervalendvalue'],
                    "net_flow" => $x['netflowamount'],
                    "net_return" => $x['netreturnamount'],
                    "expense_amount" => $x['expenseamount'],
                    "incomeamount" => $x['incomeamount'],
                    "investmentreturn" => $x['investmentreturn'],
                    "net_return_percent" => ($x['netreturnamount']-1) * 100,
                    "twr" => ($twr - 1) * 100);
            }
        }

        return array_reverse($intervals);
/*
        global $adb;
        $twr = 1;

        $questions = generateQuestionMarks($accounts);
#        $query = "CALL CALCULATE_MONTHLY_INTERVALS_FROM_DAILY_COMBINED(\"{$questions}\", ?, ?)";
        $query = "CALL CALCULATE_INTERVALS_FROM_DAILY_COMBINED(\"{$questions}\", ?, ?)";
//        $query = "CALL TWR_INTERVALS_CALCULATED(\"{$questions}\", ?, ?)";
        $adb->pquery($query, array($accounts, $start_date, $end_date), true);

        $query = "SELECT * FROM tmpDailyPreTWR ORDER BY intervalenddate ASC";
        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            $twr = 1;
            while ($v = $adb->fetchByAssoc($result)) {
                $twr = $twr * $v['netreturnamount'];
                $intervals[] = array("account_number" => $v['accountnumber'],
                    "begin_date" => date("m-d-Y", strtotime($v['intervalbegindate'])),
                    "end_date" => date("m-d-Y", strtotime($v['intervalenddate'])),
                    "begin_value" => $v['intervalbeginvalue'],
                    "end_value" => $v['intervalendvalue'],
                    "net_flow" => $v['netflowamount'],
                    "net_return" => $v['netreturnamount'],
                    "gross_return" => $v['grossreturnamount'],
                    "expense_amount" => $v['expenseamount'],
                    "incomeamount" => $v['incomeamount'],
                    "journalamount" => $v['journalamount'],
                    "tradeamount" => $v['tradeamount'],
                    "investmentreturn" => $v['investmentreturn'],
                    "net_return_percent" => ($v['netreturnamount']-1) * 100,
                    "twr" => ($twr - 1) * 100);
            }
            return array_reverse($intervals);
        }*/
    }

    static public function GetDailyIntervalsForAccounts(array $accounts)
    {
        global $adb;
        $questions = generateQuestionMarks($accounts);
        $params = array();
        $params[] = $accounts;

        $query = "SELECT intervalbegindate, 
                  intervalenddate, 
                  DATE_FORMAT(intervalbegindate, '%m-%d-%Y') AS intervalbegindate_formatted, 
                  DATE_FORMAT(intervalenddate, '%m-%d-%Y') AS intervalenddate_formatted, 
                  SUM(intervalbeginvalue) AS intervalbeginvalue, 
                  SUM(intervalendvalue) AS intervalendvalue, 
                  SUM(netflowamount) AS netflowamount, 
                  netreturnamount, 
                  SUM(grossreturnamount) AS grossreturnamount, 
                  SUM(expenseamount) AS expenseamount, 
                  SUM(incomeamount) AS incomeamount, journalamount, tradeamount, 
                  SUM(investmentreturn) AS investmentreturn
                  FROM intervals_daily 
                  WHERE AccountNumber IN ({$questions}) AND IntervalType = 'daily'
                  GROUP BY intervalenddate 
                  ORDER BY intervalenddate DESC";
        $result = $adb->pquery($query, $params);

        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $intervals[] = array("account_number" => $v['accountnumber'],
                    "begin_date" => $v['intervalbegindate_formatted'],
                    "end_date" => $v['intervalenddate_formatted'],
                    "begin_value" => $v['intervalbeginvalue'],
                    "end_value" => $v['intervalendvalue'],
                    "net_flow" => $v['netflowamount'],
                    "net_return" => $v['netreturnamount'],
                    "gross_return" => $v['grossreturnamount'],
                    "expense_amount" => $v['expenseamount'],
                    "incomeamount" => $v['incomeamount'],
                    "journalamount" => $v['journalamount'],
                    "tradeamount" => $v['tradeamount'],
                    "investmentreturn" => $v['investmentreturn']);
            }
            return $intervals;
        }

        return 0;
    }


    static public function GetMonthlyIntervalDatesStartDate(&$start_date, &$end_date)
    {
        global $adb;
        if (strlen($start_date) > 0 && strlen($end_date) > 0) {
            $query = "SELECT MIN(IntervalBeginDate) AS IntervalBeginDate, MAX(IntervalEndDate) AS IntervalEndDate  
                      FROM CALCULATED_INTERVALS 
                      WHERE IntervalEndDate >= ? 
                      AND IntervalEndDate <= ?";
            $result = $adb->pquery($query, array($start_date, $end_date));
            if ($adb->num_rows($result) > 0) {
                $start_date = $adb->query_result($result, 0, 'intervalbegindate');
                $end_date = $adb->query_result($result, 0, 'intervalenddate');
                return;
            }
        }
        $start_date = 0;
        $end_date = 0;
    }

    function getTop10AUMPortfolios($headerColumns)
    {

        $db = PearDatabase::getInstance();

        $moduleName = $this->getName();

        $currentUserModel = Users_Record_Model::getCurrentUserModel();

        $queryGenerator = new QueryGenerator($moduleName, $currentUserModel);

        $headerColumns = array_merge($headerColumns, array("id"));

        $queryGenerator->setFields($headerColumns);

        $listviewController = new ListViewController($db, $currentUserModel, $queryGenerator);

        if (in_array('total_value', $headerColumns))
            $queryGenerator->addCondition("total_value", "", "ny");

        $query = $queryGenerator->getQuery();

        $query .= " AND DATE_FORMAT( vtiger_crmentity.modifiedtime, '%Y-%m-%d' ) <= DATE_FORMAT(NOW() - INTERVAL 1 DAY, '%Y-%m-%d')";

        $query .= ' ORDER BY vtiger_portfolioinformation.total_value DESC LIMIT 0, 10';

        $query = str_replace(" FROM ", ",vtiger_crmentity.crmid as id FROM ", $query);

        $result = $db->pquery($query, array());

        $moduleFocus = CRMEntity::getInstance($moduleName);

        $entries = $listviewController->getListViewRecords($moduleFocus, $moduleName, $result);

        $listviewRecords = array();
        $index = 0;
        foreach ($entries as $id => $record) {
            $rawData = $db->query_result_rowdata($result, $index++);
            $record['id'] = $id;
            $listviewRecords[$id] = $this->getRecordFromArray($record, $rawData);
        }

        return $listviewRecords;
    }

    function getTop10RevenuePortfolios($headerColumns)
    {

        $db = PearDatabase::getInstance();

        $moduleName = $this->getName();

        $currentUserModel = Users_Record_Model::getCurrentUserModel();

        $queryGenerator = new QueryGenerator($moduleName, $currentUserModel);

        $headerColumns = array_merge($headerColumns, array("id"));

        $queryGenerator->setFields($headerColumns);

        $listviewController = new ListViewController($db, $currentUserModel, $queryGenerator);

        $query = $queryGenerator->getQuery();

        $query .= " AND vtiger_transactionscf.transaction_type = 'Expense' AND vtiger_transactionscf.transaction_activity = 'Management fee'";

        $query .= ' GROUP BY vtiger_transactions.account_number ORDER BY annual_management_fee DESC LIMIT 0, 10';

        $query = str_replace(" FROM vtiger_portfolioinformation", ",vtiger_crmentity.crmid as id, 
		SUM(vtiger_transactionscf.net_amount) as annual_management_fee FROM vtiger_portfolioinformation
		INNER JOIN vtiger_transactions ON vtiger_transactions.account_number = vtiger_portfolioinformation.account_number 
		INNER JOIN vtiger_transactionscf ON vtiger_transactions.transactionsid = vtiger_transactionscf.transactionsid ", $query);

        $result = $db->pquery($query, array());

        $moduleFocus = CRMEntity::getInstance($moduleName);

        $entries = $listviewController->getListViewRecords($moduleFocus, $moduleName, $result);

        $listviewRecords = array();
        $index = 0;
        foreach ($entries as $id => $record) {
            $rawData = $db->query_result_rowdata($result, $index++);
            $record['id'] = $id;
            $listviewRecords[$id] = $this->getRecordFromArray($record, $rawData);
        }

        return $listviewRecords;
    }

    static public function GetActivityPicklistValues()
    {
        global $adb;
        $query = "SELECT transaction_activity FROM vtiger_transaction_activity";
        $result = $adb->pquery($query, array());
        $activities = array();
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $activities[] = $v['transaction_activity'];
            }
            return $activities;
        }
        return 0;
    }

    static public function GetMappedAccountType($custodian, $type)
    {
        global $adb;

        $query = "SELECT account_type FROM vtiger_accounttype_mapping WHERE custodian = ? AND custodian_account_type = ?";
        $result = $adb->pquery($query, array($custodian, $type));
        if ($adb->num_rows($result) > 0) {
            return $adb->query_result($result, 0, 'account_type');
        }
        return 0;
    }

    /**
     * Update the TD portfolio type based on mapping.
     * The account type code is fed into Omniscient VIA the API and is NOT in the cloud, so omniscient itself is responsible for filling this in.
     */
    static public function UpdatePortfolioTypeTDOnly()
    {return;
        global $adb;
        $query = "UPDATE vtiger_portfolioinformation p
	             JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
	             JOIN custodian_omniscient.custodian_portfolios_td ptd ON ptd.account_number = p.account_number
	             JOIN portfolios_mapping_td map ON ptd.account_type = map.td_type 	             
	             SET cf.cf_2549 = CASE WHEN map.omniscient_type != '' AND map.omniscent_type IS NOT NULL THEN map.omniscient_type ELSE ptd.account_type END
	             WHERE origination = 'TD'";
        $adb->pquery($query, array());
    }

    static public function UpdatePortfolioTDInfo()
    {
        global $adb;
        $trade = new Trading_Ameritrade_Model();
        $increment = 300;
        $counter = 1;
        $x = 1;
        $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, 1, 1);
        $max = $tmp['model']['getAccountsJson']['responseInfo']['totalSize'];
        $query = "UPDATE vtiger_portfolioinformation 
                  JOIN vtiger_portfolioinformationcf USING (portfolioinformationid) 
                  SET custodian_inception = ?, production_number = ?, accountclosed = ?, account_type_code = ?
                  WHERE account_number = ?";
        while ($counter < $max) {
            $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, $counter, $counter + $increment);
            foreach ($tmp['model']['getAccountsJson']['account'] AS $k => $v) {
                if ($v['accountStatus'] == 'CLOSED')
                    $closed = 1;
                else
                    $closed = 0;

                $params = array($v['dateOpened'], $v['repCode'], $closed, $v['accountCategoryCode'], $v['accountNumber']);
                $adb->pquery($query, $params);
            }
            $counter += $increment;
        }
    }

    static public function UpdatePortfolioTDInfoBackup()//The new version above removes accountType so it can be handled by Java
    {
        global $adb;
        $trade = new Trading_Ameritrade_Model();
        $increment = 300;
        $counter = 1;
        $x = 1;
        $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, 1, 1);
        $max = $tmp['model']['getAccountsJson']['responseInfo']['totalSize'];
        $query = "UPDATE vtiger_portfolioinformation 
                  JOIN vtiger_portfolioinformationcf USING (portfolioinformationid) 
                  SET custodian_inception = ?, production_number = ?, account_type = ?, accountclosed = ?, account_type_code = ?
                  WHERE account_number = ?";
        while ($counter < $max) {
            $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, $counter, $counter + $increment);
            foreach ($tmp['model']['getAccountsJson']['account'] AS $k => $v) {
                if ($v['accountStatus'] == 'CLOSED')
                    $closed = 1;
                else
                    $closed = 0;

                $params = array($v['dateOpened'], $v['repCode'], $v['accountType'], $closed, $v['accountCategoryCode'], $v['accountNumber']);
                $adb->pquery($query, $params);
            }
            $counter += $increment;
        }
    }

    static public function AuditTDRepCodes()
    {
        global $adb;
        $trade = new Trading_Ameritrade_Model();
        $increment = 300;
        $counter = 1;

        $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, 1, 1);
        $max = $tmp['model']['getAccountsJson']['responseInfo']['totalSize'];

        $accounts = array();
        $reps = array();
        while ($counter < $max) {
            $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, $counter, $counter + $increment);
            foreach ($tmp['model']['getAccountsJson']['account'] AS $k => $v) {
                $accounts[$v['accountNumber']]['rep_code'] = $v['repCode'];
                $accounts[$v['accountNumber']]['status'] = $v['accountStatus'];
                $reps[$v['repCode']] += 1;
            }
            $counter += $increment;
        }

        $query = "INSERT INTO rep_code_counts (rep_code, account_count, custodian) VALUES (?, ?, 'td') ON DUPLICATE KEY UPDATE account_count = VALUES(account_count), custodian = VALUES(custodian)";
        foreach ($reps AS $k => $v) {
            $adb->pquery($query, array($k, $v));
        }

        $query = "INSERT INTO account_rep_codes (account_number, rep_code, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rep_code = VALUES(rep_code), status = VALUES(status)";
        foreach ($accounts AS $k => $v) {
            $adb->pquery($query, array($k, $v['rep_code'], $v['status']));
        }
    }

    static public function UpdateTDAccountTypes()
    {
        return;
        global $adb;
        $trade = new Trading_Ameritrade_Model();
        $increment = 300;
        $counter = 1;
        $x = 1;
        $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, 1, 1);
        $max = $tmp['model']['getAccountsJson']['responseInfo']['totalSize'];
        $query = "INSERT INTO portfolio_mapping_td (td_type)  
                  VALUES (?)
                  ON DUPLICATE KEY SET td_type = VALUES(td_type)";
        $types = array();

        while ($counter < $max) {
            $tmp = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", null, $counter, $counter + $increment);
            foreach ($tmp['model']['getAccountsJson']['account'] AS $k => $v) {
                $types[$v['accountCategoryCode']] = 1;
            }
            $counter += $increment;
        }
        foreach ($types AS $k => $v) {
            $adb->pquery($query, array($k));
        }
    }

    static public function MarkInceptionIntervalsDone($account_number){
        global $adb;

        $query = "UPDATE vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING(portfolioinformationid)
                  SET daily_since_inception = 1 WHERE account_number = ?";
        $adb->pquery($query, array($account_number));
    }

    static public function GetAccountsThatInceptionIntervalsHaveNotRun($limit){
        global $adb;

        if(strlen($limit) > 0){
            $limit = " LIMIT {$limit} ";
        }
        $query = "SELECT account_number 
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND p.accountclosed = 0
                  AND cf.daily_since_inception = 0
                  {$limit}";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $account_numbers[] = $v['account_number'];
            }
            return $account_numbers;
        }
        return 0;
    }

    static public function GetAccountsThatDontHaveIntervalForDate($date, $limit){
        global $adb;

        if(strlen($limit) > 0){
            $limit = " LIMIT {$limit} ";
        }
        $query = "SELECT account_number 
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0 AND p.accountclosed = 0
                  AND account_number NOT IN (SELECT accountnumber FROM intervals_daily WHERE intervalenddate = ?)
                  {$limit}";
        $result = $adb->pquery($query, array($date));
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $account_numbers[] = $v['account_number'];
            }
            return $account_numbers;
        }
        return 0;
    }

    static public function GetAccountsPCHasNotTransferred($limit){
        global $adb;

        if(strlen($limit) > 0)
            $limit = " LIMIT " . $limit;

        $query = "SELECT account_number 
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE pc_transactions_transferred != 1 OR pc_transactions_transferred IS NULL
                  AND e.deleted = 0 
                  AND p.accountclosed = 0
                  {$limit}";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $account_numbers[] = $v['account_number'];
            }
            return $account_numbers;
        }
        return 0;
    }

    static public function HavePCTransactionsBeenTransferred($account_number){
        global $adb;

        $query = "SELECT pc_transactions_transferred FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0) {
            if ($adb->query_result($result, 0, 'pc_transactions_transferred') == 1)
                return 1;
            return 0;
        }
        return 0;
    }

    static public function SetPCTransactionsTransferredToNo($account_number)
    {
        global $adb;
        $query = "UPDATE vtiger_portfolioinformation p 
	              JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid) 
	              SET pc_transactions_transferred = 0 
	              WHERE account_number = ? ";
        $adb->pquery($query, array($account_number));
    }

    static public function CopyTransactionsFromPCTableToCloud($account_number){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        $query = "INSERT IGNORE INTO custodian_omniscient.custodian_transactions_pc
                  SELECT t.*, o.custodian AS custodian FROM {$db_name}.vtiger_pc_transactions t
                  JOIN {$db_name}.vtiger_portfolios p ON t.portfolio_id = p.portfolio_id AND p.portfolio_account_number = ?
                  JOIN {$db_name}.vtiger_pc_originations o ON o.id = t.origination_id
                  WHERE t.activity_id != 30 AND t.symbol_id != 0";
        $adb->pquery($query, array($account_number));
    }

    static public function CopyTransactionsFromCloudToCRM($account_number){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];


        $query = "DROP TABLE IF EXISTS PCTransactions";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE PCTransactions 
                  SELECT cloud_transaction_id 
                  FROM live_omniscient.vtiger_transactions JOIN live_omniscient.vtiger_transactionscf USING (transactionsid) 
                  WHERE pc_transferred = 1 AND account_number = ?";
        $adb->pquery($query, array($account_number));

        $query = "DROP TABLE IF EXISTS CreateTransactions";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE CreateTransactions 
SELECT 0 AS crmid, 0000000.00000 AS price, m.omniscient_category, m.omniscient_activity, transaction_id, portfolio_id, sell_lot_id, trade_lot_id, link_id, custodian_id, symbol_id, activity_id, money_id, broker_id, report_as_type_id, quantity, total_value, conversion_value, accrued_interest, yield_at_purchase, advisor_fee, amount_per_share, other_fee, net_amount, settlement_date, trade_date, origina_trade_date, entry_date, link_date, odd_income_payment_flag, long_position_flag, reinvest_gains_flag, reinvest_income_flag, keep_fractional_shares_flag, taxable_prev_year_flag, complete_transaction_flag, is_reinvested_flag, notes, principal, add_sub_status_type_id, contribution_type_id, matching_method_id, custodian_account, original_link_account, origination_id, last_modified_date, trans_link_id, status_type_id, last_modified_user_id, dirty_flag, invalid_cost_basis_flag, cost_basis_adjustment, security_split_flag, reset_cost_basis_flag, deleted, account_number, data_set_id, symbol, custodian, m.operation 
FROM custodian_omniscient.custodian_transactions_pc t 
JOIN live_omniscient.pcmapping m ON m.id = t.activity_id AND m.rat = t.report_as_type_id AND m.add_sub_status_type = t.add_sub_status_type_id WHERE t.transaction_id NOT IN (SELECT cloud_transaction_id FROM PCTransactions) AND t.status_type_id = 100
        AND t.account_number = ?
GROUP BY transaction_id";
        $adb->pquery($query, array($account_number));

        $query = "UPDATE CreateTransactions t JOIN live_omniscient.vtiger_securities s ON t.symbol_id = s.security_id 
JOIN live_omniscient.vtiger_portfolios p ON t.portfolio_id = p.portfolio_id 
SET t.symbol = s.security_symbol, t.account_number = REPLACE(p.portfolio_account_number, '-', ''), operation = CASE WHEN operation IS NULL THEN '' ELSE operation END";
        $adb->pquery($query, array());

        $query = "UPDATE CreateTransactions t
SET net_amount = CASE WHEN net_amount = 0 THEN total_value ELSE net_amount END";
        $adb->pquery($query, array());

        $query = "UPDATE CreateTransactions t SET price = COALESCE(ABS(net_amount / CASE WHEN quantity > 0 THEN quantity ELSE net_amount END), 0.0)";
        $adb->pquery($query, array());

        $crmid = $adb->getUniqueID("vtiger_crmentity");
        $query = "UPDATE CreateTransactions SET crmid = ?";
        $adb->pquery($query, array($crmid));

        $query = "INSERT INTO live_omniscient.vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label) SELECT crmid, 1, 1, 1, 'Transactions', NOW(), NOW(), notes FROM CreateTransactions";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_transactions (transactionsid, account_number, security_symbol, security_price, quantity, trade_date, origination, cloud_transaction_id, operation) SELECT crmid, account_number, symbol, price, ABS(quantity), trade_date, custodian, transaction_id, operation FROM CreateTransactions";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_transactionscf (transactionsid, custodian, transaction_type, transaction_activity, net_amount, principal, comment, pc_transferred) SELECT crmid, custodian, omniscient_category, omniscient_activity, ABS(net_amount), ABS(principal), notes, 1 FROM CreateTransactions";
        $adb->pquery($query, array());
    }

    static public function CreateTransactionsFromPCCloudUsingJava($custodian, $account_number){
        global $dbconfig;
        $db_name = $dbconfig['db_name'];
        $url = "http://lanserver24.concertglobal.com:8085/OmniServ/AutoParse?tenant=Omniscient&user=syncuser&password=Concert222&connection=192.168.102.229&dbname=custodian_omniscient&operation=createtransactions&vtigerDBName={$db_name}&custodian={$custodian}&account_number={$account_number}";
        file_get_contents($url);
    }

    static public function CreateTransactionsFromPCCloud($custodian, $account_number)
    {
        global $dbconfig;
        $db_name = $dbconfig['db_name'];
        $url = "http://lanserver24.concertglobal.com:8085/OmniServ/AutoParse?tenant=Omniscient&user=syncuser&password=Concert222&connection=192.168.102.229&dbname=custodian_omniscient&operation=createtransactions&vtigerDBName={$db_name}&custodian={$custodian}&account_number={$account_number}";
        file_get_contents($url);
    }


    /**
     * Function to get the Quick Links for the module
     * @param <Array> $linkParams
     * @return <Array> List of Vtiger_Link_Model instances
     */
    public function getSideBarLinks($linkParams)
    {
        $parentQuickLinks = parent::getSideBarLinks($linkParams);

        $quickLink = array(
            'linktype' => 'SIDEBARLINK',
            'linklabel' => 'LBL_DASHBOARD',
            'linkurl' => $this->getDashBoardUrl(),
            'linkicon' => '',
        );

        //Check profile permissions for Dashboards
        $moduleModel = Vtiger_Module_Model::getInstance('Dashboard');
        $userPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
        $permission = $userPrivilegesModel->hasModulePermission($moduleModel->getId());
        if ($permission) {
            $parentQuickLinks['SIDEBARLINK'][] = Vtiger_Link_Model::getInstanceFromValues($quickLink);
        }

        return $parentQuickLinks;
    }

    static public function CreateDailyIntervalsForAccounts(array $accounts, $date)
    {
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        $query = "CALL CALCULATE_DAILY_INTERVALS_LOOP(?, ?, ?, ?, {$db_name});";
        foreach ($accounts AS $k => $v) {
            $custodian = self::GetCustodianFromAccountNumber($v);
            $adb->pquery($query, array($v, $date, $date, $custodian));
        }
    }

    static public function DeleteIntervals(array $account_numbers, $sdate, $edate){
        global $adb;
        if(empty($account_numbers))
            return;

        $questions = generateQuestionMarks($account_numbers);

        $query = "DELETE FROM intervals_daily 
                  WHERE accountnumber IN ({$questions}) 
                  AND intervalenddate BETWEEN ? AND ?";
        $adb->pquery($query, array($account_numbers, $sdate, $edate));
    }

    static public function CalculateDailyIntervals(array $account_numbers, $sdate, $edate){
        global $adb;
        if(empty($account_numbers))
            return;
#        self::DeleteIntervals($account_numbers, $sdate, $edate);

        foreach($account_numbers AS $account_number){
            $tmp = new CustodianClassMapping($account_number);
            $begin = new DateTime($sdate);
            $end = new DateTime($edate);
            $earliest = $tmp->portfolios::GetEarliestBalanceAndDate(array($account_number));
            $earliest = new DateTime($earliest[$account_number]['as_of_date']);

            if(empty($earliest))
                return;

            if($earliest > $begin)
                $begin = $earliest;

            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($begin, $interval, $end);
            foreach ($period as $dt) {
                $tokenDate = $dt->format("Y-m-d");
                $start = $tmp->portfolios::GetBeginningBalanceAsOfDate(array($account_number), $tokenDate);
                $end = $tmp->portfolios::GetEndingBalanceAsOfDate(array($account_number), $tokenDate);


                if(empty($start) && !empty($end)){
                    $start[$account_number]['value'] = 0;
                    $start[$account_number]['date'] = $end[$account_number]['date'];
                }elseif(empty($end)){//If end date has no value, leave, there is nothing to enter
                    return;
                }

            $query = "SELECT SUM(CONCAT(operation, ABS(net_amount))) AS amount, transaction_type, transaction_activity, trade_date, operation
                      FROM vtiger_transactions t
                      JOIN vtiger_transactionscf cf USING (transactionsid)
                      JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                      WHERE account_number = ? 
                      AND trade_date > ? 
                      AND trade_date <= ?
                      AND e.deleted = 0
                      GROUP BY transaction_type, transaction_activity";

            $result = $adb->pquery($query, array($account_number, $start['date'], $end['date']));



/*

                print_r($start);
                echo '<br /><br />';
                print_r($end);exit;
/*
                if(empty($start[$account_number]['date']) && !empty($end[$account_number]['date']) )

IF @beginningDate IS NULL AND @endingDate IS NOT NULL THEN SET @beginningDate := inStartDate - INTERVAL 1 DAY; END IF;#@beginningDate = inStartDate;
IF @beginningNet IS NULL THEN SET @beginningNet := 0; END IF;
   IF @beginningDate >= @endingDate THEN LEAVE this_proc; END IF;
   IF @beginningDate IS NULL THEN LEAVE this_proc; END IF;
   IF @endingDate IS NULL THEN LEAVE this_proc; END IF;


#                $iStart = $begin->format("Y-m-d");
#                $iEnd = $end->format("Y-m-d");

*/

#                echo $iStart . " started with: "; print_r($iStart); echo " AND ended with "; print_r($iEnd); echo '<br /><br />';
            }
/*            $tmp = new CustodianClassMapping($account_number);
            $start = $tmp->portfolios::GetBeginningBalanceAsOfDate(array($account_number), $sdate);
            $end = $tmp->portfolios::GetEndingBalanceAsOfDate(array($account_number), $sdate);
            if(empty($start) && !empty($end)){//This is the first day that has a value, so it started with $0
                $start[$account_number]['value'] = 0;
                $start[$account_number]['date'] = $sdate;
            }
*/

#            $start = $tmp->portfolios::GetEarliestBalanceAndDate(array($account_number));

#            print_r($start);
#            echo '<br /><br />';
#            print_r($end);
        }
    }

    static public function IsPerformanceDisabled($account_number)
    {
        global $adb;

        $query = "SELECT disable_performance FROM vtiger_portfolioinformation p JOIN vtiger_portfolioinformationcf cf USING(portfolioinformationid) WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0) {
            return $adb->query_result($result, 0, 'disable_performance');
        }
        return 0;
    }

    static public function GetAccountOwnerFromAccountNumber(string $account_number){
        global $adb;
        $query = "SELECT smownerid 
                  FROM vtiger_crmentity e 
                  JOIN vtiger_portfolioinformation p ON p.portfolioinformationid = e.crmid
                  WHERE p.account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0,"smownerid");
        }
        return 1;
    }

    static public function GetAccountNameFromAccountNumber($account_number)
    {
        global $adb;
        $desc = '';

        $query = "SELECT first_name, last_name, description FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  WHERE account_number=?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0){
            $description = $adb->query_result($result, 0, 'description');
            $first = $adb->query_result($result, 0, 'first_name');
            $last = $adb->query_result($result, 0, 'last_name');
            if(strlen(trim($description)) < 2){
                $desc = $first . ' ' . $last;
            }else{
                $desc = $description;
            }
            return $desc;
        }else{
            return ' -- ';
        }
    }

    static public function GetAccountTypeFromAccountNumber($account_number)
    {
        global $adb;

        $query = "SELECT account_type FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  WHERE account_number=?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'account_type');
        return ' -- ';
    }

    static public function GetPreparedForNameByRecordID($record_id)
    {
        if ($record_id) {
            $record = Vtiger_Record_Model::getInstanceById($record_id);
            $data = $record->getData();
            $module = $record->getModule();
            switch ($module->getName()) {
                case "PortfolioInformation":
                    if (strlen($data['statement_title']) > 0) {
                        return $data['statement_title'];
                    }
                    if (strlen($data['contact_link']) > 0 && $data['contact_link'] != '0') {
                        $contact_record = Contacts_Record_Model::getInstanceById($data['contact_link']);
                        $name = $contact_record->getName();
                        return $name;
                    }
                    return $data['last_name'];
                    break;
                case "Contacts":
                    $contact_record = Contacts_Record_Model::getInstanceById($record_id);
                    $name = $contact_record->getName();
                    return $name;
                    break;
                case "Accounts":
                    if (strlen($data['statement_title']) > 0)
                        return $data['statement_title'];
                    $account_record = Accounts_Record_Model::getInstanceById($record_id);
                    $name = $account_record->getName();
                    return $name;
                    break;
            }
        }
    }

    static public function GetPreparedByFormattedByUserID($user_id){
        $statement = new PortfolioInformation_Statements_Model();
        $preparedBy = $statement->GetPreparedByData($user_id);
        if($preparedBy)
            return htmlspecialchars_decode($preparedBy);

        return false;
    }

    static public function GetPreparedByFormattedByRecordID($record_id){
        $record = VTiger_Record_Model::getInstanceById($record_id);
        $assigned_user = $record->get('assigned_user_id');
        $statement = new PortfolioInformation_Statements_Model();
        $preparedBy = $statement->GetPreparedByData($assigned_user);
        if($preparedBy)
            return htmlspecialchars_decode($preparedBy);

        return false;
    }

    static public function GetPreparedByNameByRecordID($record_id)
    {
        if ($record_id) {
            $record = VTiger_Record_Model::getInstanceById($record_id);
            $data = $record->getData();
            $module = $record->getModule();
            $current_user = Users_Record_Model::getCurrentUserModel();
            switch ($module->getName()) {
                case "PortfolioInformation":
                    if (strlen($data['prepared_by']) > 0) {
                        return $data['prepared_by'];
                    }
                    return $current_user->getName();
                    break;
                case "Contacts":
                    return $current_user->getName();
                    break;
                case "Accounts":
                    if (strlen($data['preparer']) > 0)
                        return $data['preparer'];
                    return $current_user->getName();
                    break;
            }
        }
    }

    static public function UpdateOmniInceptionDate(){
        global $adb;

        $query = "UPDATE vtiger_portfolioinformation p
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  SET p.inceptiondate = e.createdtime 
                  WHERE p.inceptiondate IS NULL OR p.inceptiondate = '0000-00-00';";
        $adb->pquery($query, array());
    }

    static public function UpdateContactFees(){
        global $adb;
        $query = "SELECT contactid, SUM(pcf.ytd_management_fees) AS ytd_management_fees, SUM(p.annual_management_fee) AS annual_management_fee 
                  FROM vtiger_contactscf ccf
                  JOIN vtiger_portfolioinformation p ON p.contact_link = ccf.contactid
                  JOIN vtiger_portfolioinformationcf pcf ON pcf.portfolioinformationid = p.portfolioinformationid
                  GROUP BY contactid";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_contactscf 
                      SET ytd_management_fees = ?, annual_management_fee = ?, management_fee_update = NOW()
                      WHERE contactid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, array($v['ytd_management_fees'], $v['annual_management_fee'], $v['contactid']));
            }
        }
    }

    static public function UpdateHouseholdFees()
    {
        global $adb;
        $query = "SELECT accountid, SUM(pcf.ytd_management_fees) AS ytd_management_fees, SUM(p.annual_management_fee) AS annual_management_fee 
                  FROM vtiger_accountscf ccf
                  JOIN vtiger_portfolioinformation p ON p.household_account = ccf.accountid
                  JOIN vtiger_portfolioinformationcf pcf ON pcf.portfolioinformationid = p.portfolioinformationid
                  GROUP BY accountid";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_accountscf 
                      SET ytd_management_fees = ?, trailing_fees = ?, management_fee_update = NOW()
                      WHERE accountid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, array($v['ytd_management_fees'], $v['annual_management_fee'], $v['accountid']));
            }
        }
#        global $adb;
#        $query = "CALL UPDATE_HOUSEHOLD_MANAGEMENT_FEES()";
#        $adb->pquery($query, array());
    }

    static public function UpdateYTDManagementFees()
    {
        global $adb;
        $query = "CALL UPDATE_PORTFOLIO_MANAGEMENT_FEES_FIELD(DATE_FORMAT(NOW(),'%Y-01-01'), NOW(), 'ytd_management_fees')";
        $adb->pquery($query, array());
    }

    static public function UpdateTrailing12ManagementFees()
    {
        global $adb;
        $query = "CALL UPDATE_PORTFOLIO_MANAGEMENT_FEES_FIELD(NOW() - INTERVAL 1 YEAR, NOW(), 'annual_management_fee')";
        $adb->pquery($query, array());
    }

    static public function GetReportSelectionOptions($report_name)
    {
        global $adb;
        $query = "SELECT * FROM vtiger_report_options WHERE report_name = ? ORDER BY sort_order ASC";
        $result = $adb->pquery($query, array($report_name));
        $values = array();
        if ($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $v['date'] = self::ReportValueToDate($v['option_value']);
                if($v['default'] == 1)
                    $v['date']['default'] = 1;
                $values[] = $v;
            }
            return $values;
        }
        return 0;
    }

    /**
     * Converts the option value to an actual date to be filled in
     * @param $option_value
     */
    static public function ReportValueToDate($option_value, $month_only = false)
    {
        $day = "";
        if (!$month_only)
            $day = "d/";
        $date = date("Y");

        switch ($option_value) {
            case "current":
                $dateReturn['end'] = date("m/{$day}Y");
                break;
            case "last_month":
                $dateReturn['end'] = date("m/{$day}Y", strtotime("last day of previous month"));
                break;
            case "last_year":
                $dateReturn['end'] = date("m/{$day}Y", strtotime("last year December 31st"));
                break;
            case "last_year_start":
                $dateReturn['end'] = date("m/{$day}Y", strtotime("last year January 1st"));
                break;
            case "ytd":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st " . date('Y')));
                $dateReturn['end'] = date("Y-m-d", strtotime("today"));
                break;
            case "2017":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st 2017"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2017"));
                break;
            case "2018":
                $dateReturn['start'] = date("Y-m", strtotime("January 1st 2018"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2018"));
                break;
            case "2019":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st 2019"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2019"));
                break;
            case "2020":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st 2020"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2020"));
                break;
            case "2021":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st 2021"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2021"));
                break;
      	    case "2022":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st 2022"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2022"));
                break;
	    case "2023":
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st 2023"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st 2023"));
                break;
	    case "trailing_12":
                $dateReturn['start'] = date("Y-m-d", strtotime("today -1 year"));
                $dateReturn['end'] = date("Y-m-d", strtotime("today"));
                break;
            case "trailing_6":
                $dateReturn['start'] = date("Y-m-d", strtotime("today -6 months"));
                $dateReturn['end'] = date("Y-m-d", strtotime("today"));
                break;
            case $date:
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st {$date}"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st {$date}"));
                break;
            case "custom":
                $date = date("Y");
                $dateReturn['start'] = date("Y-m-d", strtotime("January 1st {$date}"));
                $dateReturn['end'] = date("Y-m-d", strtotime("December 31st {$date}"));
                break;
            default:
                $dateReturn['end'] = date("m/{$day}Y");
        }

        return $dateReturn;
    }

    static public function SetStratifiID($id, $account_number)
    {
        global $adb;

        $query = "UPDATE vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  SET cf.stratid = ? WHERE p.account_number = ?";
        $adb->pquery($query, array($id, $account_number));
    }

    static public function GetStratifiData($account_number)
    {
        global $adb;
        $questions = generateQuestionMarks($account_number);
        $query = "SELECT stratid, p.account_number, CONCAT('POR',p.portfolioinformationid) AS stratname, security_symbol, pos.weight, pos.current_value
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid 
                  JOIN vtiger_positioninformation pos ON pos.account_number = p.account_number
                  JOIN vtiger_positioninformationcf poscf ON poscf.positioninformationid = pos.positioninformationid
                  WHERE e.deleted = 0 AND p.accountclosed = 0 AND pos.quantity != 0 AND p.account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_number));

        if ($adb->num_rows($result) > 0) {
            $account_info = array();
            while ($v = $adb->fetchByAssoc($result)) {
                $tmp = array();
                $tmp['security_symbol'] = $v['security_symbol'];
                $tmp['weight'] = $v['weight'];
                $tmp['current_value'] = $v['current_value'];
                $account_info['symbol_data'][] = $tmp;
            }
            $account_info['stratid'] = $adb->query_result($result, 0, 'stratid');
            $account_info['stratname'] = $adb->query_result($result, 0, 'stratname');
            $account_info['account_number'] = $adb->query_result($result, 0, 'account_number');
            return $account_info;
        }
        return 0;
    }

    static public function GetAccountNumbersWithoutStratifiID($number_to_get)
    {
        global $adb;

        $query = "SELECT p.account_number 
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  JOIN vtiger_positioninformation pos ON pos.account_number = p.account_number
                  JOIN vtiger_positioninformationcf poscf ON poscf.positioninformationid = pos.positioninformationid
                  JOIN vtiger_modsecurities m ON m.security_symbol = pos.security_symbol
                  WHERE (cf.stratid IS NULL OR cf.stratid = 0)
                  AND e.deleted = 0 
                  AND p.accountclosed = 0 
                  AND pos.quantity > 0 LIMIT {$number_to_get}";
        $result = $adb->pquery($query, array());
        if ($adb->num_rows($result) > 0) {
            while ($x = $adb->fetchByAssoc($result)) {
                $account_numbers[] = $x['account_number'];
            }
        }

        return $account_numbers;
    }

    static public function DoesAccountHaveStratifiID($account_number)
    {
        global $adb;
        $query = "SELECT stratid 
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid) 
                  WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($result) > 0) {
            $val = $adb->query_result($result, 0, 'stratid');
            if ($val == 0 || $val == '') {
                return 0;
            } else {
                return $val;
            }
        }
        return 0;
    }

    /**
     * Gets account numbers without stratifi ID's and creates them in Stratifi
     */
    static public function StratifiCreationLoop()
    {
        $strat = new StratifiAPI();

        $account_numbers = PortfolioInformation_Module_Model::GetAccountNumbersWithoutStratifiID(25);
        foreach ($account_numbers AS $k => $v) {
            $stratify_data[] = PortfolioInformation_Module_Model::GetStratifiData($v);
        }

        foreach ($stratify_data AS $k => $v) {
            $result = json_decode($strat->CreateNewStratifiAccount($v['stratname']));
            $v['stratid'] = $result->id;
            PortfolioInformation_Module_Model::SetStratifiID($result->id, $v['account_number']);
            $result = $strat->SendPositionsToStratifi($v);
        }
    }

    /**
     * @param $omniID
     * @param $stratifiID
     * Updates the portfolio in omniscient with the Stratifi ID
     */
    static public function UpdateStratifiID($omniID, $stratifiID)
    {
        global $adb;
        $query = "UPDATE vtiger_portfolioinformationcf 
                  SET stratid = ? WHERE portfolioinformationid = ?";
        $adb->pquery($query, array($stratifiID, $omniID));

    }

    /**
     * @param $omniID
     * Creates a new Stratifi account on their end.  Uses a Portfolio record number
     */
    static public function CreateStratifiPortfolioAccount($omniID)
    {
        global $adb;
        $stratifi = new StratifiAPI();
        $result = json_decode($stratifi->CreateNewStratifiAccount("POR{$omniID}"));
        echo " - result - ";
        print_r($result);
        echo "<br />";
        if ($result->id) {
            self::UpdateStratifiID($omniID, $result->id);
        }
    }

    static public function UpdateHouseholdLinkForContact($contact_id, $new_household_id)
    {
        global $adb;
        $query = "UPDATE vtiger_portfolioinformation SET household_account = ? WHERE contact_link = ?";
        $adb->pquery($query, array($new_household_id, $contact_id));
    }

    /**
     * Get list of account numbers for the logged in user
     * @return array|int
     */
    static public function GetAccountNumbersForLoggedInUser($open_only = true)
    {
        if ($open_only) {
            $where = " WHERE vtiger_crmentity.deleted=0 ";
        }
        $currentUser = Users_Record_Model::getCurrentUserModel();
        $adb = PearDatabase::getInstance();

        $query = "SELECT account_number FROM vtiger_portfolioinformation
				  INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid ";

        if (!$currentUser->isAdminUser()) {
            $query .= Users_Privileges_Model::getNonAdminAccessControlQuery('PortfolioInformation');
        }
        $query .= $where;
        $result = $adb->pquery($query, array());

        if ($adb->num_rows($result) > 0) {
            $account_numbers = array();
            while ($x = $adb->fetchByAssoc($result)) {
                $account_numbers[] = $x['account_number'];
            }
            return $account_numbers;
        }
        return false;
    }

    /**
     * This is specifically for an individual transaction, NOT AN ARRAY
     * @param $account_number
     */
    static public function CreateTransactionsForPositionsThatHaveNone($account_number){
        global $adb;return;
        $query = "SELECT * FROM vtiger_positioninformation p
                  JOIN vtiger_positioninformationcf cf USING (positioninformationid)
                  WHERE account_number IN (?)
                  AND quantity <> 0
                  AND p.security_symbol NOT IN (SELECT security_symbol FROM vtiger_transactions t 
                                                JOIN vtiger_transactionscf cf USING (transactionsid) 
                                                WHERE account_number IN (?) AND transaction_type IN ('Trade', 'Flow') 
                                                GROUP BY security_symbol)";
        $result = $adb->pquery($query, array($account_number, $account_number));
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $t = Vtiger_Record_Model::getCleanInstance("Transactions");
                $data = $t->getData();
                $data['security_symbol'] = $v['security_symbol'];
                $data['description'] = 'System Generated Transaction (Code 1)';
                $data['account_number'] = $v['account_number'];
                $data['quantity'] = 0;
                $data['net_amount'] = 0;
                $data['transaction_type'] = 'Flow';
                $data['trade_date'] = date("Y-m-d");
                $data['system_generated'] = 1;
                $t->set('mode','create');
                $t->setData($data);
                $t->save();
            }
        }
    }

    static public function AutoGenerateTransactionsForGainLossReport($account_number){
        global $adb;return;
        PortfolioInformation_Module_Model::CreateTransactionsForPositionsThatHaveNone($account_number);
        PortfolioInformation_GainLoss_Model::CreateGainLossTables($account_number);

        $query = "SELECT * FROM COMPARISON";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                if($v['reconcile'] != 0){//If we need to reconcile
                    $transaction_id = Transactions_Module_Model::GetGeneratedTransactionID($account_number, $v['security_symbol']);//Find a transaction that already exists for the given symbol
                    if($transaction_id == 0){//The symbol doesn't exist already for this account, create it
                        $t = Vtiger_Record_Model::getCleanInstance("Transactions");
                        $data = $t->getData();
                        $data['security_symbol'] = $v['security_symbol'];
                        $data['description'] = 'System Generated Transaction (Code 2)';
                        $data['account_number'] = $account_number;
                        $tmp_quantity = $v['reconcile'];
                        $data['quantity'] = ABS($v['reconcile']);
                        $data['net_amount'] = ABS($v['reconcile']);
                        $data['transaction_type'] = 'Flow';
                        if($tmp_quantity < 0) {
                            $data['operation'] = '-';
                            $data['transaction_activity'] = 'Transfer of securities';
                        }else{
                            $data['transaction_activity'] = 'Receipt of securities';
                        }
                        $data['trade_date'] = date("Y-m-d");
                        $data['system_generated'] = 1;
                        $t->set('mode','create');
                        $t->setData($data);
                        $t->save();
##                        echo 'created for ' . $v['security_symbol'] . '<br />';
                    }else{//A system generated transaction already exists, so update the quantity accordingly
                        $recordModel = Vtiger_Record_Model::getInstanceById($transaction_id, 'Transactions');
                        $data = $recordModel->getData();
                        $tmp_quantity = $data['quantity'] + $v['reconcile'];
                        $data['quantity'] = ABS($tmp_quantity);
#                        print_r($v); echo "<br />";
#                        print_r($data); echo "<br />";exit;
                        $price = ModSecurities_Module_Model::GetSecurityPrice($v['security_symbol']);
                        $data['transaction_type'] = 'Flow';
                        $data['security_price'] = $price;
                        $data['net_amount'] = ABS($tmp_quantity * $v['security_price_adjustment'] * $price);
                        if($tmp_quantity < 0) {
                            $data['operation'] = '-';
                            $data['transaction_activity'] = 'Transfer of securities';
                        }
                    else{
                            $data['operation'] = '';
                            $data['transaction_activity'] = 'Receipt of securities';
                        }
                        $recordModel->setData($data);
                        $recordModel->set('mode','edit');
                        $recordModel->save();
##                        echo 'updated for ' . $v['security_symbol'] . '<br />';
                    }
##                    echo 'check for ' . $v['security_symbol'];exit;
                }
            }
        }
    }

    /**
     * Get list of account numbers for the specified user
     * @return array|int
     */
    static public function GetAccountNumbersForSpecificUser($user_id, $open_only = true)
    {
        $user = new Users();
        $user = $user->retrieve_entity_info($user_id, 'Users');
        $user = Users_Record_Model::getInstanceFromUserObject($user);

        if ($open_only) {
            $where = " WHERE vtiger_crmentity.deleted=0 ";
        }
        $adb = PearDatabase::getInstance();

        $query = "SELECT account_number FROM vtiger_portfolioinformation
				  INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid ";

        if (!$user->isAdminUser()) {
            $query .= getNonAdminAccessControlQuery("PortfolioInformation", $user);
        }
        $query .= $where;

        $result = $adb->pquery($query, array());

        if ($adb->num_rows($result) > 0) {
            $account_numbers = array();
            while ($x = $adb->fetchByAssoc($result)) {
                $account_numbers[] = $x['account_number'];
            }
            return $account_numbers;
        }
        return false;
    }

    static public function GetEntityIDFromStratifiID($stratifiid){
        global $adb;

        $query = "SELECT portfolioinformationid FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid) 
                  WHERE cf.stratid = ?";
        $result = $adb->pquery($query, array($stratifiid));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'portfolioinformationid');
        }
        return 0;
    }

    static public function WriteTrailingTWRToPortfolio($account_number, $trailing_num_months, $field){
        global $adb;
        $sdate = GetFirstDayMinusNumberOfMonthsFromEndOfLastMonth($trailing_num_months);
        $edate = GetLastDayLastMonth();
        $type = "monthly";
        $query = "CALL TWR_TRAILING_FOR_ACCOUNT(?, ?, ?, ?, @twr)";
        $twr = 0.00;
        $adb->pquery($query, array($account_number, $sdate, $edate, $type));
        $query = "SELECT @twr AS twr";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            $twr = $adb->query_result($result, 0, 'twr');
            $query = "UPDATE vtiger_portfolioinformation p
                      JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                      SET {$field} = ?, last_twr_calculated = NOW()
                      WHERE account_number = ?";
            $adb->pquery($query, array($twr, $account_number));
        }
    }

    static public function UpdatePortfolioDataInCloudForTDByRepCode(array $rep_codes){
        include_once("modules/Trading/models/Ameritrade.php");
        global $adb;
        $trade = new Trading_Ameritrade_Model();
        $result = $trade->GetAllAccountsForRepCode("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", $rep_codes);
        $query = "INSERT INTO custodian_omniscient.custodian_portfolios_td (account_number, first_name, last_name, street, address2, city, 
                              state, zip, phone_number, advisor_id, rep_code)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), street = VALUES(street), address2 = VALUES(address2),
                                          city = VALUES(city), state = VALUES(state), zip = VALUES(zip),  
                                          phone_number = VALUES(phone_number), advisor_id = VALUES(advisor_id), rep_code = VALUES(rep_code)";
        foreach($result['model']['getAccountsJson']['account'] AS $k => $v){
            $adb->pquery($query, array($v['accountNumber'], $v['firstName'], $v['lastName'], $v['address1'], $v['address2'], $v['city'], $v['state'],
                $v['zip'], $v['secondaryPhone'], $v['repCode'], $v['repCode']));
        }
    }

    static public function UpdatePortfolioDataInCloudForTDByRepCodeBackup(array $rep_codes){//The original had accounttype in it, the version above no longer does
        include_once("modules/Trading/models/Ameritrade.php");
        global $adb;
        $trade = new Trading_Ameritrade_Model();
        $result = $trade->GetAllAccountsForRepCode("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", $rep_codes);
        $query = "INSERT INTO custodian_omniscient.custodian_portfolios_td (account_number, first_name, last_name, street, address2, city, 
                              state, zip, account_type, phone_number, advisor_id, rep_code)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), street = VALUES(street), address2 = VALUES(address2),
                                          city = VALUES(city), state = VALUES(state), zip = VALUES(zip), account_type = VALUES(account_type), 
                                          phone_number = VALUES(phone_number), advisor_id = VALUES(advisor_id), rep_code = VALUES(rep_code)";
        foreach($result['model']['getAccountsJson']['account'] AS $k => $v){
            $adb->pquery($query, array($v['accountNumber'], $v['firstName'], $v['lastName'], $v['address1'], $v['address2'], $v['city'], $v['state'],
                $v['zip'], $v['accountType'], $v['secondaryPhone'], $v['repCode'], $v['repCode']));
        }
    }

    static public function TDBalanceCalculations($sdate, $edate){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];

        $begin = new DateTime($sdate);
        $end = new DateTime($edate);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        $query = "CALL custodian_omniscient.TD_BALANCES_FROM_POSITIONS(?, {$db_name})";
        foreach ($period as $dt) {
            $d = $dt->format("Y-m-d");
            $adb->pquery($query, array($d));
        }
    }

    static public function GetInceptionBalanceDateForAccountNumber($account_number){
        global $adb;
        $custodian = self::GetCustodianFromAccountNumber($account_number);
        $field = '';
        switch(strtolower($custodian)){
            case "pershing":
                $field = 'date';
                break;
            default:
                $field = 'as_of_date';
                break;
        }

        $query = "SELECT MIN({$field}) AS {$field} FROM custodian_omniscient.custodian_balances_{$custodian} WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "{$field}");
        }
        #fidelity = as_of_date
        #schwab = as_of_date
        #td = as_of_date
        #pershing = date
    }

    static public function GetLatestBalanceForAccount($account_number){
        global $adb;

        $custodian = self::GetCustodianFromAccountNumber($account_number);
        switch(strtoupper($custodian)){
            case "TD":
                return cTDPortfolios::GetLatestBalance($account_number);
                break;
            case "FIDELITY":
                return cTDPortfolios::GetLatestBalance($account_number);
                break;
            case "SCHWAB":
                return cTDPortfolios::GetLatestBalance($account_number);
                break;
            case "PERSHING":
                return cTDPortfolios::GetLatestBalance($account_number);
                break;
            default:
                return 0;
                break;
        }

        return null;
    }

    static public function GetIntervalBeginValueForDate($account_number, $date){
        global $adb;

        $query = "SELECT * 
                  FROM intervals_daily 
			      WHERE IntervalEndDate >= ?
                  AND AccountNumber = ?
			      ORDER BY IntervalEndDate ASC LIMIT 1";
        $result = $adb->pquery($query, array($date, $account_number), true);
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "intervalbeginvalue");
        }
        return 0;
    }

    static public function GetIntervalValueLessThanDate($account_number, $date){
        global $adb;

        $query = "SELECT * 
                  FROM intervals_daily 
			      WHERE IntervalEndDate < ?
                  AND AccountNumber = ?
			      ORDER BY IntervalEndDate DESC LIMIT 1";
        $result = $adb->pquery($query, array($date, $account_number), true);
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "intervalendvalue");
        }
        return 0;
    }
    static public function GetIntervalValueAsOfDate($account_number, $date){
        global $adb;

        $query = "SELECT * 
                  FROM intervals_daily 
			      WHERE IntervalEndDate <= ?
                  AND AccountNumber = ?
			      ORDER BY IntervalEndDate DESC LIMIT 1";
        $result = $adb->pquery($query, array($date, $account_number), true);
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "intervalendvalue");
        }
        return 0;
    }

    static public function TDBalanceCalculationsIndividual($account_number, $sdate, $edate){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];

        $begin = new DateTime($sdate);
        $end = new DateTime($edate);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        $query = "CALL custodian_omniscient.TD_BALANCES_FROM_POSITIONS_INDIVIDUAL(?, ?, ?)";
        foreach ($period as $dt) {
            $d = $dt->format("Y-m-d");
            $adb->pquery($query, array($account_number, $d, $db_name), true);
#            echo "Check for {$account_number}, {$d} -- {$db_name}<br />";
        }
    }

    static public function GetEarliestTDPositionDate(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);
        $params = array();
        $params[] = $account_number;
        $query = "SELECT MIN(date) AS date FROM custodian_omniscient.custodian_positions_td WHERE account_number IN ({$questions}) AND date != '0000-00-00'";
        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');
        return 0;
    }

    static public function TDBalanceCalculationsMissingOnly($sdate, $edate){
        global $adb, $dbconfig;
        $db_name = 'live_omniscient';//$dbconfig['db_name'];

        $begin = new DateTime($sdate);
        $end = new DateTime($edate);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);
        $query = "CALL custodian_omniscient.TD_BALANCES_FROM_POSITIONS_MISSING_ONLY(?, ?);";

        foreach ($period as $dt) {
            $d = $dt->format("Y-m-d");
#            echo $query . '<br />';
##            $adb->pquery($query, array());

            $adb->pquery($query, array($d, $db_name), true);
#            echo $query . '<br />' . $d . '<br />' . $db_name;exit;
#            $q = "SELECT * FROM AccountValues;";
#            $result = $adb->pquery($q, array());
#            if($adb->num_rows($result) > 0){
#                echo 'YAY';
#            }
        }
    }

    static public function TDBalanceCalculationsMultiple(array $account_number, $sdate, $edate){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];

        $begin = new DateTime($sdate);
        $end = new DateTime($edate);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        $questions = generateQuestionMarks($account_number);
        $query = "CALL custodian_omniscient.TD_BALANCES_FROM_POSITIONS_MULTIPLE_FASTEST(\"{$questions}\", ?, ?)";
#        $query = "CALL custodian_omniscient.TD_BALANCES_FROM_POSITIONS_MULTIPLE(\"'925514559'\", '2019-02-20', '360vew_opt')";
        foreach ($period as $dt) {
            $d = $dt->format("Y-m-d");
#            echo $query . '<br />';
##            $adb->pquery($query, array());
            StatusUpdate::UpdateMessage("TDBALANCEUPDATE", "Calculating Balances for {$d}");
            $adb->pquery($query, array($account_number, $d, $db_name), true);
#            echo $query . '<br />' . $d . '<br />' . $db_name;exit;
#            $q = "SELECT * FROM AccountValues;";
#            $result = $adb->pquery($q, array());
#            if($adb->num_rows($result) > 0){
#                echo 'YAY';
#            }
        }
        StatusUpdate::UpdateMessage("TDBALANCEUPDATE", "Finished");
    }

    static public function TDBalanceCalculationsRepCodes(array $rep_codes, $sdate, $edate, $earliest=false){
        $accounts = self::GetAccountNumbersFromCustodianUsingRepCodes("TD", $rep_codes);

        if($earliest == true)
            $sdate = PortfolioInformation_Module_Model::GetEarliestTDPositionDate($accounts);
        /*        foreach($accounts AS $k => $v){
                    echo "'{$v}',";
                }
                exit;*/
#        foreach($accounts AS $k => $v){
#            echo "Trying {$v} using {$sdate}, {$edate}<br />";
#        $accounts = array('925514559');
#        print_r($accounts);exit;
            self::TDBalanceCalculationsMultiple($accounts, $sdate, $edate);
#            echo 'check now for ';
#            print_r($accounts);exit;
#            echo "Done {$v}<br />";exit;
#        }
    }

    static public function TDBalanceCalculationsAccount($account_number, $sdate, $edate, $earliest=false){
        if($earliest == true)
            $sdate = PortfolioInformation_Module_Model::GetEarliestTDPositionDate(array($account_number));

        self::TDBalanceCalculationsMultiple(array($account_number), $sdate, $edate);
    }

    static public function GetAccountNumbersFromCustodianUsingRepCodes($custodian, array $rep_codes){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($rep_codes);
        $params[] = $rep_codes;
        $account_numbers = array();

        $query = "SELECT account_number 
                  FROM custodian_omniscient.custodian_portfolios_{$custodian} 
                  WHERE rep_code IN ({$questions})";
        $result = $adb->pquery($query, $params, true);
        if($adb->num_rows($result) > 0)
            while($r = $adb->fetchByAssoc($result)){
                $account_numbers[] = $r['account_number'];
            }
        return $account_numbers;
    }

    static public function GetDateFieldForCustodianBalance($custodian){
        $datefield = "";

        switch(strtolower($custodian)){
            case "pershing":
                $datefield = "date";
                break;
            case "td":
            case "fidelity":
            case "schwab":
                $datefield = "as_of_date";
                break;
        }

        return $datefield;
    }

    static public function GetValueFieldForCustodianBalance($custodian){
        $balance_field = "";

        switch(strtolower($custodian)){
            case "pershing":
            case "fidelity":
                $balance_field = "net_worth";
                break;
            case "td":
            case "schwab":
                $balance_field = "account_value";
                break;
        }

        return $balance_field;
    }

    static public function GetInsertDateFieldCustodianBalance($custodian){
        $insert_field = "";

        switch(strtolower($custodian)){
            case "pershing":
            case "fidelity":
            case "schwab":
                $insert_field = "insert_date";
                break;
            case "td":
                $insert_field = "calculated";
                break;
        }

        return $insert_field;
    }

    static public function CreateBalanceInCustodian($database = 'custodian_omniscient', $custodian, $account_number, $balance, $date){
        global $adb;
        $datefield = self::GetDateFieldForCustodianBalance($custodian);
        $balance_field = self::GetValueFieldForCustodianBalance($custodian);
        $insert_date_field = self::GetInsertDateFieldCustodianBalance($custodian);

        $query = "INSERT INTO {$database}.custodian_balances_{$custodian} (account_number, {$datefield}, {$balance_field}, {$insert_date_field})
                  VALUES (?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE {$balance_field} = VALUES({$balance_field})";
//        echo $query . '<br />' . $account_number . '<br />' . $date . '<br />' . $balance . '<br />';
        $adb->pquery($query, array($account_number, $date, $balance));
//        echo "SELECT * FROM custodian_balances_{$custodian} WHERE account_number = '{$account_number}'";
    }

    static public function GetRepCodeFromAccountNumber($account_number){
        global $adb;
        $query = "SELECT cf.production_number FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "production_number");
        }
        return null;
    }

    static public function GetRepCodeListFromUsersTable(){
        global $adb;
        $query = "SELECT REPLACE(advisor_control_number, ' ', '') AS advisor_control_number
                  FROM vtiger_users WHERE advisor_control_number != ''";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $ids = explode(",", $r['advisor_control_number']);
                foreach($ids AS $k => $v){
                    $list[$v] = $v;//This ensures we don't get duplicates
                }
            }
        }
        return $list;
    }

    /**
     * Return a list of account numbers that don't have the provided production number(s)
     * @param array $rep_codes
     * @return array|void
     */
    static public function GetAccountNumbersNotBelongingToRepcodes(array $rep_codes){
        global $adb;
        if(sizeof($rep_codes) < 1)
            return;
        $questions = generateQuestionMarks($rep_codes);
        $query = "SELECT account_number 
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid) 
                  WHERE production_number NOT IN ({$questions}) OR production_number IS NULL";
        $result = $adb->pquery($query, array($rep_codes));

        $accounts = array();
        if($adb->num_rows($result) > 0){
            while ($v = $adb->fetchByAssoc($result)) {
                $accounts[] = $v['account_number'];
            }
        }
        return $accounts;
    }

    /**
     * Delete everything from the portfolioinformation module, including the vtiger_crmentity table
     * @param array $account_numbers
     */
    static public function RemovePortfoliosBelongingToAccounts(array $account_numbers){
        global $adb;
        if(sizeof($account_numbers) < 1)
            return;
        $questions = generateQuestionMarks($account_numbers);
        $query = "DELETE vtiger_portfolioinformation, vtiger_portfolioinformationcf, vtiger_crmentity 
                  FROM vtiger_portfolioinformation 
                  JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformation.portfolioinformationid = vtiger_portfolioinformationcf.portfolioinformationid
                  JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid
                  WHERE account_number IN({$questions})";
        $adb->pquery($query, array($account_numbers));
    }

    /**
     * Delete everything from consolidated balances where the provided account number(s) exist
     * @param array $account_numbers
     */
    static public function RemoveConsolidatedBalancesBelongingToAccounts(array $account_numbers){
        global $adb;
        if(sizeof($account_numbers) < 1)
            return;
        $questions = generateQuestionMarks($account_numbers);
        $query = "DELETE FROM consolidated_balances WHERE account_number IN({$questions})";
        $adb->pquery($query, array($account_numbers));
    }

    /**
     * Delete everything from intervals where the provided account number(s) exist
     * @param array $account_numbers
     */
    static public function RemoveIntervalsBelongingToAccounts(array $account_numbers){
        global $adb;
        if(sizeof($account_numbers) < 1)
            return;
        $questions = generateQuestionMarks($account_numbers);
        $query = "DELETE FROM intervals_daily WHERE accountnumber IN ({$questions})";
        $adb->pquery($query, array($account_numbers), true);

        $query = "DELETE FROM vtiger_interval_calculations WHERE account_number IN ({$questions})";
        $adb->pquery($query, array($account_numbers), true);

        $query = "DELETE FROM vtiger_asset_class_history WHERE account_number IN ({$questions})";
        $adb->pquery($query, array($account_numbers), true);
    }

    //TODO Need this finished.. It is to figured out portfolios not linked to contact
    static public function GetValidPortfoliosNotLinked(){
        global $adb;
        $query = "SELECT portfolioinformationid 
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  WHERE last_name IS NOT NULL AND last_name != '' AND last_name != 'System Generated'
                  AND ";
    }

    static public function GetLogo(){
        $current_user = Users_Record_Model::getCurrentUserModel();
        $companyDetails = Vtiger_CompanyDetails_Model::getInstanceById();
        $logo = $current_user->getImageDetails();

        if(isset($logo['user_logo']) && !empty($logo['user_logo'])){
            //echo '1';
            if(isset($logo['user_logo'][0]) && !empty($logo['user_logo'][0])){
                //echo '2';
                $logo = $logo['user_logo'][0];
                $logo = $logo['path']."_".$logo['name'];
            } else
                $logo = 0;
        } else
            $logo = "";

        if($logo == "_" || $logo == "") {
            $logo = $companyDetails->getLogo()->getImagePath();
        }

        if($logo == "")
            $logo = "test/logo/Omniscient Logo small.png";

        return $logo;
    }

    static public function DoesAccountExist($account_number)
    {
        global $adb;
        $query = "SELECT account_number FROM vtiger_portfolioinformation WHERE account_number = ?";
        $r = $adb->pquery($query, array($account_number));
        if ($adb->num_rows($r) > 0) {
            return true;
        }
        return false;
    }

    static public function WipeAccountData(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);
        $params[] = $account_number;

        if(!empty($params)) {
            $query = "UPDATE vtiger_portfolioinformation p 
                      JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                      JOIN vtiger_positioninformation pos ON pos.account_number = p.account_number
                      JOIN vtiger_positioninformationcf poscf ON poscf.positioninformationid = pos.positioninformationid
                      SET p.total_value = 0, p.market_value = 0, p.cash_value = 0, pos.quantity = 0, pos.current_value = 0
                      WHERE p.account_number IN ({$questions})";
            $adb->pquery($query, $params, true);
        }
    }

    protected function GetConsolidatedBalances(array $account_number, $sdate, $edate, $custodian, $value_field, $date_field){
        global $adb;
        $questions = generateQuestionMarks($account_number);
        $params = array();
        $params[] = $sdate;
        $params[] = $edate;
        $params[] = $account_number;

        $query = "SELECT account_number, {$value_field} AS account_value, {$date_field} AS as_of_date
                  FROM custodian_omniscient.custodian_balances_{$custodian} WHERE {$date_field} BETWEEN ? AND ? 
                  AND account_number IN ({$questions}) ";
        $result = $adb->pquery($query, $params);

        if($adb->num_rows($result) > 0){
            $data = array();
            while($v = $adb->fetchByAssoc($result)){
                $data[] = $v;
            }
            return $data;
        }
        return array();
    }

    static public function ConsolidatedBalancesTD(array $account_number, $sdate, $edate){
        global $adb;
        $values = self::GetConsolidatedBalances($account_number, $sdate, $edate, 'td', 'account_value', 'as_of_date');
        $query = "INSERT INTO consolidated_balances (account_number, account_value, as_of_date)
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE account_value = VALUES(account_value)";

        if(sizeof($values) > 0) {
            foreach($values AS $k => $v){
                $adb->pquery($query, array($v['account_number'], $v['account_value'], $v['as_of_date']));
            }
        }
    }

    static public function ConsolidatedBalancesFidelity(array $account_number, $sdate, $edate){
        global $adb;
        $values = self::GetConsolidatedBalances($account_number, $sdate, $edate, 'fidelity', 'net_worth', 'as_of_date');
        $query = "INSERT INTO consolidated_balances (account_number, account_value, as_of_date)
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE account_value = VALUES(account_value)";

        if(sizeof($values) > 0) {
            foreach($values AS $k => $v){
                $adb->pquery($query, array($v['account_number'], $v['account_value'], $v['as_of_date']));
            }
        }
    }

    static public function ConsolidatedBalances(array $account_number, $sdate, $edate){
        global $adb;
        $td = self::GetConsolidatedBalances($account_number, $sdate, $edate, 'td', 'account_value', 'as_of_date');
        $fidelity = self::GetConsolidatedBalances($account_number, $sdate, $edate, 'fidelity', 'net_worth', 'as_of_date');
        $schwab = self::GetConsolidatedBalances($account_number, $sdate, $edate, 'schwab', 'account_value', 'as_of_date');
        $pershing = self::GetConsolidatedBalances($account_number, $sdate, $edate, 'pershing', 'net_worth', 'date');

        $values = array_merge($td, $fidelity, $schwab, $pershing);

        $query = "INSERT INTO consolidated_balances (account_number, account_value, as_of_date)
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE account_value = VALUES(account_value)";

        if(sizeof($values) > 0) {
            foreach($values AS $k => $v){
                $adb->pquery($query, array($v['account_number'], $v['account_value'], $v['as_of_date']));
            }
        }
    }

    static public function UpdateAccountDataFromCustodian(array $account_number){
        $copy = new CustodianToOmniTransfer($account_number);

        $copy->UpdatePortfolios();
        $copy->CreateSecurities();
#        $copy->UpdateSecurities();
        $copy->CreatePositions();
#        $copy->UpdatePositions();m
    }

    public static function getInstanceSetting($setting_name, $match_check=null){
        global $adb;
        $params = array();
        $params[] = $setting_name;

        if($match_check != null) {
            $and = " AND match_check = ?";
            $params[] = $match_check;
        }

        $query = "SELECT match_result
                  FROM vtiger_instance_settings 
                  WHERE setting_name = ? {$and}";
        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'match_result');
        return 0;
    }

    /**
     * Returns a list of TD accounts which positions total different than their balances
     * @return array
     */
    public static function GetDifferentValuesVsPositionsTD(){
        global $adb;
        $accounts = array();
        $account_values = array();
        $different_accounts = array();

        $query = "SELECT account_number, total_value, stated_value_date, origination
                  FROM vtiger_portfolioinformation 
                  JOIN vtiger_portfolioinformationcf USING(portfolioinformationid)
                  WHERE origination = 'TD'";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $accounts[] = $x['account_number'];
                $account_values[$x['account_number']] = $x['total_value'];
            }
        }

        $questions = generateQuestionMarks($accounts);
        $query = "SELECT account_number, SUM(current_value) AS pos_value
                  FROM vtiger_positioninformation
                  WHERE account_number IN ({$questions})
                  GROUP BY account_number";
        $result = $adb->pquery($query, array($accounts));

        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $dif = $account_values[$x['account_number']] - $x['pos_value'];
                if(abs($dif) > 10){
#                    echo $x['account_number'] . ' value is ' . $account_values[$x['account_number']] . ' and positions value is ' . $x['pos_value'] . '<br />';
                    $different_accounts[] = $x['account_number'];
                }
            }
        }
        return $different_accounts;
    }

    static public function UpdateCashValueField($account_number){

    }
}
