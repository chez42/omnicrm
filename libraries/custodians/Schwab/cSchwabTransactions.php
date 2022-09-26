<?php

require_once("libraries/custodians/cCustodian.php");

class cSchwabTransactionsData{
    public  $transaction_id, $account_number, $trade_date, $transaction_code, $security_type, $symbol, $dollar_amount, $account_type, $quantity,
        $brokerage_fee, $unit_cost, $accrued_interest, $broker_code, $filename, $custodian_id, $master_account_number, $master_account_name,
        $business_date, $account_title_line1, $account_title_line2, $account_title_line3, $account_registration, $product_code,
        $product_category_code, $tax_code, $legacy_security_type, $ticker_symbol, $industry_ticker_symbol, $cusip, $schwab_security_number,
        $item_issue_id, $rule_set_suffix_id, $isin, $sedol, $options_display_symbol, $underlying_ticker_symbol,
        $underlying_industry_ticker_symbol, $underlying_cusip, $underlying_schwab_security_number, $underlying_item_issue_id,
        $underlying_rule_set_suffix_id, $underlying_isin, $underlying_sedol, $money_market_code, $transaction_type_code,
        $transaction_subtype_code, $transaction_category, $transaction_source_code, $transaction_source_code_description,
        $transaction_detail_description, $action_code, $transaction_cancel_code, $settlement_date, $transaction_date, $exdividend_date,
        $price, $gross_amount, $debit_credit_indicator, $net_amount, $commission, $exchange_processing_fee, $broker_service_fee,
        $prime_broker_fee, $trade_away_fee, $redemption_fee, $other_fee, $federal_tefra_withholding, $state_tax_withholding,
        $state_receiving_tax, $accounting_rule_code, $order_source_code, $order_number, $trade_order_entry_time_stamp,
        $trade_order_execution_time_stamp, $broker_name, $schwab_from_account, $schwab_to_account, $schwab1_check_number, $sweep_indicator,
        $stock_exchange_code, $interclass_exchange_code, $distribution_rate, $cash_in_lieu_share_quantity, $dividend_interest_share_quantity,
        $cash_in_lieu_rate, $asset_backed_factor, $source_system, $journal_type, $deposit_media, $schwab_cashiering_unique_identifier,
        $recipient_maker_name_line1, $recipient_maker_name_line2, $recipient_maker_name_line3, $frequency, $disbursed_check_number,
        $fed_reference_number, $recipient_maker_account_number, $bank_account_type, $bank_name_part1, $bank_name_part2, $bank_aba_number,
        $intermediary_name, $transaction_check_memo1, $transaction_check_memo2, $retirement_federal_income_tax, $retirement_state_income_tax,
        $retirement_income_tax_state, $publication_time_stamp, $version_marker1, $tips_factor, $closing_price, $version_marker2,
        $transaction_memo, $version_marker3, $closing_price_unfactored, $factor, $factor_date, $file_date, $insert_date, $dupe_flag;

    public  $id, $source_code, $type_code, $subtype_code, $direction, $transaction_activity, $omniscient_category, $omniscient_activity,
        $schwab_category, $operation, $stopping_point, $affects_total, $affects_performance;

    public  $transaction_type;

