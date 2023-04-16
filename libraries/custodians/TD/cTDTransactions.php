<?php

require_once("libraries/custodians/cCustodian.php");
require_once("libraries/Reporting/ReportCommonFunctions.php");

spl_autoload_register(function ($className) {
    if (file_exists("libraries/EODHistoricalData/$className.php")) {
        include_once "libraries/EODHistoricalData/$className.php";
    }
});

class cTDTransactionsData{
    /*CUSTODIAN_TRANSACTIONS_TD*/
    public $transaction_id, $advisor_rep_code, $file_date, $account_number, $transaction_code, $cancel_status_flag, $symbol, $security_code;
    public $trade_date, $quantity, $net_amount, $principal, $broker_fee, $other_fee, $settle_date, $from_to_account, $account_type;
    public $accrued_interest, $comment, $closing_method, $filename, $insert_date, $dupe_saver_id;

    /*TDMAPPING*/
    public $id, $transaction_type, $transaction_activity, $omniscient_category, $omniscient_activity, $operation, $stopping_point, $affects_total, $affects_performance;

    /*CUSTOM*/
    public $price;//This gets calculated separately

    public function __construct($data){
        /*CUSTODIAN_TRANSACTIONS_TD*/
        $this->transaction_id = $data['transaction_id'];
        $this->advisor_rep_code = $data['advisor_rep_code'];
        $this->file_date = $data['file_date'];
        $this->account_number = $data['account_number'];
        $this->transaction_code = $data['transaction_code'];
        $this->cancel_status_flag = $data['cancel_status_flag'];
        $this->symbol = $data['symbol'];
        $this->security_code = $data['security_code'];
        $this->trade_date = $data['trade_date'];
        $this->quantity = $data['quantity'];
        $this->net_amount = $data['net_amount'];
        $this->principal = $data['principal'];
        $this->broker_fee = $data['broker_fee'];
        $this->other_fee = $data['other_fee'];
        $this->settle_date = $data['settle_date'];
        $this->from_to_account = $data['from_to_account'];
        $this->account_type = $data['account_type'];
        $this->accrued_interest = $data['accrued_interest'];
        $this->comment = $data['comment'];
        $this->closing_method = $data['closing_method'];
        $this->filename = $data['filename'];
        $this->insert_date = $data['insert_date'];
        $this->dupe_saver_id = $data['dupe_saver_id'];

        /*TDMAPPING*/
        $this->id = $data['id'];
        $this->transaction_type = $data['transaction_type'];
        $this->transaction_activity = $data['transaction_activity'];
        $this->omniscient_category = $data['omniscient_category'];
        $this->omniscient_activity = $data['omniscient_activity'];
        $this->operation = $data['operation'];
        $this->stopping_point = $data['stopping_point'];
        $this->affects_total = $data['affects_total'];
        $this->affects_performance = $data['affects_performance'];

        /*CUSTOM*/
        $this->price = $data['price'];

    }
}

/**
 * Class cTDPortfolios
 * This class allows the pulling of data from the custodian database
 */
class cTDTransactions extends cCustodian
{
    use tTransactions;
    protected $transactions_data;//Holds the pricing information
    protected $columns;

    /**
     * cTDPortfolios constructor.
     * @param string $custodian_name
     * @param string $database
     * @param string $module
     * @param string $transactions_table
     * @param string $table (REFERS TO BALANCE TABLE)
     */
    public function __construct(string $custodian_name, string $database, string $module,
                                string $portfolio_table, string $transactions_table, array $rep_codes, $columns=array("*")){
        $this->name = $custodian_name;
        $this->database = $database;
        $this->module = $module;
        $this->portfolio_table = $portfolio_table;
        $this->table = $transactions_table;
        $this->columns = $columns;
        if(!empty($rep_codes)) {
            $this->SetRepCodes($rep_codes);
        }
    }
    /**
     * Returns an associative array of all requested transactions as of the given date
     * @param null $date
     * @return mixed
     */
    public function GetTransactionsDataForDate($date=null){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->account_numbers);
        $params[] = $this->account_numbers;