    public function __construct($data){
        $this->transaction_id = $data['transaction_id'];
        $this->account_number = ltrim($data['account_number'], '0');
        $this->trade_date = $data['trade_date'];
        $this->transaction_code = $data['transaction_code'];
        $this->security_type = $data['security_type'];
        $this->symbol = $data['symbol'];
        $this->dollar_amount = $data['dollar_amount'];
        $this->account_type = $data['account_type'];
        $this->quantity = $data['quantity'];
        $this->brokerage_fee = $data['brokerage_fee'];
        $this->unit_cost = $data['unit_cost'];
        $this->accrued_interest = $data['accrued_interest'];
        $this->broker_code = $data['broker_code'];
        $this->filename = $data['filename'];
        $this->custodian_id = $data['custodian_id'];
        $this->master_account_number = $data['master_account_number'];
        $this->master_account_name = $data['master_account_name'];
        $this->business_date = $data['business_date'];
        $this->account_title_line1 = $data['account_title_line1'];
        $this->account_title_line2 = $data['account_title_line2'];
        $this->account_title_line3 = $data['account_title_line3'];
        $this->account_registration = $data['account_registration'];
        $this->product_code = $data['product_code'];
        $this->product_category_code = $data['product_category_code'];
        $this->tax_code = $data['tax_code'];
        $this->legacy_security_type = $data['legacy_security_type'];
        $this->ticker_symbol = $data['ticker_symbol'];
        $this->industry_ticker_symbol = $data['industry_ticker_symbol'];
        $this->cusip = $data['cusip'];
        $this->schwab_security_number = $data['schwab_security_number'];
        $this->item_issue_id = $data['item_issue_id'];
        $this->rule_set_suffix_id = $data['rule_set_suffix_id'];
        $this->isin = $data['isin'];
        $this->sedol = $data['sedol'];
        $this->options_display_symbol = $data['options_display_symbol'];
        $this->underlying_ticker_symbol = $data['underlying_ticker_symbol'];
        $this->underlying_industry_ticker_symbol = $data['underlying_industry_ticker_symbol'];
        $this->underlying_cusip = $data['underlying_cusip'];
        $this->underlying_schwab_security_number = $data['underlying_schwab_security_number'];
        $this->underlying_item_issue_id = $data['underlying_item_issue_id'];
        $this->underlying_rule_set_suffix_id = $data['underlying_rule_set_suffix_id'];
        $this->underlying_isin = $data['underlying_isin'];
        $this->underlying_sedol = $data['underlying_sedol'];
        $this->money_market_code = $data['money_market_code'];
        $this->transaction_type_code = $data['transaction_type_code'];
        $this->transaction_subtype_code = $data['transaction_subtype_code'];
        $this->transaction_category = $data['transaction_category'];
        $this->transaction_source_code = $data['transaction_source_code'];
        $this->transaction_source_code_description = $data['transaction_source_code_description'];
        $this->transaction_detail_description = $data['transaction_detail_description'];
        $this->action_code = $data['action_code'];
        $this->transaction_cancel_code = $data['transaction_cancel_code'];
        $this->settlement_date = $data['settlement_date'];
        $this->transaction_date = $data['transaction_date'];
        $this->exdividend_date = $data['exdividend_date'];
        $this->price = $data['price'];
        $this->gross_amount = $data['gross_amount'];
        $this->debit_credit_indicator = $data['debit_credit_indicator'];
        $this->net_amount = $data['net_amount'];
        $this->commission = $data['commission'];
        $this->exchange_processing_fee = $data['exchange_processing_fee'];
        $this->broker_service_fee = $data['broker_service_fee'];
        $this->prime_broker_fee = $data['prime_broker_fee'];
        $this->trade_away_fee = $data['trade_away_fee'];
        $this->redemption_fee = $data['redemption_fee'];
        $this->other_fee = $data['other_fee'];
        $this->federal_tefra_withholding = $data['federal_tefra_withholding'];
        $this->state_tax_withholding = $data['state_tax_withholding'];
        $this->state_receiving_tax = $data['state_receiving_tax'];
        $this->accounting_rule_code = $data['accounting_rule_code'];
        $this->order_source_code = $data['order_source_code'];
        $this->order_number = $data['order_number'];
        $this->trade_order_entry_time_stamp = $data['trade_order_entry_time_stamp'];
        $this->trade_order_execution_time_stamp = $data['trade_order_execution_time_stamp'];
        $this->broker_name = $data['broker_name'];
        $this->schwab_from_account = $data['schwab_from_account'];
        $this->schwab_to_account = $data['schwab_to_account'];
        $this->schwab1_check_number = $data['schwab1_check_number'];
        $this->sweep_indicator = $data['sweep_indicator'];
        $this->stock_exchange_code = $data['stock_exchange_code'];
        $this->interclass_exchange_code = $data['interclass_exchange_code'];
        $this->distribution_rate = $data['distribution_rate'];
        $this->cash_in_lieu_share_quantity = $data['cash_in_lieu_share_quantity'];
        $this->dividend_interest_share_quantity = $data['dividend_interest_share_quantity'];
        $this->cash_in_lieu_rate = $data['cash_in_lieu_rate'];
        $this->asset_backed_factor = $data['asset_backed_factor'];
        $this->source_system = $data['source_system'];
        $this->journal_type = $data['journal_type'];
        $this->deposit_media = $data['deposit_media'];
        $this->schwab_cashiering_unique_identifier = $data['schwab_cashiering_unique_identifier'];
        $this->recipient_maker_name_line1 = $data['recipient_maker_name_line1'];
        $this->recipient_maker_name_line2 = $data['recipient_maker_name_line2'];
        $this->recipient_maker_name_line3 = $data['recipient_maker_name_line3'];
        $this->frequency = $data['frequency'];
        $this->disbursed_check_number = $data['disbursed_check_number'];
        $this->fed_reference_number = $data['fed_reference_number'];
        $this->recipient_maker_account_number = $data['recipient_maker_account_number'];
        $this->bank_account_type = $data['bank_account_type'];
        $this->bank_name_part1 = $data['bank_name_part1'];
        $this->bank_name_part2 = $data['bank_name_part2'];
        $this->bank_aba_number = $data['bank_aba_number'];
        $this->intermediary_name = $data['intermediary_name'];
        $this->transaction_check_memo1 = $data['transaction_check_memo1'];
        $this->transaction_check_memo2 = $data['transaction_check_memo2'];
        $this->retirement_federal_income_tax = $data['retirement_federal_income_tax'];
        $this->retirement_state_income_tax = $data['retirement_state_income_tax'];
        $this->retirement_income_tax_state = $data['retirement_income_tax_state'];
        $this->publication_time_stamp = $data['publication_time_stamp'];
        $this->version_marker1 = $data['version_marker1'];
        $this->tips_factor = $data['tips_factor'];
        $this->closing_price = $data['closing_price'];
        $this->version_marker2 = $data['version_marker2'];
        $this->transaction_memo = $data['transaction_memo'];
        $this->version_marker3 = $data['version_marker3'];
        $this->closing_price_unfactored = $data['closing_price_unfactored'];
        $this->factor = $data['factor'];
        $this->factor_date = $data['factor_date'];
        $this->file_date = $data['file_date'];
        $this->insert_date = $data['insert_date'];
        $this->dupe_flag = $data['dupe_flag'];

        $this->id = $data['id'];
        $this->source_code = $data['source_code'];
        $this->type_code = $data['type_code'];
        $this->subtype_code = $data['subtype_code'];
        $this->direction = $data['direction'];
        $this->transaction_activity = $data['transaction_activity'];
        $this->omniscient_category = $data['omniscient_category'];
        $this->omniscient_activity = $data['omniscient_activity'];
        $this->schwab_category = $data['schwab_category'];
        $this->operation = (is_null($data['operation'])) ? '' : $data['operation'];
        $this->stopping_point = $data['stopping_point'];
        $this->affects_total = $data['affects_total'];
        $this->affects_performance = $data['affects_performance'];


        if(strlen($data['omniscient_category']) > 2)
            $this->transaction_type = $data['omniscient_category'];
        else
            $this->transaction_type = $data['transaction_category'];

        if(strlen($data['omniscient_activity'] > 2))
            $this->transaction_activity = $data['omniscient_activity'];
        else
            $this->transaction_activity = $data['transaction_activity'];

        if($data['price'] == 0 && $data['closing_price'] != 0)
            $this->price = $data['closing_price'];
        else
            $this->price = 1;

        $this->quantity = abs($data['quantity']);
        $data['net_amount'] = abs($data['net_amount']);
        $this->gross_amount = abs($data['gross_amount']);

        if(strlen($data['ticker_symbol']) < 2 && strlen($data['cusip']) > 2)
            $this->ticker_symbol = $data['cusip'];

        if(strlen($data['ticker_symbol']) < 2)
            $this->ticker_symbol = 'SCASH';

        if($data['gross_amount'] == 0)
            $this->net_amount = $data['quantity'] * $data['closing_price'] * ModSecurities_Module_Model::GetSecurityPriceAdjustment($data['symbol']);
        else
            $this->net_amount = $data['gross_amount'];
    }
}

/**
 * Class cSchwabPortfolios
 * This class allows the pulling of data from the custodian database
 */
class cSchwabTransactions extends cCustodian
{
    use tTransactions;
    protected $transactions_data;//Holds the pricing information

    protected function FillAccountNumbersFromRepCodes(){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->rep_codes);
        $params[] = $this->rep_codes;

        $query = "SELECT account_number 
                  FROM {$this->database}.{$this->portfolio_table} p 
                  WHERE rep_code IN ({$questions})
                  ORDER BY file_date DESC";

        $result = $adb->pquery($query, array($this->rep_codes), true);
        if($adb->num_rows($result) > 0)
            while($r = $adb->fetchByAssoc($result)){
                $this->account_numbers[] = $r['account_number'];
            }
    }
    /**
     * cSchwabPortfolios constructor.
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

        return $this->transactions_data;
    }

    protected function AccountsWithLeadingZeros(array $account_numbers){
        $tmp = array();
        foreach($account_numbers AS $k => $v){
            $tmp[] = "00" . $v;
        }
        return $tmp;
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
#        $questions = generateQuestionMarks($this->account_numbers);
        $leading_zeros = self::AccountsWithLeadingZeros($this->account_numbers);
        $merged_accounts = array_merge($this->account_numbers, $leading_zeros);
        $questions = generateQuestionMarks($merged_accounts);
        $params[] = $merged_accounts;
        $params[] = $start;
        $params[] = $end;

        if (empty($this->columns))
            $fields = "*";
        else {
            $fields = implode ( ", ", $this->columns );
        }

        $query = "SELECT {$fields} FROM {$this->database}.{$this->table} t
                  JOIN {$this->database}.schwabmapping m ON m.source_code = t.transaction_source_code AND m.type_code = t.transaction_type_code AND m.subtype_code = t.transaction_subtype_code AND m.direction = t.debit_credit_indicator
                  WHERE account_number IN ({$questions}) AND trade_date BETWEEN ? AND ?";
        $result = $adb->pquery($query, $params, true);

        if ($adb->num_rows($result) > 0) {
            while ($r = $adb->fetchByAssoc($result)) {
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
     * Using the cSchwabTransactionsData class, create the portfolios.  Used with a pre-filled in cSchwabPortfolioData class (done manually)
     * @param cSchwabPortfolioData $data
     * @throws Exception
     */
    public function CreateNewTransactionUsingcSchwabTransactionsData(cSchwabTransactionsData $data){
#        print_r($data);exit;
#        echo $data->transaction_id . '<br />';
//        print_r(echo $data->transaction_id);exit;
        if(!$this->DoesTransactionExistInCRM($data->transaction_id)) {//If the transaction doesn't exist yet, create it (uses custodian transaction ID)
#            $crmid = "73957144";
            $crmid = $this->UpdateEntitySequence();
#            echo "CRMID: {$crmid} <br />";

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
        if(!empty($missing_account_data)) {
            foreach ($missing_account_data AS $account_number => $v) {
                foreach ($v AS $a => $transaction_id) {
                    /*                    print_r($this->transactions_data[$account_number]); echo " .. ";
                                        echo $transaction_id;exit;
                                        print_r($this->transactions_data[$account_number][$transaction_id]);exit;*/
                    $data = $this->transactions_data[$account_number][$transaction_id];
                    if (!empty($data)) {
                        $tmp = new cSchwabTransactionsData($data);
                        $this->CreateNewTransactionUsingcSchwabTransactionsData($tmp);
                    }
                }
            }
        }
    }

    /**
     * Auto updates the transaction's based on the data loaded into the $transaction_data member.
     * @param array $account_numbers
     */
    public function UpdateTransactionsFromTransactionsData(){
        if(!empty($this->transactions_data)){
            foreach ($this->transactions_data AS $k => $v) {
                foreach ($v AS $a => $transaction) {
                    $data = $this->transactions_data[$k][$a];
                    if (!empty($data)) {
                        $tmp = new cSchwabTransactionsData($data);
                        $this->UpdateTransactionsUsingcSchwabTransactionsData($tmp);
                    }
                }
            }
        }
    }

    /**
     * Create the new entity in the crmentity table
     * @param $crmid
     * @param $owner
     * @param cSchwabTransactionsData $data
     */
    protected function FillEntityTable($crmid, $owner, cSchwabTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = 1;
        $params[] = $owner;
        $params[] = 1;
        $params[] = 'Transactions';
        $params[] = $data->transaction_detail_description;
        $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                  VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";
        $adb->pquery($query, $params, true);
    }

    /**
     * Creates data in the vtiger_transactioninformation table
     * @param $crmid
     * @param cSchwabTransactionsData $data
     */
    protected function FillTransactionTable($crmid, cSchwabTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = $data->account_number;
        $params[] = $data->ticker_symbol;
        $params[] = $data->price;
        $params[] = $data->quantity;
        $params[] = $data->trade_date;
        $params[] = 'SCHWAB';
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
     * @param cSchwabTransactionsData $data
     */
    protected function FillTransactionCFTable($crmid, cSchwabTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = 'SCHWAB';
        $params[] = $data->transaction_type;
        $params[] = $data->transaction_activity;
        $params[] = $data->net_amount;
        $params[] = $data->broker_service_fee + $data->prime_broker_fee;
        $params[] = $data->commission + $data->other_fee;
        $params[] = $data->debit_credit_indicator;
        $params[] = $data->transaction_detail_description;
        $params[] = $data->filename;
        $params[] = $data->transaction_source_code;
        $params[] = $data->transaction_type_code;
        $params[] = $data->transaction_subtype_code;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_transactionscf (transactionsid, custodian, transaction_type, transaction_activity, net_amount, broker_fee, 
                                                     other_fee, schwab_direction, description, filename, key_mnemonic_description, 
                                                     transaction_key_code_description, transaction_code_description)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    /**
     * Update the transaction in the CRM using the cSchwabTransactionsData class
     * @param cSchwabTransactionsData $data
     */
    public function UpdateTransactionsUsingcSchwabTransactionsData(cSchwabTransactionsData $data){
        global $adb;
        $params = array();
        $params[] = $data->quantity;
        $params[] = $data->net_amount;
        $params[] = $data->symbol;
        $params[] = $data->net_amount;
        $params[] = $data->transaction_id;

        $query = "UPDATE vtiger_transactions t
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  SET t.quantity = ?, t.net = ?, t.security_symbol = ?, cf.net_amount = ?
                  WHERE cloud_transaction_id = ?";
        $adb->pquery($query, $params);
    }

    public function RemoveDupesByZeroingOut(){
        global $adb;
        if(empty($this->account_numbers))
            return;

        $leading_zeros = $this->AccountsWithLeadingZeros($this->account_numbers);
        $merged_accounts = array_merge($this->account_numbers, $leading_zeros);
        $questions = generateQuestionMarks($merged_accounts);

        foreach($merged_accounts AS $k => $acct_num){
            $sdate = PortfolioInformation_Module_Model::GetFirstTransactionDate($acct_num);
            if(!isset($sdate))
                continue;
            $query = "DROP TABLE IF EXISTS TradeDates";
            $adb->pquery($query, array(), true);
            $query = "DROP TABLE IF EXISTS DupeDays";
            $adb->pquery($query, array(), true);

            $query = "CREATE TEMPORARY TABLE TradeDates
                      SELECT trade_date, master_account_number, COUNT(*) AS count
                      FROM custodian_omniscient.custodian_transactions_schwab
                      WHERE account_number=?
                      AND trade_date BETWEEN ? AND NOW()
                      GROUP BY master_account_number, trade_date
                      ORDER BY trade_date DESC";
            $adb->pquery($query, array('00' . $acct_num, $sdate), true);

            $query = "CREATE TEMPORARY TABLE DupeDays
                      SELECT trade_date, COUNT(*) AS count
                      FROM TradeDates
                      GROUP BY trade_date
                      ORDER BY trade_date DESC";
            $adb->pquery($query, array(), true);

            $query = "DELETE FROM DupeDays WHERE count <= 1";
            $adb->pquery($query, array(), true);

            $query = "SELECT trade_date FROM DupeDays";
            $result = $adb->pquery($query, array(), true);

            $dates = array();
            if($adb->num_rows($result) > 0){
                while($r = $adb->fetchByAssoc($result)){
                    $dates[] = $r['trade_date'];
                }
            }

            foreach($dates AS $k => $v) {
                $query = "DROP TABLE IF EXISTS NumTransactions";
                $adb->pquery($query, array(), true);
                $query = "DROP TABLE IF EXISTS ToRemove";
                $adb->pquery($query, array(), true);

                $query = "CREATE TEMPORARY TABLE NumTransactions
                          SELECT master_account_number, COUNT(*) num_transactions
                          FROM custodian_omniscient.custodian_transactions_schwab
                          WHERE account_number = ?
                                AND trade_date = ?
                          GROUP BY master_account_number
                          ORDER BY COUNT(*) DESC";
                $adb->pquery($query, array('00'.$acct_num, $v), true);

                $query = "CREATE TEMPORARY TABLE ToRemove
                          SELECT transaction_id, master_account_number
                          FROM custodian_omniscient.custodian_transactions_schwab
                          WHERE master_account_number IN (SELECT master_account_number FROM NumTransactions)
                          AND trade_date = ?
                          AND account_number = ?";
                $adb->pquery($query, array($v, '00'.$acct_num), true);

                $query = "SELECT master_account_number FROM ToRemove GROUP BY master_account_number ORDER BY master_account_number ASC LIMIT 1";
                $rresult = $adb->pquery($query, array(), true);
                if($adb->num_rows($rresult) > 0) {
                    $query = "DELETE FROM ToRemove WHERE master_account_number = ?";
                    $adb->pquery($query, array($adb->query_result($rresult, 0, 'master_account_number')));
                    $query = "UPDATE vtiger_transactions t
                              JOIN vtiger_transactionscf cf USING (transactionsid)
                              SET quantity = 0, security_price = 0, net_amount = 0
                              WHERE cloud_transaction_id IN (SELECT transaction_id FROM ToRemove)";
                    $adb->pquery($query, array(), true);
                }

                /*                if($adb->num_rows($dresult) > 0){
                                    $to_zero = array();
                                    while($r = $adb->fetchByAssoc($dresult)){
                                        $to_zero[] = $r['transaction_id'];
                                    }
                                    $ids = generateQuestionMarks($to_zero);
                                    if(!empty($to_zero)){
                                        print_r($to_zero);
                                        $query = "UPDATE vtiger_transactions t
                                                  JOIN vtiger_transactionscf cf USING (transactionsid)
                                                  SET quantity = 0, security_price = 0, net_amount = 0
                                                  WHERE cloud_transaction_id IN ({$ids})";
                                        $adb->pquery($query, array($to_zero));
                                        echo "Trying for " . $acct_num . ' aka ' . '00' . $acct_num . '<br />';
                                    }
                                }*/
            }
        }
    }

    static public function PrependZero(array $account_number){
        $accounts = array();
        foreach($account_number AS $k => $v){
            $accounts[] = $v;
            $accounts[] = "00{$v}";
        }
        return $accounts;
    }

    static public function CreateNewTransactionsForAccounts(array $account_number){
        
		global $adb;
		
		//$account_no = $account_number;
		
        $account_number = self::PrependZero($account_number);

        $account_questions = generateQuestionMarks($account_number);
       
	    $query = "SELECT cloud_transaction_id 
                  FROM vtiger_transactions 
                  WHERE origination = 'Schwab'
                  AND account_number IN ({$account_questions})";
        
		$result = $adb->pquery($query, array($account_number));
        
		$params = array();
        
		//$cloud_ids = array();
        
		$transaction_ids = "";
        
		$params[] = $account_number;

        if($adb->num_rows($result) > 0){
            
			/*while($v = $adb->fetchByAssoc($result)){
                $cloud_ids[] = $v['cloud_transaction_id'];
            }
            $cloud_id_questions = generateQuestionMarks($cloud_ids);
			*/
			
            $transaction_ids = " AND t.transaction_id NOT IN (SELECT cloud_transaction_id 
            FROM vtiger_transactions WHERE origination = 'Schwab'
            AND account_number IN ({$account_questions})) ";
				  
            $params[] = $account_number;
        }

        $query = "SELECT 1 AS ownerid, 
                         CASE WHEN m.omniscient_category > '' THEN m.omniscient_category ELSE t.transaction_category END AS transaction_type, 
                         CASE WHEN m.omniscient_activity > '' THEN m.omniscient_activity ELSE m.transaction_activity END AS transaction_activity, 
                         transaction_id, TRIM(LEADING '0' FROM t.account_number) AS account_number, trade_date, transaction_code, security_type, symbol, dollar_amount, t.account_type, ABS(quantity) AS quantity, 
                         brokerage_fee, unit_cost, accrued_interest, broker_code, filename, custodian_id, master_account_number, master_account_name, business_date, 
                         account_title_line1, account_title_line2, account_title_line3, account_registration, product_code, product_category_code, tax_code, 
                         legacy_security_type, TRIM(ticker_symbol) AS ticker_symbol, industry_ticker_symbol, TRIM(cusip) AS cusip, schwab_security_number, item_issue_id, rule_set_suffix_id, isin, sedol, 
                         options_display_symbol, underlying_ticker_symbol, underlying_industry_ticker_symbol, underlying_cusip, underlying_schwab_security_number, 
                         underlying_item_issue_id, underlying_rule_set_suffix_id, underlying_isin, underlying_sedol, money_market_code, transaction_type_code, 
                         transaction_subtype_code, transaction_category, transaction_source_code, transaction_source_code_description, transaction_detail_description, 
                         action_code, transaction_cancel_code, settlement_date, transaction_date, exdividend_date, 
                         CASE WHEN price = 0 AND closing_price = 0 THEN 1 WHEN price > 0 THEN price ELSE closing_price END AS price, 
                         ABS(gross_amount) AS gross_amount, debit_credit_indicator, ABS(net_amount) AS net_amount, commission, exchange_processing_fee, broker_service_fee, 
                         prime_broker_fee, trade_away_fee, redemption_fee, other_fee, federal_tefra_withholding, state_tax_withholding, 
                         state_receiving_tax, accounting_rule_code, order_source_code, order_number, trade_order_entry_time_stamp, 
                         trade_order_execution_time_stamp, broker_name, schwab_from_account, schwab_to_account, schwab1_check_number, 
                         sweep_indicator, stock_exchange_code, interclass_exchange_code, distribution_rate, cash_in_lieu_share_quantity, 
                         dividend_interest_share_quantity, cash_in_lieu_rate, asset_backed_factor, source_system, journal_type, 
                         deposit_media, schwab_cashiering_unique_identifier, recipient_maker_name_line1, recipient_maker_name_line2, 
                         recipient_maker_name_line3, frequency, disbursed_check_number, fed_reference_number, recipient_maker_account_number, 
                         bank_account_type, bank_name_part1, bank_name_part2, bank_aba_number, intermediary_name, transaction_check_memo1, 
                         transaction_check_memo2, retirement_federal_income_tax, retirement_state_income_tax, retirement_income_tax_state, 
                         publication_time_stamp, version_marker1, tips_factor, closing_price, version_marker2, transaction_memo, 
                         version_marker3, closing_price_unfactored, factor, factor_date, file_date, insert_date, CASE WHEN m.operation is null THEN '' ELSE m.operation END AS operation
                  FROM custodian_omniscient.custodian_transactions_schwab t 
                  JOIN custodian_omniscient.schwabmapping m ON m.source_code = t.transaction_source_code AND m.type_code = t.transaction_type_code AND m.subtype_code = t.transaction_subtype_code AND m.direction = t.debit_credit_indicator 
                  WHERE t.account_number IN ({$account_questions}) {$transaction_ids}  
                  GROUP BY transaction_id";

        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");

                if($v['quantity'] == 0)
                    $v['quantity'] = $v['gross_amount'];
                $v['ownerid'] = PortfolioInformation_Module_Model::GetAccountOwnerFromAccountNumber($v['account_number']);
                if($v['ticker_symbol'] == '' AND $v['cusip'] != '')
                    $v['ticker_symbol'] = $v['cusip'];
                if($v['ticker_symbol'] == '' AND $v['cusip'] == '')
                    $v['ticker_symbol'] = 'SCASH';

                $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                          VALUES (?, ?, ?, ?, 'Transactions', NOW(), NOW(), ?)";
                $adb->pquery($query, array($v['crmid'], $v['ownerid'], $v['ownerid'], $v['ownerid'], $v['transaction_detail_description']));

                $query = "INSERT INTO vtiger_transactions (transactionsid, account_number, security_symbol, security_price, quantity, trade_date, origination, cloud_transaction_id, operation)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], $v['account_number'], $v['ticker_symbol'], $v['price'], $v['quantity'], $v['trade_date'], 'Schwab', $v['transaction_id'], $v['operation']));

                $query = "INSERT INTO vtiger_transactionscf (transactionsid, custodian, transaction_type, transaction_activity, net_amount, broker_fee, other_fee, schwab_direction, description, filename)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], 'Schwab', $v['transaction_type'], $v['transaction_activity'], $v['gross_amount'], $v['broker_service_fee'] + $v['prime_broker_fee'],
                                           $v['commission'] + $v['other_fee'], $v['debit_credit_indicator'], $v['transaction_detail_description'], $v['filename']));
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
                  WHERE origination = 'schwab'
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

        $query = "SELECT f.quantity, m.operation, pcf.production_number, m.omniscient_category, m.schwab_category, m.omniscient_activity, m.transaction_activity,
                         TRIM(LEADING '0' FROM t.account_number) AS account_number, pcf.omniscient_control_number,
                         f.price, f.closing_price, f.gross_amount, f.quantity, mcf.security_price_adjustment, f.gross_amount,
                         f.transaction_source_code, f.transaction_type_code, f.transaction_subtype_code, t.cloud_transaction_id
                  FROM vtiger_transactions t
                  JOIN vtiger_transactionscf cf ON t.transactionsid = cf.transactionsid
                  JOIN custodian_omniscient.custodian_transactions_schwab f ON f.transaction_id = t.cloud_transaction_id
                  JOIN custodian_omniscient.schwabmapping m ON m.source_code = f.transaction_source_code AND m.type_code = f.transaction_type_code AND m.subtype_code = f.transaction_subtype_code AND m.direction = f.debit_credit_indicator
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  LEFT JOIN vtiger_portfolioinformation p ON p.account_number = TRIM(LEADING '0' FROM t.account_number)
                  LEFT JOIN vtiger_portfolioinformationcf pcf ON pcf.portfolioinformationid = p.portfolioinformationid
                  LEFT JOIN vtiger_modsecurities ms ON ms.security_symbol = t.security_symbol
                  LEFT JOIN vtiger_modsecuritiescf mcf ON ms.modsecuritiesid = mcf.modsecuritiesid
                  WHERE {$transaction_ids}
                  AND t.account_number IN ({$account_questions})
                  {$and}
                  GROUP BY f.transaction_id";
        $result = $adb->pquery($query, $params, true);
        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_transactions t 
                      JOIN vtiger_transactionscf cf USING(transactionsid)
					  JOIN vtiger_crmentity on vtiger_crmentity.crmid = t.transactionsid
                      SET t.quantity = ?, t.operation = ?, cf.custodian_control_number = ?, cf.transaction_type = ?, cf.transaction_activity = ?,
                      t.account_number = ?, cf.rep_code = ?, t.security_price = ?, cf.net_amount = ?, key_mnemonic_description = ?,
                      transaction_key_code_description = ?, transaction_code_description = ?
                      WHERE vtiger_crmentity.deleted = 0  and cloud_transaction_id = ?";
            while($v = $adb->fetchByAssoc($result)){
                $params = array();
                $params[] = $v['quantity'];
                if(is_null($v['operation']))
                    $v['operation'] = '';
                $params[] = $v['operation'];
                $params[] = $v['production_number'];

                if(strlen($v['omniscient_category']) > 3)
                    $params[] = $v['omniscient_category'];
                else
                    $params[] = $v['schwab_category'];

                if(strlen($v['omniscient_activity']) > 3)
                    $params[] = $v['omniscient_activity'];
                else
                    $params[] = $v['transaction_activity'];
                $params[] = $v['account_number'];
                $params[] = $v['omniscient_control_number'];

                if($v['price'] == 0 && $v['closing_price'] == 0)
                    $params[] = 1;
                elseif($v['price'] > 0)
                    $params[] = $v['price'];
                else
                    $params[] = $v['closing_price'];

                if($v['gross_amount'] == 0)
                    $params[] = $v['quantity'] * $v['closing_price'] * $v['security_price_adjustment'];
                else
                    $params[] = $v['gross_amount'];

                $params[] = $v['transaction_source_code'];
                $params[] = $v['transaction_type_code'];
                $params[] = $v['transaction_subtype_code'];
                $params[] = $v['cloud_transaction_id'];
				
                $adb->pquery($query, $params, true);
            }
        }
    }

    static public function CreateTransactionsInCustodian($account_number, $symbol, $trade_date,
                                                         $type, $amount, $quantity, $price){
        global $adb;
/**THIS NEEDS FIGURED OUT FOR SCHWAB STILL BUT WE NEEDS AN EXAMPLE ACCOUNT FIRST**/
        return;
        if($type == 1){
            $transaction_type = 'CKR';
        }else{
            $transaction_type = 'REC';
        }
        $query = "INSERT INTO custodian_omniscient.custodian_transactions_schwab(account_number, 
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