        if (empty($this->columns))
            $fields = "*";
        else {
            $fields = implode ( ", ", $this->columns );
        }

        if(!$date)
            $date = $this->GetLatestTransactionsDate("trade_date");
        $params[] = $date;


        $query = "SELECT {$fields} FROM {$this->database}.{$this->table} t
                  WHERE account_number IN ({$questions}) AND trade_date = ?";
        $result = $adb->pquery($query, $params, true);

        if ($adb->num_rows($result) > 0) {
            while ($r = $adb->fetchByAssoc($result)) {
                $this->transactions_data[$r['account_number']] = $r;
            }
        }

        $this->SetupTransactionComparisons($date, $date);
        return $this->transactions_data;
    }

    /**
     * Returns an associative array of all requested transactions between the given dates
     * @param null start
     * @param null end
     * @return mixed
     */
    public function GetTransactionsDataBetweenDates($start, $end){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->account_numbers);
        $params[] = $this->account_numbers;
        $params[] = $start;
        $params[] = $end;

        if (empty($this->columns))
            $fields = "*";
        else {
            $fields = implode ( ", ", $this->columns );
        }

        $query = "DROP TABLE IF EXISTS BeforeMapping";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE BeforeMapping
                  SELECT {$fields} FROM {$this->database}.{$this->table} t
                  JOIN {$this->database}.tdmapping m ON m.id = t.transaction_code
                  JOIN vtiger_modsecurities ms ON ms.security_symbol = t.symbol
                  JOIN vtiger_modsecuritiescf mscf USING (modsecuritiesid) 
                  WHERE account_number IN ({$questions}) AND trade_date BETWEEN ? AND ?";
        $adb->pquery($query, $params, true);

        $query = "UPDATE BeforeMapping bm
                  SET bm.symbol = 'TDCASH' WHERE bm.symbol = '' OR bm.symbol IS NULL";
        $adb->pquery($query, array());

        $fields = preg_replace('/\b([(t.|ms.|m.|cf.|pr.)]{1,})\b/', '', $fields);

        $query = "SELECT {$fields} FROM BeforeMapping t";
        $result = $adb->pquery($query, array(), true);

        /*
        $query = "SELECT {$fields} FROM {$this->database}.{$this->table} 
                  WHERE account_number IN ({$questions}) AND trade_date BETWEEN ? AND ?";
        $result = $adb->pquery($query, $params, true);
*/
        if($adb->num_rows($result) > 0) {
            while ($r = $adb->fetchByAssoc($result)) {
                if($r['quantity'] == 0 OR empty($r['quantity']))
                    $r['quantity'] = $r['net_amount'];

                if($r['quantity'] != 0 AND !empty($r['quantity']))
                    $r['price'] = $r['net_amount'] / $r['quantity'];//We set the price here so it calculates the buy price
                else
                    $r['price'] = 1;//Set the price to 1 if we can't figure it out (no net amount/quantity)

                //If net amount hasn't been set and the transaction code is REC or DEL
                if((empty($r['net_amount']) OR $r['net_amount'] == 0) AND in_array($r['transaction_code'], array('REC','DEL'))){
                    $query = "INSERT IGNORE INTO vtiger_problem_accounts (account_number, custodian, problem, problem_id)
                              VALUES (?, ?, ?, ?)";
                    $adb->pquery($query, array($r['account_number'], "TD", 'transactions_no_net_amount', $r['symbol']));
                }
                    /*
                    $query = "SELECT security_price_adjustment, pr.price
                              FROM vtiger_modsecurities m
                              JOIN vtiger_modsecuritiescf cf USING (modsecuritiesid)
                              JOIN {$this->database}.custodian_prices_td pr ON m.security_symbol = pr.symbol AND pr.date = (SELECT date FROM {$this->database}.custodian_prices_td WHERE date < ? AND symbol = m.security_symbol ORDER BY date DESC LIMIT 1)
                              WHERE security_symbol = ?";//Get what we know from modsecurities, take the end of day price from custodian database
                    $price_result = $adb->pquery($query, array($r['trade_date'], $r['symbol']), true);

                    if($adb->num_rows($price_result) > 0){
                        $adjustment = $adb->query_result($price_result, 0, 'security_price_adjustment');
                        $price = $adb->query_result($price_result, 0, 'price');
                        $net_amount = $price * $adjustment * $r['quantity'];
                        $r['price'] = $price;
                        $r['net_amount'] = $net_amount;
                    }
                }*/
/*                if(strtoupper($r['symbol']) == 'AAPL') {
                    print_r($r);
                    exit;
                }*/

                $this->transactions_data[$r['account_number']][$r['transaction_id']] = $r;
            }
        }
        $this->SetupTransactionComparisons($start, $end);
        return $this->transactions_data;
    }

    /**
     * Returns the transactions_data variable that was filled in from the last retrieve
     * @return mixed
     */
    public function GetSavedTransactionsData(){
        return $this->transactions_data;
    }

    /**
     * Using the cTDTransactionsData class, create the portfolios.  Used with a pre-filled in cTDPortfolioData class (done manually)
     * @param cTDPortfolioData $data
     * @throws Exception
     */
    public function CreateNewTransactionUsingcTDTransactionsData(cTDTransactionsData $data){
        if(!$this->DoesTransactionExistInCRM($data->transaction_id)) {//If the transaction doesn't exist yet, create it (uses custodian transaction ID)
            $crmid = $this->UpdateEntitySequence();
            $owner = $this->GetAccountOwnerFromAccountNumber($data->account_number);

            $this->FillEntityTable($crmid, $owner, $data);
            $this->FillTransactionTable($crmid, $data);
            $this->FillTransactionCFTable($crmid, $data);
        }
    }

    /**
     * Auto creates the transaction's based on the data loaded into the $transactions_data member.  If the transaction exists in this data, it will be created
     * @param array $account_numbers
     */
    public function CreateNewTransactionsFromTransactionData(array $missing_account_data){
        if(isset($missing_account_data)) {
            foreach ($missing_account_data AS $account_number => $v) {
                foreach ($v AS $a => $transaction_id) {
                    $data = $this->transactions_data[$account_number][$transaction_id];
                    if (isset($data)) {
                        $tmp = new cTDTransactionsData($data);
                        StatusUpdate::UpdateMessage("TDUPDATER", "Creating Transactions for {$account_number} " . $tmp->trade_date . ' - ' . $tmp->symbol);
                        $this->CreateNewTransactionUsingcTDTransactionsData($tmp);
                    }
                }
            }
        }
    }

    /**
     * Auto updates the transaction's based on the data loaded into the $transaction_data member.
     * @param array $account_numbers
     */
    public function UpdateTransactionsFromTransactionsData(array $transaction_account_data){
        if(isset($transaction_account_data)) {
            foreach ($transaction_account_data AS $k => $v) {
                foreach ($v AS $a => $transaction) {
                    $data = $this->transactions_data[$k][$a];
                    if (isset($data)) {
                        $tmp = new cTDTransactionsData($data);
                        $this->UpdateTransactionsUsingcTDTransactionsData($tmp);
                    }
                }
            }
        }
    }

    /**
     * Create the new entity in the crmentity table
     * @param $crmid
     * @param $owner
     * @param cTDTransactionsData $data
     */
    protected function FillEntityTable($crmid, $owner, cTDTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = 1;
        $params[] = $owner;
        $params[] = 1;
        $params[] = 'Transactions';
        $params[] = $data->comment;
        $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                  VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";
        $adb->pquery($query, $params, true);
    }

    /**
     * Creates data in the vtiger_transactioninformation table
     * @param $crmid
     * @param cTDTransactionsData $data
     */
    protected function FillTransactionTable($crmid, cTDTransactionsData $data){
        global $adb;
        $params = array();
        if($data->operation == null)
            $data->operation = '';
        $params[] = $crmid;
        $params[] = $data->account_number;
        $params[] = $data->symbol;
        $params[] = $data->price;
        $params[] = $data->quantity;
        $params[] = $data->trade_date;
        $params[] = 'TD';
        $params[] = $data->transaction_id;//cloud transaction id
        $params[] = $data->operation;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_transactions (transactionsid, account_number, security_symbol, security_price, quantity, trade_date, 
                              origination, cloud_transaction_id, operation)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    /**
     * Creates data in the vtiger_transactionscf table
     * @param $crmid
     * @param cTDTransactionsData $data
     */
    protected function FillTransactionCFTable($crmid, cTDTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = 'TD';
        $params[] = $data->omniscient_category;
        $params[] = $data->advisor_rep_code;
        $params[] = $data->omniscient_activity;
        $params[] = $data->net_amount;
        $params[] = $data->principal;
        $params[] = $data->broker_fee;
        $params[] = $data->other_fee;
        $params[] = $data->transaction_code;
        $params[] = $data->comment;
        $params[] = $data->filename;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_transactionscf (transactionsid, custodian, transaction_type, rep_code, transaction_activity, net_amount, 
                                                     principal, broker_fee, other_fee, key_mnemonic_description, description, filename)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    static public function UpdateAllTransactionsOperations(array $account_number){
        global $adb;
        $params = array();
        if(!empty($account_number)){
            $questions = generateQuestionMarks($account_number);
            $where = " WHERE account_number IN (?) ";
            $params[] = $questions;
        }
        $query = "UPDATE vtiger_transactions t
                  JOIN custodian_omniscient.custodian_transactions_td ct ON t.cloud_transaction_id = ct.transaction_id
                  JOIN custodian_omniscient.tdmapping m ON m.id = ct.transaction_code
                  SET t.operation = m.operation
                  {$where}";
        $adb->pquery($query, $params, true);
    }

    static public function UpdateAllTransactionsMapping(array $account_number){
        global $adb;
        $params = array();
        if(!empty($account_number)){
            $questions = generateQuestionMarks($account_number);
            $where = " WHERE t.account_number IN (?) ";
            $params[] = $questions;
        }
        $query = "UPDATE vtiger_transactions t
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  JOIN custodian_omniscient.custodian_transactions_td ct ON t.cloud_transaction_id = ct.transaction_id
                  JOIN custodian_omniscient.tdmapping m ON m.id = ct.transaction_code
                  SET t.operation = m.operation, cf.transaction_type = m.omniscient_category, 
                      cf.transaction_activity = m.omniscient_activity
                  {$where}";
        $adb->pquery($query, $params, true);
    }

    /**
     * Update the transaction in the CRM using the cTDTransactionsData class
     * @param cTDTransactionsData $data
     */
    public function UpdateTransactionsUsingcTDTransactionsData(cTDTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $data->quantity_amount_combo;
        $params[] = $data->quantity_amount_combo;
        $params[] = $data->insert_date;
        $params[] = $data->filename;
        $params[] = $data->account_number;
        $params[] = $data->symbol;


/*        $query = "UPDATE vtiger_transactions p
                  JOIN vtiger_positioninformationcf pcf ON pcf.positioninformationid = p.positioninformationid 
                  SET p.quantity = 0, p.current_value = 0 
                  WHERE account_number = ?";
        $adb->pquery($query, array($data->account_number), true);

        $query = "UPDATE vtiger_positioninformation p 
                  JOIN vtiger_positioninformationcf cf USING (positioninformationid)
                  LEFT JOIN vtiger_modsecurities m ON m.security_symbol = p.security_symbol 
                  LEFT JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
                  SET p.quantity = ?, p.current_value = ? * m.security_price * CASE WHEN mcf.security_price_adjustment > 0 
                                                                                    THEN mcf.security_price_adjustment ELSE 1 END 
                                                                                    * CASE WHEN m.asset_backed_factor > 0 
                                                                                    THEN m.asset_backed_factor ELSE 1 END,
                  p.description = m.security_name, cf.security_type = m.securitytype, cf.base_asset_class = mcf.aclass, cf.custodian = 'TD',
                  p.last_price = m.security_price * CASE WHEN mcf.security_price_adjustment > 0 THEN mcf.security_price_adjustment ELSE 1 END,
                  cf.last_update = ?, cf.custodian_source = ?
                  WHERE account_number = ? AND p.security_symbol = ?";
        $adb->pquery($query, $params, true);*/
    }

    static public function CreateNewTransactionsForAccounts(array $account_number, $sdate=null, $edate=null){
		
        global $adb;

        $q1 = array();
        $q1[] = $account_number;
        if($sdate && $edate){
            $and = " AND trade_date BETWEEN ? AND ? ";
            $q1[] = $sdate;
            $q1[] = $edate;
        }

        $account_questions = generateQuestionMarks($account_number);
        $query = "SELECT cloud_transaction_id 
                  FROM vtiger_transactions 
                  WHERE origination = 'TD'
                  AND account_number IN ({$account_questions})
                  {$and} ";

        $result = $adb->pquery($query, $q1);
        $params = array();
        $cloud_ids = array();
        $transaction_ids = "";

        if($adb->num_rows($result) > 0){
           
			/*while($v = $adb->fetchByAssoc($result)){
                $cloud_ids[] = $v['cloud_transaction_id'];
            }
            $cloud_id_questions = generateQuestionMarks($cloud_ids);
            $transaction_ids = " t.transaction_id NOT IN ({$cloud_id_questions}) ";
            $params[] = $cloud_ids;
			*/
			
			$transaction_ids = "  t.transaction_id NOT IN (SELECT cloud_transaction_id
            FROM vtiger_transactions WHERE origination = 'TD'
            AND account_number IN ({$account_questions})) ";
            
            $params[] = $account_number;
			
			
        }

        if(strlen($transaction_ids) == 0){
            $transaction_ids = " t.transaction_id != 0 ";
        }

        $params[] = $account_number;

        if($sdate && $edate){
            $params[] = $sdate;
            $params[] = $edate;
        }

        $query = "SELECT transaction_id, advisor_rep_code, file_date, account_number, transaction_code, omniscient_category, omniscient_activity, cancel_status_flag, symbol, security_code, trade_date, quantity, net_amount, 000000000.0000000 AS price, principal, broker_fee, other_fee, settle_date, from_to_account, account_type, accrued_interest, comment, closing_method, filename, insert_date, dupe_saver_id
                  FROM custodian_omniscient.custodian_transactions_td t 
                  JOIN custodian_omniscient.tdmapping m ON m.id = t.transaction_code 
                  WHERE {$transaction_ids}
                  AND t.account_number IN ({$account_questions})
                  {$and}
                  GROUP BY transaction_id";
        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");
                $v['ownerid'] = PortfolioInformation_Module_Model::GetAccountOwnerFromAccountNumber($v['account_number']);

                if (is_null($v['net_amount']))
                    $v['net_amount'] = 0;

                if (is_null($v['quantity']) || $v['quantity'] == 0)
                    $v['quantity'] = $v['net_amount'];

                if (!is_null($v['quantity']) && $v['quantity'] != 0)
                    $v['price'] = $v['net_amount'] / $v['quantity'];

                if (is_null($v['price']))
                    $v['price'] = 1;

                if ($v['net_amount'] == 0) {
                    switch (strtoupper($v['transaction_code'])) {
                        case 'REC':
                        case 'DEL':
                            $query = "SELECT pr.price, pr.price * security_price_adjustment * {$v['quantity']} AS net_amount
                                  FROM custodian_omniscient.custodian_prices_td pr
                                  JOIN vtiger_modsecurities m ON m.security_symbol = pr.symbol 
                                  JOIN vtiger_modsecuritiescf cf USING (modsecuritiesid) 
                                  WHERE pr.symbol = ? 
                                  AND pr.date = (SELECT date 
                                                 FROM custodian_omniscient.custodian_prices_td 
                                                 WHERE date < ? 
                                                 AND symbol = ? 
                                                 ORDER BY date DESC LIMIT 1)";
                            $price_result = $adb->pquery($query, array($v['symbol'], $v['trade_date'], $v['symbol']));
                            if ($adb->num_rows($price_result) > 0) {
                                $v['price'] = $adb->query_result($price_result, 0, 'price');
                                $v['net_amount'] = $adb->query_result($price_result, 0, 'net_amount');
                            }
                    }
                }

                $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                          VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";
                $adb->pquery($query, array($v['crmid'], 1, $v['ownerid'], $v['ownerid'], 'Transactions', $v['comment']));

                $query = "INSERT INTO vtiger_transactions (transactionsid, account_number, security_symbol, security_price, quantity, trade_date, origination, cloud_transaction_id)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], $v['account_number'], $v['symbol'], $v['price'], $v['quantity'], $v['trade_date'],
                    'TD', $v['transaction_id']));

                $query = "INSERT INTO vtiger_transactionscf (transactionsid, custodian, transaction_type, rep_code, transaction_activity, net_amount, principal, broker_fee, other_fee, description, filename)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], 'TD', $v['omniscient_category'], $v['advisor_rep_code'], $v['omniscient_activity'], $v['net_amount'], $v['principal'],
                    $v['broker_fee'], $v['other_fee'], $v['comment'], $v['filename']));
            }
        }
    }

    static public function UpdateTransactionsForAccounts(array $account_number, $sdate=null, $edate=null){
        global $adb;

        $q1 = array();
        $q1[] = $account_number;
        if($sdate && $edate){
            $and = " AND t.trade_date BETWEEN ? AND ? ";
            $q1[] = $sdate;
            $q1[] = $edate;
        }

        $account_questions = generateQuestionMarks($account_number);
        $query = "SELECT cloud_transaction_id 
                  FROM vtiger_transactions t
                  WHERE origination = 'TD'
                  AND account_number IN ({$account_questions})
                  {$and} ";

        $result = $adb->pquery($query, $q1);
        $params = array();
        $cloud_ids = array();
        $transaction_ids = "";

        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $cloud_ids[] = $v['cloud_transaction_id'];
            }
            $cloud_id_questions = generateQuestionMarks($cloud_ids);
            $transaction_ids = " f.transaction_id IN ({$cloud_id_questions}) ";
            $params[] = $cloud_ids;
        }

        if(strlen($transaction_ids) == 0){
            $transaction_ids = " f.transaction_id != 0 ";
        }

        $params[] = $account_number;

        if($sdate && $edate){
            $params[] = $sdate;
            $params[] = $edate;
        }

        $query = "SELECT m.operation, cf.custodian_control_number, pcf.production_number, m.omniscient_category, m.omniscient_activity, 
                         f.transaction_code, t.security_price, t.quantity, f.net_amount, f.symbol, f.transaction_id, f.account_number,
                         t.trade_date, mscf.security_price_adjustment AS pricing_factor, f.cancel_status_flag, f.comment
                  FROM vtiger_transactions t
                  JOIN vtiger_transactionscf cf ON t.transactionsid = cf.transactionsid
                  JOIN custodian_omniscient.custodian_transactions_td f ON f.transaction_id = t.cloud_transaction_id
                  JOIN custodian_omniscient.tdmapping m ON m.id = f.transaction_code
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  LEFT JOIN vtiger_modsecurities ms ON ms.security_symbol = f.symbol
                  LEFT JOIN vtiger_modsecuritiescf mscf ON ms.modsecuritiesid = mscf.modsecuritiesid
                  LEFT JOIN custodian_omniscient.custodian_prices_td pr ON pr.symbol = f.symbol AND pr.date = t.trade_date
                  LEFT JOIN vtiger_portfolioinformation p ON p.account_number = t.account_number
                  LEFT JOIN vtiger_portfolioinformationcf pcf ON pcf.portfolioinformationid = p.portfolioinformationid
                  WHERE {$transaction_ids}
                  AND t.account_number IN ({$account_questions})
                  {$and}
                  GROUP BY f.transaction_id";
        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                if($v['net_amount'] == '' || $v['net_amount'] == 0 || is_null($v['net_amount']))
                    $v['net_amount'] = $v['security_price'] * $v['quantity'];

                if(strtolower($v['transaction_activity']) == 'management fee' && is_null($v['net_maount']))
                    $v['net_amount'] = $v['quantity'];

                if(strtoupper($v['omniscient_activity']) == "RECEIPT OF SECURITIES"){
                    $v['price'] = $v['close_price'] = self::GetBestReceiptOfSecurityPrice($v['symbol'], $v['trade_date']);
                    $v['net_amount'] = $v['quantity'] * $v['pricing_factor'] * $v['close_price'];
#                    echo $v['net_amount'] . ' = ' . $v['quantity'] . ' * ' . $v['pricing_factor'] . ' * ' . $v['close_price'] . '<br />';
                }

                switch(strtoupper($v['cancel_status_flag'])){
                    case "Y":
                        if($v['operation'] == '-')
                            $v['operation'] = '';
                        elseif(($v['operation'] == '') || $v['operation'] == '+')
                            $v['operation'] = '-';

#                    if(strtolower($v['omniscient_category']) == 'flow'){
#                        $v['omniscient_activity'] = 'Cancelled Transaction';
#                    }
                }

                if(strpos($v['comment'], '|JNJN')){
                    $v['omniscient_category'] = 'Journal';
                }

                $query = "UPDATE vtiger_transactions t 
                          JOIN vtiger_transactionscf cf USING (transactionsid)
                          SET t.operation = ?, cf.custodian_control_number = ?, cf.transaction_type = ?, cf.transaction_activity = ?,
                              cf.key_mnemonic_description = ?, cf.net_amount = ?
                          WHERE t.cloud_transaction_id = ? AND t.account_number = ?";

                $adb->pquery($query, array($v['operation'], $v['custodian_control_number'], $v['omniscient_category'], $v['omniscient_activity'],
                                           $v['key_mnemonic_description'], $v['net_amount'], $v['transaction_id'], $v['account_number']), true);
            }
        }
    }

    private function GetBestReceiptOfSecurityPrice($symbol, $date){
#        $price = cTDPrices::GetBestKnownPriceBeforeDate($symbol, $date);
        $price = cTDPrices::GetPriceAsOfDate($symbol, $date);

        if($price == false){
            $fix = new CustodianWriter();
            $sdate = GetDateMinusMonthsSpecified($date, 1);
            $edate = $date;
            $fix->WriteEodToCustodian($symbol, $sdate, $edate, "TD");//Only update if there is no price for previous day
        }else{
            return $price;
        }
        $price = cTDPrices::GetBestKnownPriceBeforeDate($date, $symbol);
        return $price;
    }

    static public function GetTransactionCount(array $account_number){
        global $adb;

        if(empty($account_number))
            return null;

        $questions = generateQuestionMarks($account_number);

        $query = "SELECT account_number, COUNT(*) as count
                  FROM custodian_omniscient.custodian_transactions_td 
                  WHERE account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_number));

        if($adb->num_rows($result) > 0){
            $data = array();
            while($v = $adb->fetchByAssoc($result)){
                $data[$v['account_number']] = $v['count'];
            }
            return $data;
        }
        return null;
    }

    static public function CreateTransactionsInCustodian($account_number, $symbol, $trade_date,
                                                         $type, $amount, $quantity, $price){
        global $adb;
/**THIS HAS NOT BEEN TESTED WITH TD BUT SHOULD WORK AS IS ... DOUBLE CHECK FIRST**/
        if($type == 1){
            $transaction_type = 'CKR';
        }else{
            $transaction_type = 'REC';
        }
        $query = "INSERT INTO custodian_omniscient.custodian_transactions_td (account_number, 
                              symbol, trade_date, net_amount, quantity, transaction_code)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $adb->pquery($query, array($account_number, $symbol, $trade_date, $amount, $quantity, $transaction_type));

        $query = "SELECT LAST_INSERT_ID() AS id";
        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0)
            $id = $adb->query_result($result, 0);

        $query = "INSERT INTO custodian_omniscient.omni_created_transactions (transaction_id, custodian)
                  VALUES (?, ?)";
        $adb->pquery($query, array($id, 'Fidelity'));
    }
}
