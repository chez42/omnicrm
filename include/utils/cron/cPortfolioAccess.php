<?php

class cPortfolioAccess{
    private $pc;
    private $datasets;
    private $reset;
    private $max_rows;

    public function __construct() {
        $this->pc = new cPortfolioCenter();
        $this->datasets = $this->pc->GetDatasets();//"1, 28";
        $this->reset = 250;
        $this->max_rows = 5000;
    }

    /**
     * Get a list of portfolios from vtiger_portfolios that don't already exist in vtiger_portfolio_summary and insert them
     */
    public function CopyBasicPortfolInfoToPortfolioSummaryTable(){
        global $adb;
        $query = "INSERT INTO vtiger_portfolio_summary (account_number, first_name, last_name, account_type, advisor_id, master_account, inception)
                  SELECT p.portfolio_account_number, p.portfolio_first_name, p.portfolio_last_name, p.portfolio_account_type, p.advisor_id, p.master_account, p.created_date
                  FROM vtiger_portfolios p
                  LEFT JOIN vtiger_portfolio_summary vps ON vps.account_number = p.portfolio_account_number
                  WHERE vps.account_number IS NULL
                  GROUP BY p.portfolio_id
                  ON DUPLICATE KEY UPDATE account_number=VALUES(account_number)";
        $adb->pquery($query, array());
    }

    /**
     * Fallback sync to populate vtiger_portfolios from vtiger_portfolioinformation.
     * Used when the connection to the external PortfolioCenter database is not available.
     */
    public function SyncPortfoliosFromPortfolioInformation() {
        global $adb;
        echo "Syncing portfolios from vtiger_portfolioinformation to vtiger_portfolios...\n";
        
        $query = "INSERT INTO vtiger_portfolios (
                    portfolio_id, portfolio_portfolio_type_id, portfolio_account_id, portfolio_first_name, portfolio_last_name, portfolio_account_number,
                    portfolio_inception_date, portfolio_birth_date, portfolio_user_id, portfolio_account_name, portfolio_account_type, portfolio_market_value,
                    portfolio_cash_value, portfolio_bond_value, portfolio_annual_revenue, portfolio_cost_basis, portfolio_total_value, portfolio_net_new_cash_trans,
                    portfolio_net_new_cash, portfolio_tax_id, portfolio_service_provider_id, portfolio_client_organization_id, created_date, created_by,
                    modified_date, modified_by, marked_for_deletion, advisor_fee, advisor_id, master_account, data_set_id, BillingInceptionDate, PerformanceInceptionDate, origination_id, account_closed
                  ) 
                  SELECT 
                    p.portfolioinformationid AS portfolio_id,
                    16 AS portfolio_portfolio_type_id,
                    0 AS portfolio_account_id,
                    p.first_name AS portfolio_first_name,
                    p.last_name AS portfolio_last_name,
                    p.account_number AS portfolio_account_number,
                    COALESCE(p.inceptiondate, '1900-01-01') AS portfolio_inception_date,
                    NULL AS portfolio_birth_date,
                    0 AS portfolio_user_id,
                    CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')) AS portfolio_account_name,
                    p.account_type AS portfolio_account_type,
                    COALESCE(p.market_value, 0) AS portfolio_market_value,
                    COALESCE(p.cash_value, 0) AS portfolio_cash_value,
                    0 AS portfolio_bond_value,
                    0 AS portfolio_annual_revenue,
                    0 AS portfolio_cost_basis,
                    COALESCE(p.total_value, 0) AS portfolio_total_value,
                    0 AS portfolio_net_new_cash_trans,
                    0 AS portfolio_net_new_cash,
                    cf.tax_id AS portfolio_tax_id,
                    0 AS portfolio_service_provider_id,
                    0 AS portfolio_client_organization_id,
                    COALESCE(e.createdtime, NOW()) AS created_date,
                    1 AS created_by,
                    COALESCE(e.modifiedtime, NOW()) AS modified_date,
                    1 AS modified_by,
                    0 AS marked_for_deletion,
                    COALESCE(p.annual_management_fee, 0) AS advisor_fee,
                    p.advisor_id AS advisor_id,
                    cf.master_account AS master_account,
                    1 AS data_set_id,
                    p.inceptiondate AS BillingInceptionDate,
                    p.inceptiondate AS PerformanceInceptionDate,
                    1 AS origination_id,
                    CASE WHEN p.accountclosed = '1' THEN 1 ELSE 0 END AS account_closed
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  LEFT JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = p.portfolioinformationid
                  WHERE e.deleted = 0
                  ON DUPLICATE KEY UPDATE 
                    portfolio_id = VALUES(portfolio_id),
                    portfolio_portfolio_type_id = VALUES(portfolio_portfolio_type_id),
                    portfolio_account_id = VALUES(portfolio_account_id),
                    portfolio_first_name = VALUES(portfolio_first_name),
                    portfolio_last_name = VALUES(portfolio_last_name),
                    portfolio_account_number = VALUES(portfolio_account_number),
                    portfolio_inception_date = VALUES(portfolio_inception_date),
                    portfolio_birth_date = VALUES(portfolio_birth_date),
                    portfolio_account_name = VALUES(portfolio_account_name),
                    portfolio_account_type = VALUES(portfolio_account_type),
                    portfolio_market_value = VALUES(portfolio_market_value),
                    portfolio_cash_value = VALUES(portfolio_cash_value),
                    portfolio_bond_value = VALUES(portfolio_bond_value),
                    portfolio_annual_revenue = VALUES(portfolio_annual_revenue),
                    portfolio_cost_basis = VALUES(portfolio_cost_basis),
                    portfolio_total_value = VALUES(portfolio_total_value),
                    portfolio_net_new_cash_trans = VALUES(portfolio_net_new_cash_trans),
                    portfolio_net_new_cash = VALUES(portfolio_net_new_cash),
                    portfolio_tax_id = VALUES(portfolio_tax_id),
                    portfolio_service_provider_id = VALUES(portfolio_service_provider_id),
                    portfolio_client_organization_id = VALUES(portfolio_client_organization_id),
                    created_date = VALUES(created_date),
                    created_by = VALUES(created_by),
                    modified_date = VALUES(modified_date),
                    modified_by = VALUES(modified_by),
                    marked_for_deletion = VALUES(marked_for_deletion),
                    advisor_fee = VALUES(advisor_fee),
                    advisor_id = VALUES(advisor_id),
                    master_account = VALUES(master_account),
                    data_set_id = VALUES(data_set_id),
                    BillingInceptionDate = VALUES(BillingInceptionDate),
                    PerformanceInceptionDate = VALUES(PerformanceInceptionDate),
                    origination_id = VALUES(origination_id),
                    account_closed = VALUES(account_closed)";
        
        $adb->pquery($query, array());
    }

    /**
     * Fills in the crmentity table with missed portfolios should they exist
     * @global type $adb
     */
    public function GetMissed(){
        global $adb;
        $query = "SELECT vpi.* FROM vtiger_portfolioinformation vpi
                  left outer join vtiger_crmentity e ON e.crmid = vpi.portfolioinformationid
                  WHERE e.crmid is null
                  GROUP BY vpi.portfolioinformationid";

        $result = $adb->pquery($query, array());
        foreach($result AS $k => $v){
            $crmid = $v['portfolioinformationid'];
            echo $crmid;
            $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, viewedtime, presence) VALUES
                      ({$crmid}, 1, 1, 1, 'PortfolioInformation', NOW(), NOW(), NOW(), 1)";
            $adb->pquery($query, array());
        }
    }

    /**
     * Get all valid Portfolio ID's only
     * @return string
     */
    public function GetAllPortfolioIDsFromPC(){
        if(!$this->pc->connect())//Try connecting
            return false;
        $and = '';
        $query = "SELECT p.PortfolioID 
                  FROM Portfolios p 
                  WHERE p.DataSetID IN ({$this->datasets}) AND PortfolioTypeID = 16 AND ClosedAccountFlag=0";
        $result = mssql_query($query);
        $info = array();//Holds all row info
        if(mssql_num_rows($result) > 0)
            while($row = mssql_fetch_array($result))
                $info[] = $row;
        return $info;
    }

    /**
     * Returns a query result with all portfolio information straight from PC in key/value format (DataSetID's 1 and 28 returned)
     * pass in an override account to get just a single account
     */
    public function GetAllPortfoliosFromPC($pids='', $date=null){
        if(!$this->pc->connect())//Try connecting
            return false;
        $and = '';
        if(strlen($date) > 0)
            $and .= " AND p.LastModifiedDate >= '{$date}' ";
        if(strlen($pids) > 0)
            $and = " AND p.PortfolioID IN ({$pids}) ";
        $query = "SELECT bs.SpecificationDescription AS advisor_fee, p.* 
                  FROM PortfolioCenter.dbo.Portfolios p 
                  LEFT JOIN PortfolioCenter.dbo.BillingSpecifications bs ON bs.BillingSpecificationID = p.BillingSpecificationID 
                  WHERE p.DataSetID IN ({$this->datasets}) {$and} AND PortfolioTypeID = 16 AND ClosedAccountFlag=0";
        $result = mssql_query($query);
        $info = array();//Holds all row info
        if(mssql_num_rows($result) > 0)
            while($row = mssql_fetch_array($result))
                $info[] = $row;
        return $info;
    }

    public function GetManualAccountsFromPC(){
        $pdo = $this->pc->PDOConnect();//Try connecting

        $query = "SELECT * FROM PortfolioCenter.dbo.Portfolios WHERE AccountNumber LIKE 'M%' AND DataSetID = 1 AND ClosedAccountFlag = 0";
        $result = $pdo->query($query);
        $info = array();//Holds all row info
        while($row = $result->fetch()){
            $row['AccountNumber'] = str_replace("-", "", $row['AccountNumber']);
            $info[$row['AccountNumber']] = $row;
        }
        $this->pc->PDOCloseConnection();
        return $info;
    }

    public function GetManualAccountsSecurityIDsFromPC(){
        $pdo = $this->pc->PDOConnect();//Try connecting

        $query = "SELECT DISTINCT(s.SecurityID)
                  FROM PortfolioCenter.dbo.Portfolios p 
                  JOIN PortfolioCenter.dbo.Transactions t ON t.PortfolioID = p.PortfolioID
                  JOIN PortfolioCenter.dbo.Securities s ON s.SecurityID = t.SymbolID
                  WHERE p.AccountNumber LIKE 'M%'
                  GROUP BY s.SecurityID";
        $result = $pdo->query($query);
        $securityID = array();
        while($row = $result->fetch()){
            $securityID[] = $row['SecurityID'];
        }
        $this->pc->PDOCloseConnection();
        return $securityID;
    }

    public function GetManualAccountSecuritiesFromPC(){
        $pdo = $this->pc->PDOConnect();//Try connecting

        $securityIDs = self::GetManualAccountsSecurityIDsFromPC();
        $questions = generateQuestionMarks($securityIDs);
        $query = "SELECT*
                  FROM PortfolioCenter.dbo.Securities 
                  WHERE SecurityID IN ({$questions})";
        $result = $pdo->query($query, array($securityIDs));
        $securityID = array();
        while($row = $result->fetch()){
            $securityID[] = $row['SecurityID'];
        }
        $this->pc->PDOCloseConnection();
        return $securityID;
    }


    public function WipeCRMTransactionAndDeleteFromPCTable($account_number){
        global $adb;

        $query = "DELETE pct.*, ctp.* 
                  FROM vtiger_pc_transactions pct
                  JOIN vtiger_transactions vtt ON vtt.cloud_transaction_id = pct.transaction_id
                  JOIN custodian_omniscient.custodian_transactions_pc ctp ON ctp.portfolio_id = pct.portfolio_id
                  WHERE vtt.account_number = ?";
        $adb->pquery($query, array($account_number));

        $query = "UPDATE vtiger_transactions t
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  SET net_amount = 0, quantity = 0 WHERE account_number = ?";
        $adb->pquery($query, array($account_number));
    }

    public function GetTransactionCount($pids, $date=null){
        if(strlen($date) < 2){
            $date = '1900-01-01';
        }
        $questions = generateQuestionMarks($pids);
        $where = " WHERE t.TradeDate >= ? AND p.PortfolioID IN ({$questions})";

        $pdo = $this->pc->PDOConnect();//Try connecting

        $query = "SELECT count(*) AS count FROM PortfolioCenter.dbo.transactions t
                  LEFT JOIN PortfolioCenter.dbo.Portfolios p ON t.PortfolioID = p.PortfolioID
                  {$where}
                  AND p.DataSetID IN (1)";//Get the number of transactions

        $params = array();
        $params[] = $date;
        foreach($pids AS $k => $v){
            $params[] = $v;
        }
        $sth = $pdo->prepare($query);
        $sth->execute($params);
        $this->pc->PDOCloseConnection();

        return $sth->fetchColumn();
    }

    public function CopyTransactionsFromPCToCRM($pids, $date=null){
        $transaction_result_count = 0;
        $num_transactions = $this->GetTransactionCount($pids, $date);

        $transaction_loop_counter = $num_transactions/$this->max_rows;
        $x = 0;
        do{
            $start = $x * $this->max_rows;
            $end = $start + $this->max_rows;
            $transaction_result_count += $this->WriteTransactions($pids, $date, $start, $end);
            $x++;
        } while ($x < $transaction_loop_counter);

        return $transaction_result_count;
    }

    private function GetTransactionsFromPC($pids, $date, $row_start=0, $row_end=50000){
        global $adb;

        $questions = generateQuestionMarks($pids);

        $where = " WHERE t.TradeDate >= ? AND p.PortfolioID IN ({$questions}) ";

        $pdo = $this->pc->PDOConnect();//Try connecting

        $query = "with cte as (
                      SELECT t.*,
                       ROW_NUMBER () OVER (ORDER BY TransactionID ASC) as rn
                      FROM PortfolioCenter.dbo.Transactions t 
                      LEFT JOIN PortfolioCenter.dbo.Portfolios p ON p.PortfolioID = t.PortfolioID
                      {$where} AND p.DataSetID IN (1)) 
                      SELECT * FROM cte 
                      WHERE rn BETWEEN {$row_start} and {$row_end}";

        $params = array();
        $params[] = $date;
        foreach($pids AS $k => $v){
            $params[] = $v;
        }

        $sth = $pdo->prepare($query);
        $sth->execute($params);

        $data = $sth->fetchAll();
        $this->pc->PDOCloseConnection();
        return $data;
    }

    private function WriteTransactions($pids, $date, $start, $end){
        global $adb;
        $data = $this->GetTransactionsFromPC($pids, $date, $start, $end);

        $count = 0;
        $reset = 0;

        if(sizeof($data) > 0)
        {
            $transaction_result_count = sizeof($data);
            $query = "INSERT INTO vtiger_pc_transactions (transaction_id, portfolio_id, sell_lot_id, trade_lot_id, link_id, custodian_id, symbol_id, activity_id, 
                                                          money_id, broker_id, report_as_type_id, quantity, total_value, conversion_value, accrued_interest, 
                                                          yield_at_purchase, advisor_fee, amount_per_share, other_fee, net_amount, settlement_date, trade_date, 
                                                          origina_trade_date, entry_date, link_date, odd_income_payment_flag, long_position_flag, reinvest_gains_flag, 
                                                          reinvest_income_flag, keep_fractional_shares_flag, taxable_prev_year_flag, complete_transaction_flag, 
                                                          is_reinvested_flag, notes, principal, add_sub_status_type_id, contribution_type_id, matching_method_id, 
                                                          custodian_account, original_link_account, origination_id, last_modified_date, trans_link_id, status_type_id, 
                                                          last_modified_user_id, dirty_flag, invalid_cost_basis_flag, cost_basis_adjustment, security_split_flag, 
                                                          reset_cost_basis_flag) VALUES ";
            $extension = "";
            $update = " ON DUPLICATE KEY UPDATE symbol_id = VALUES(symbol_id), money_id = VALUES(money_id), net_amount = VALUES(net_amount), total_value = VALUES(total_value),
                quantity = VALUES(quantity), trade_date = VALUES(trade_date), last_modified_date = VALUES(last_modified_date), cost_basis_adjustment = VALUES(cost_basis_adjustment)";

            foreach($data AS $k => $row)
            {
                $transaction_id = $row['TransactionID'];
                $portfolio_id = $row['PortfolioID'];
                $sell_lot_id = $row['SellLotID'];
                $trade_lot_id = $row['TradeLotID'];
                $link_id = $row['LinkID'];
                $custodian_id = $row['CustodianID'];
                $symbol_id = $row['SymbolID'];
                $activity_id = $row['ActivityID'];
                $money_id = $row['MoneyID'];
                $broker_id = $row['BrokerID'];
                $report_as_type_id = $row['ReportAsTypeID'];
                $quantity = $row['Quantity'];
                $total_value = $row['TotalValue'];
                $conversion_value = $row['ConversionValue'];
                $accrued_interest = $row['AccruedInterest'];
                $yield_at_purchase = $row['YieldAtPurchase'];
                $advisor_fee = $row['AdvisorFee'];
                $amounter_per_share = $row['AmountPerShare'];
                $other_fee = $row['OtherFee'];
                $net_amount = $row['NetAmount'];

                $settlement_date = $row['SettlementDate'];
                $settlement_date = $this->pc->ConvertDateKeepingTime($settlement_date);
                $trade_date = $row['TradeDate'];
                $trade_date = $this->pc->ConvertDateKeepingTime($trade_date);
                $original_trade_date = $row['OriginalTradeDate'];
                $original_trade_date = $this->pc->ConvertDateKeepingTime($original_trade_date);
                $entry_date = $row['EntryDate'];
                $entry_date = $this->pc->ConvertDateKeepingTime($entry_date);
                $link_date = $row['LinkDate'];
                $link_date = $this->pc->ConvertDateKeepingTime($link_date);
                $last_modified_date = $row['LastModifiedDate'];
                $last_modified_date = $this->pc->ConvertDateKeepingTime($last_modified_date);

                $odd_income_payment_flag = $row['OddIncomePaymentFlag'];
                $long_position_flag = $row['LongPositionFlag'];
                $reinvest_gains_flag = $row['ReinvestGainsFlag'];
                $reinvest_income_flag = $row['ReinvestIncomeFlag'];
                $keep_fractional_shares_flag = $row['KeepFractionalSharesFlag'];
                $taxable_prev_year_flag = $row['TaxablePrevYearFlag'];
                $complete_transaction_flag = $row['CompleteTransactionFlag'];
                $is_reinvested_flag = $row['IsReinvestedFlag'];
                $notes = str_replace("'", "", $row['Notes']);//mysql_real_escape_string($row['Notes']);
                $principal = $row['Principal'];
                $add_sub_status_type_id = $row['AddSubStatusTypeID'];
                $contribution_type_id = $row['ContributionTypeID'];
                $matching_method_id = $row['MatchingMethodID'];
                $custodian_account = $row['CustodianAccount'];
                $original_link_account = $row['OriginalLinkAccount'];
                $origination_id = $row['OriginationID'];
                $trans_link_id = $row['TransLinkID'];
                $status_type_id = $row['StatusTypeID'];
                $last_modified_user_id = $row['LastModifiedUserID'];
                $dirty_flag = $row['DirtyFlag'];
                $invalid_cost_basis_flag = $row['InvalidCostBasisFlag'];
                $cost_basis_adjustment = $row['CostBasisAdjustment'];
                $security_split_flag = $row['SecuritySplitFlag'];
                $reset_cost_basis_flag = $row['ResetCostBasisFlag'];

                $extension .= "('{$transaction_id}','{$portfolio_id}','{$sell_lot_id}','{$trade_lot_id}','{$link_id}','{$custodian_id}','{$symbol_id}','{$activity_id}','{$money_id}',
                              '{$broker_id}','{$report_as_type_id}','{$quantity}','{$total_value}','{$conversion_value}','{$accrued_interest}','{$yield_at_purchase}','{$advisor_fee}',
                              '{$amounter_per_share}','{$other_fee}','{$net_amount}','{$settlement_date}','{$trade_date}','{$original_trade_date}','{$entry_date}','{$link_date}','{$odd_income_payment_flag}',
                              '{$long_position_flag}','{$reinvest_gains_flag}','{$reinvest_income_flag}','{$keep_fractional_shares_flag}','{$taxable_prev_year_flag}','{$complete_transaction_flag}',
                              '{$is_reinvested_flag}','{$notes}','{$principal}','{$add_sub_status_type_id}','{$contribution_type_id}','{$matching_method_id}','{$custodian_account}',
                              '{$original_link_account}','{$origination_id}','{$last_modified_date}','{$trans_link_id}','{$status_type_id}','{$last_modified_user_id}','{$dirty_flag}',
                              '{$invalid_cost_basis_flag}','{$cost_basis_adjustment}','{$security_split_flag}','{$reset_cost_basis_flag}')";
                $count++;
                $reset++;
                if($count < sizeof($data) && $reset < $this->reset)
                    $extension .= ",";

                if($reset >= $this->reset)
                {
                    $reset = 0;//Reset the query insert
                    $adb->pquery($query . $extension . $update, array());
//                    $this->ExecuteQuery($query . $extension . $update, "Inserting into vtiger_pc_transactions ");
                    unset($extension);
                }
            }

            if(strlen($extension) > 0)
                $adb->pquery($query . $extension . $update);
//                $this->ExecuteQuery($query . $extension . $update, "Inserting into vtiger_pc_transactions -- Final insert ");
            }
        return $transaction_result_count;
    }

    public function GetManualBalancesFromPC(){
        $pdo = $this->pc->PDOConnect();//Try connecting

        $query = "SELECT p.AccountNumber, i.IntervalEndDate, i.IntervalEndValue FROM PortfolioCenter.dbo.Portfolios p 
                  JOIN PortfolioCenter.dbo.PortfolioIntervals i ON p.PortfolioID = i.PortfolioID
                  WHERE p.AccountNumber LIKE 'M-%' AND p.DataSetID = 1 AND p.ClosedAccountFlag = 0
                  AND i.IntervalEndDate = (SELECT MAX(IntervalEndDate) FROM PortfolioCenter.dbo.PortfolioIntervals i 
                                           WHERE i.PortfolioID IN (
                                                SELECT PortfolioID 
                                                FROM PortfolioCenter.dbo.Portfolios 
                                                WHERE AccountNumber LIKE 'M-%' AND DataSetID = 1 AND ClosedAccountFlag = 0));";
        $result = $pdo->query($query);
        $info = array();//Holds all row info

        while($row = $result->fetch()){
            $row['AccountNumber'] = str_replace("-", "", $row['AccountNumber']);
            $info[$row['AccountNumber']] = $row;
        }
        $this->pc->PDOCloseConnection();
        return $info;
    }

    public function EnableAndDisablePortfoliosToMatchPC(){
        global $adb;
        require_once("libraries/reports/new/nAuditing.php");

        $audit = new nAuditing();
        $inactive = $audit->GetInActiveAccountsFromPC();
        $active = $audit->GetActiveAccountsFromPC();

        $query = "UPDATE vtiger_portfolios SET account_closed=1 WHERE portfolio_id = ?";
        if(is_array($inactive)) {
            foreach ($inactive AS $k => $v){
                $adb->pquery($query, array($k));
            }
        }

        $query = "UPDATE vtiger_portfolios SET account_closed=0 WHERE portfolio_id = ?";
        if(is_array($active)){
            foreach($active AS $k => $v){
                $adb->pquery($query, array($k));
            }
        }
        /*        $query = "SELECT * FROM PortfolioCenter.dbo.Portfolios";
				$result = $this->CustomQuery($query);
				if(is_array($result)){
					foreach($result AS $k => $v){
						print_r($v);exit;
					}
				}*/
    }

    /**
     * Get a list of closed accounts
     * @param type $account_number_override
     * @return int
     */
    public function GetClosedAccountsFromPC($account_number_override=''){
        if(!$this->dbhandle)//We never connected
            if(!$this->connect())//Try connecting
                return 0;//Failed to connect
        if($account_number_override)
            $and = " AND AccountNumber = '{$account_number_override}'";
        $query = "SELECT * FROM Portfolios WHERE DataSetID IN ({$this->datasets}) AND ClosedAccountFlag=1 {$and}";
        $result = mssql_query($query);

        $info = array();//Holds all row info
        if(mssql_num_rows($result) > 0)
            while($row = mssql_fetch_array($result))
                $info[] = $row;
        return $info;
    }

    public function GetTransactionCountForDate($date){
        if(!$this->dbhandle)//We never connected
            if(!$this->connect())//Try connecting
                return 0;//Failed to connect
        echo 'here';exit;
        $query = "SELECT COUNT(*) FROM Transactions WHERE TradeDate = '{$date}'";
        $result = mssql_query($query);

        if(mssql_num_rows($result) > 0)
            return mssql_fetch_array($result);
        return 0;
    }

    /**
     * Pulls all portfolio id's and portfolio account numbers for the given accounts.  If nothing passed in, it pulls for everything
     * @global type $adb
     * @param type $override
     * @param type $reset
     * @param type $advisor_id
     */
    public function GetPortfolioIdAndAccountNumber($override=null, $reset=null, $advisor_id=null){
        global $adb;
        $condition = "";
        if($advisor_id)//If we want to pull all accounts for a given advisor
        {
            $query = "SELECT pc_id FROM vtiger_pc_advisor_linking WHERE user_id = ?";
            $result = $adb->pquery($query, array($advisor_id));
            $pc_id = $adb->query_result($result, 0, "pc_id");
            $condition = " WHERE advisor_id = ?";
        }

        $override = $_REQUEST['override'];
        $reset = $_REQUEST['reset'];
        if(!$reset)
            $reset = 0;

        if($override)
            $condition = " WHERE portfolio_account_number = '{$override}'";

        $query = "SELECT portfolio_id, portfolio_account_number FROM vtiger_portfolios {$condition} GROUP BY portfolio_account_number";//Get all portfolio ID's)
        //$query = "SELECT portfolio_id, portfolio_account_number FROM vtiger_portfolios WHERE portfolio_account_number='672-945358' GROUP BY portfolio_account_number";

        if($advisor_id)
            $result = $adb->pquery($query, array($pc_id));
        else
            $result = $adb->pquery($query, array());

        return $result;
    }

    public function GetLastModifiedDate(){
        global $adb;
        $query = "SELECT modified_date FROM vtiger_portfolios GROUP BY modified_date DESC LIMIT 1";
        $result = $adb->pquery($query, array());
        $date = $adb->query_result($result, 0, "modified_date");
        return $date;
    }

    /**
     * Shoots out the feedback and executes the passed in query
     * @global type $adb
     * @param type $query
     * @param string $feedback
     */
    private function ExecuteQuery($query, $feedback=''){
        global $adb;

        if(strlen($feedback) > 1)
            $feedback .= date('Y-m-d H:i:s') . "<br />\r\n";
        echo $feedback;
        ob_flush();
        flush();

        $adb->pquery($query, array());
    }

    /**
     * Gets everything from the summary table that doesn't already exist in the vtiger_positioninformation table
     * @global type $adb
     * @param type $date
     * @return type
     */
    public function GetPortfoliosToInsertFromSummaryTable($account_number=null, $date = null){
        global $adb;
        if(strlen($account_number) > 1)
            $and = " AND vps.account_number = '{$account_number}' ";

        /*        $query = "SELECT vps.* FROM vtiger_portfolio_summary vps
						  left outer join vtiger_portfolioinformation vpi ON vpi.account_number = vps.account_number
						  WHERE vpi.account_number is null
						  AND (vps.account_number != '' AND vps.account_number is not null)
						  {$and}
						  GROUP BY vps.account_number";*/
        $query = "SELECT vps.* FROM vtiger_portfolio_summary vps
                      WHERE vps.account_number NOT IN 
                      (SELECT account_number from vtiger_portfolioinformation WHERE account_number != '' AND account_number IS NOT NULL)
                      AND vps.account_number != ''
                      AND vps.account_number IS NOT NULL
                      {$and}";

        $result = $adb->pquery($query, array());
        return $result;
    }

    /**
     * Update the position information module from the summary table
     * @global type $adb
     * @param type $date
     */
    public function UpdatePortfolioInformationModule($date=null){
        global $adb;

        echo "Retrieving Portfolios to Insert from Summary Table " . date('Y-m-d H:i:s') . "<br />\r\n";
        ob_flush();
        flush();
        $result = $this->GetPortfoliosToInsertFromSummaryTable($date);

        $num_results = $adb->num_rows($result);
        if($num_results > 0){
            $query = "UPDATE vtiger_crmentity_seq SET id = id + 1";
            $adb->pquery($query, array());
        }

        $query = "SELECT id FROM vtiger_crmentity_seq";
        $entity_seq_result = $adb->pquery($query, array());//we now have our new crmid
        $crmid = $adb->query_result($entity_seq_result, 0, "id");

        echo "Portfolios retrieved with {$num_results} to create " . date('Y-m-d H:i:s') . "<br />\r\n";
        ob_flush();
        flush();

        if($num_results > 0){
            $query = "UPDATE vtiger_crmentity_seq SET id = id + {$num_results}";
            $adb->pquery($query, array());
        }

        $query_entity = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, viewedtime, presence) VALUES ";
        $query_portfolioinformation = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, first_name, last_name, advisor_id, total_value, market_value, 
                                                                               annual_management_fee, origination, account_type, household_account, contact_link) VALUES ";
        $query_portfolioinformationcf = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, production_number, master_account, tax_id) VALUES ";

        $count = 0;
        $reset = 0;
        $query_entity_extension = "";
        $query_portfolioinformation_extension = "";
        $query_portfolioinformationcf_extension = "";
        foreach($result AS $k => $v){
            $advisor_id = $v['advisor_id'];
            $symbol = mysql_real_escape_string($v['symbol']);
            $description = mysql_real_escape_string($v['description']);
            $account_number = mysql_real_escape_string($v['account_number']);
            $first_name = mysql_real_escape_string($v['first_name']);
            $last_name = mysql_real_escape_string($v['last_name']);

            $query_entity_extension .= "('{$crmid}', '{$v['assigned_to']}', '{$v['assigned_to']}', '{$v['assigned_to']}', 'PortfolioInformation', NOW(), NOW(), NOW(), 1)";
            $query_portfolioinformation_extension .= "('{$crmid}', '{$account_number}', '{$first_name}', '{$last_name}', '{$advisor_id}', '{$v['total_value']}', '{$v['market_value']}',
                                                       '{$v['annual_management_fee']}', '{$v['origination']}', '{$v['account_type']}', '{$v['household_account']}', '{$v['contact_id']}')";
            $query_portfolioinformationcf_extension .= "('{$crmid}', '{$v['production_number']}', '{$v['master_account']}', '{$v['tax_id']}')";
            $count++;
            $reset++;
            if($count < $num_results && $reset < $this->reset){//If we need to reset, don't add a comma
                $query_entity_extension .= ",";
                $query_portfolioinformation_extension .= ",";
                $query_portfolioinformationcf_extension .= ",";
            }
            $crmid++;

            if($reset >= $this->reset)
            {
                $reset = 0;//Reset the query insert
                $this->ExecuteQuery($query_entity . $query_entity_extension, "Inserting into entities table ");
                $this->ExecuteQuery($query_portfolioinformation . $query_portfolioinformation_extension, "Inserting into portfolio information table ");
                $this->ExecuteQuery($query_portfolioinformationcf . $query_portfolioinformationcf_extension, "Inserting into portfolio information cf table ");
                $query_entity_extension = '';
                $query_portfolioinformation_extension = '';
                $query_portfolioinformationcf_extension = '';
            }
        }

        $this->ExecuteQuery($query_entity . $query_entity_extension, "Inserting into entities table ");
        $this->ExecuteQuery($query_portfolioinformation . $query_portfolioinformation_extension, "Inserting into position information table -- Final ");
        $this->ExecuteQuery($query_portfolioinformationcf . $query_portfolioinformationcf_extension, "Inserting into position information cf table -- Final ");

        /*        $query = "UPDATE vtiger_portfolioinformation AS pinfo
						  LEFT JOIN vtiger_portfolio_summary ps ON pinfo.account_number = ps.account_number
						  LEFT JOIN vtiger_portfolioinformationcf pinfocf ON pinfocf.portfolioinformationid = pinfo.portfolioinformationid
						  LEFT JOIN vtiger_crmentity e ON e.crmid = pinfo.portfolioinformationid
						  LEFT JOIN vtiger_crmentity e2 ON e2.crmid = pinfo.contact_link
						  SET pinfo.first_name = ps.first_name,
						  pinfo.last_name = ps.last_name,
						  pinfo.account_type = ps.account_type,
						  pinfo.total_value = ps.total_value,
						  pinfo.market_value = ps.market_value,
						  pinfo.cash_value = ps.cash_value,
						  pinfo.annual_management_fee = ps.annual_management_fee,
						  pinfo.contact_link = ps.contact_id,
						  pinfo.advisor_id = ps.advisor_id,
						  pinfo.origination = ps.origination,
						  pinfocf.master_account = ps.master_account,
						  pinfocf.production_number = ps.production_number,
						  e.smownerid = CASE WHEN e2.smownerid = 0 THEN (SELECT al.user_id FROM vtiger_pc_advisor_linking al WHERE al.pc_id = pinfo.advisor_id LIMIT 1) ELSE e2.smownerid END";*/
        $query = "UPDATE vtiger_portfolioinformation AS pinfo
                          LEFT JOIN vtiger_portfolio_summary ps ON pinfo.account_number = ps.account_number
                          LEFT JOIN vtiger_portfolioinformationcf pinfocf ON pinfocf.portfolioinformationid = pinfo.portfolioinformationid
                          LEFT JOIN vtiger_crmentity epinfo ON epinfo.crmid = pinfo.portfolioinformationid
                          SET pinfo.first_name = ps.first_name,
                          pinfo.last_name = ps.last_name,
                          pinfo.account_type = ps.account_type,
                          pinfo.annual_management_fee = ps.annual_management_fee,
                          pinfo.contact_link = ps.contact_id,
                          pinfo.advisor_id = ps.advisor_id,
			  pinfo.household_account = ps.household_account,
                          pinfo.origination = ps.origination,
                          pinfocf.master_account = ps.master_account,
                          pinfocf.production_number = ps.production_number,
                          pinfocf.tax_id = ps.tax_id,
                          epinfo.smownerid = ps.assigned_to
                          WHERE epinfo.smownerid <= 1
                          AND epinfo.deleted = 0
                          AND ps.advisor_id != 0
                          AND ps.production_number IS NOT NULL
                          AND (ps.assigned_to IS NOT NULL AND ps.assigned_to != 0)";

        $this->ExecuteQuery($query, "Updating portfolioinformation (With Owner Change For Admin Assigned Only");

        $query = "UPDATE vtiger_portfolioinformation AS pinfo
                          LEFT JOIN vtiger_portfolio_summary ps ON pinfo.account_number = ps.account_number
                          LEFT JOIN vtiger_portfolioinformationcf pinfocf ON pinfocf.portfolioinformationid = pinfo.portfolioinformationid
                          LEFT JOIN vtiger_crmentity epinfo ON epinfo.crmid = pinfo.portfolioinformationid
                          SET pinfo.first_name = ps.first_name,
                          pinfo.last_name = ps.last_name,
                          pinfo.account_type = ps.account_type,
                          pinfo.annual_management_fee = ps.annual_management_fee,
                          pinfo.contact_link = ps.contact_id,
                          pinfo.advisor_id = ps.advisor_id,
                          pinfo.household_account = ps.household_account,
                          pinfo.origination = ps.origination,
                          pinfocf.master_account = ps.master_account,
                          pinfocf.production_number = ps.production_number,
                          pinfocf.tax_id = ps.tax_id";
        $this->ExecuteQuery($query, "Updating portfolioinformation");
    }

    /**
     * Calculate the portfolio summary info for the given portfolio ID
     * @param type $portfolio_id
     *
     * 947.73 seconds to do the entire Portfolios total/cash/market_value calculations (~15 mins)
     */
    public function CalculatePortfolioSummary($portfolio_id=null){
        global $adb;
        if(strlen($portfolio_id) > 1)
            $where = " WHERE vport.portfolio_id IN ({$portfolio_id})";
//OLD QUERY      
        /*        $query = "SELECT summed.account_number, summed.portfolio_id, summed.first_name, summed.last_name, summed.account_type, summed.master_account, summed.tax_id,
							summed.inception, summed.nickname,
							SUM(summed.current_value) AS total_value,
							SUM(CASE WHEN summed.type_id = 11 THEN summed.current_value END) AS cash_value,
							SUM(CASE WHEN summed.type_id != 11 THEN summed.current_value END) AS market_value,
							summed.trailing_12_fees, summed.advisor_id,
							(SELECT user_id FROM vtiger_pc_advisor_linking WHERE pc_id = summed.advisor_id LIMIT 1) AS assigned_to_pc_user,
							(SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn LIMIT 1) AS contactid, con.accountid AS household_account, e.smownerid AS assigned_to,
							(SELECT interface_name FROM vtiger_pc_interface_originations o
							   WHERE o.origination_id = (SELECT origination_id FROM vtiger_pc_transactions t WHERE t.portfolio_id = summed.portfolio_id LIMIT 1)) AS origination,
							(SELECT pc_name FROM vtiger_pc_advisors WHERE pc_id = summed.advisor_id) AS production_number
							FROM
								(SELECT vport.advisor_id, vport.portfolio_account_number AS account_number, vport.portfolio_id, vport.portfolio_first_name AS first_name, vport.portfolio_last_name AS last_name, vport.portfolio_account_type AS account_type,
										vport.master_account, vport.portfolio_tax_id AS tax_id, vport.portfolio_inception_date AS inception, pac.nickname, o.interface_name AS origination,
										st.security_type_name, c.code_description, s.security_symbol, SUM(current_value) AS current_value, st.security_type_id AS type_id,
										(SELECT SUM(cost_basis_adjustment) AS cba_total
											FROM vtiger_pc_transactions t
											WHERE t.portfolio_id = vport.portfolio_id
											AND report_as_type_id = 60
											AND trade_date >= now() - interval 12 month) AS trailing_12_fees
								FROM vtiger_positioninformation vpos
								LEFT JOIN vtiger_portfolios vport ON vpos.account_number = vport.portfolio_account_number
								LEFT JOIN vtiger_pc_account_custom pac ON pac.account_number = vport.portfolio_account_number
								LEFT JOIN vtiger_securities s ON s.security_id = vpos.symbol_id
								LEFT JOIN vtiger_security_types st ON st.security_type_id = s.security_type_id
								LEFT JOIN vtiger_pc_security_codes sc ON sc.security_id = vpos.symbol_id AND sc.code_type_id = 20
								LEFT JOIN vtiger_pc_codes c ON c.code_id = sc.code_id
								LEFT JOIN vtiger_pc_transactions t ON t.transaction_id = (select MIN(transaction_id) AS transaction_id FROM vtiger_pc_transactions WHERE portfolio_id = vport.portfolio_id)
								LEFT JOIN vtiger_pc_interface_originations o ON t.origination_id = o.origination_id
								{$where}
								GROUP BY vpos.symbol_id, vport.portfolio_id
								HAVING current_value <> 0) AS summed
								LEFT JOIN vtiger_contactdetails con ON con.contactid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn LIMIT 1)
								LEFT JOIN vtiger_crmentity e ON e.crmid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn LIMIT 1)
							GROUP BY summed.portfolio_id";*/

//This second query is working minus the fact it assigns the wrong user id if an SSN doesn't exist (it would assign it to contact_id 3, the first result with no ssn)
        /*$query = "SELECT summed.account_number, summed.portfolio_id, summed.first_name, summed.last_name, summed.account_type, summed.master_account, summed.tax_id,
							summed.inception, summed.nickname,
							SUM(summed.current_value) AS total_value,
							SUM(CASE WHEN summed.type_id = 11 THEN summed.current_value END) AS cash_value,
							SUM(CASE WHEN summed.type_id != 11 THEN summed.current_value END) AS market_value,
							summed.trailing_12_fees, summed.advisor_id,
							(SELECT user_id FROM vtiger_pc_advisor_linking WHERE pc_id = summed.advisor_id LIMIT 1) AS assigned_to_pc_user,
							(SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn LIMIT 1) AS contactid, con.accountid AS household_account, e.smownerid AS assigned_to,
							(SELECT interface_name FROM vtiger_pc_interface_originations o
							   WHERE o.origination_id = (SELECT origination_id FROM vtiger_pc_transactions t WHERE t.portfolio_id = summed.portfolio_id LIMIT 1)) AS origination,
							(SELECT pc_name FROM vtiger_pc_advisors WHERE pc_id = summed.advisor_id) AS production_number
							FROM
								(SELECT vport.advisor_id, vport.portfolio_account_number AS account_number, vport.portfolio_id, vport.portfolio_first_name AS first_name, vport.portfolio_last_name AS last_name, vport.portfolio_account_type AS account_type,
										vport.master_account, vport.portfolio_tax_id AS tax_id, vport.portfolio_inception_date AS inception, pac.nickname, o.interface_name AS origination,
										st.security_type_name, c.code_description, s.security_symbol, SUM(current_value) AS current_value, st.security_type_id AS type_id, t12.cba_total AS trailing_12_fees
								FROM vtiger_positioninformation vpos
								LEFT JOIN vtiger_portfolios vport ON vpos.account_number = vport.portfolio_account_number
								LEFT JOIN (SELECT SUM(cost_basis_adjustment) AS cba_total, t.portfolio_id
											FROM vtiger_pc_transactions t
											WHERE report_as_type_id = 60
											AND trade_date BETWEEN now() - interval 12 month AND now() GROUP BY portfolio_id) AS t12 ON t12.portfolio_id = vport.portfolio_id
								LEFT JOIN vtiger_pc_account_custom pac ON pac.account_number = vport.portfolio_account_number
								LEFT JOIN vtiger_securities s ON s.security_id = vpos.symbol_id
								LEFT JOIN vtiger_security_types st ON st.security_type_id = s.security_type_id
								LEFT JOIN vtiger_pc_security_codes sc ON sc.security_id = vpos.symbol_id AND sc.code_type_id = 20
								LEFT JOIN vtiger_pc_codes c ON c.code_id = sc.code_id
								LEFT JOIN vtiger_pc_transactions t ON t.transaction_id = (select transaction_id FROM vtiger_pc_transactions WHERE portfolio_id = vport.portfolio_id LIMIT 1)
								LEFT JOIN vtiger_pc_interface_originations o ON t.origination_id = o.origination_id
								{$where}
								GROUP BY vpos.symbol_id, vport.portfolio_id
								HAVING current_value <> 0) AS summed
								LEFT JOIN vtiger_contactdetails con ON con.contactid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn LIMIT 1)
								LEFT JOIN vtiger_crmentity e ON e.crmid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn LIMIT 1)
							GROUP BY summed.portfolio_id";*/
        $query = "SELECT summed.account_number, summed.portfolio_id, summed.first_name, summed.last_name, summed.account_type, summed.master_account, summed.tax_id, 
                    summed.inception, summed.nickname,
                    SUM(summed.current_value) AS total_value, 
                    SUM(CASE WHEN summed.type_id = 11 THEN summed.current_value END) AS cash_value,
                    SUM(CASE WHEN summed.type_id != 11 THEN summed.current_value END) AS market_value,
                    summed.trailing_12_fees, summed.advisor_id,
                    (SELECT user_id FROM vtiger_pc_advisor_linking WHERE pc_id = summed.advisor_id LIMIT 1) AS assigned_to_pc_user,
					(SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn AND vcf.ssn != '' LIMIT 1) AS contactid, con.accountid AS household_account, 
					(CASE WHEN e.smownerid is null OR e.smownerid = 1 THEN (SELECT user_id FROM vtiger_pc_advisor_linking WHERE pc_id = summed.advisor_id LIMIT 1) ELSE e.smownerid END) AS assigned_to,
					(SELECT interface_name FROM vtiger_pc_interface_originations o 
					   WHERE o.origination_id = (SELECT origination_id FROM vtiger_pc_transactions t WHERE t.portfolio_id = summed.portfolio_id LIMIT 1)) AS origination,
                    (SELECT pc_name FROM vtiger_pc_advisors WHERE pc_id = summed.advisor_id) AS production_number
                    FROM
                        (SELECT vport.advisor_id, vport.portfolio_account_number AS account_number, vport.portfolio_id, vport.portfolio_first_name AS first_name, vport.portfolio_last_name AS last_name, vport.portfolio_account_type AS account_type, 
                                vport.master_account, vport.portfolio_tax_id AS tax_id, vport.portfolio_inception_date AS inception, pac.nickname, o.interface_name AS origination,
                                st.security_type_name, c.code_description, s.security_symbol, SUM(current_value) AS current_value, st.security_type_id AS type_id, t12.cba_total AS trailing_12_fees
                        FROM vtiger_positioninformation vpos
                        LEFT JOIN vtiger_portfolios vport ON vpos.account_number = vport.portfolio_account_number
                        LEFT JOIN (SELECT SUM(cost_basis_adjustment) AS cba_total, t.portfolio_id
                                    FROM vtiger_pc_transactions t
                                    WHERE report_as_type_id = 60 
                                    AND trade_date BETWEEN now() - interval 12 month AND now() GROUP BY portfolio_id) AS t12 ON t12.portfolio_id = vport.portfolio_id
                        LEFT JOIN vtiger_pc_account_custom pac ON pac.account_number = vport.portfolio_account_number
                        LEFT JOIN vtiger_securities s ON s.security_id = vpos.symbol_id
                        LEFT JOIN vtiger_security_types st ON st.security_type_id = s.security_type_id
                        LEFT JOIN vtiger_pc_security_codes sc ON sc.security_id = vpos.symbol_id AND sc.code_type_id = 20
                        LEFT JOIN vtiger_pc_codes c ON c.code_id = sc.code_id
                        LEFT JOIN vtiger_pc_transactions t ON t.transaction_id = (select transaction_id FROM vtiger_pc_transactions WHERE portfolio_id = vport.portfolio_id LIMIT 1)
                        LEFT JOIN vtiger_pc_interface_originations o ON t.origination_id = o.origination_id
                        {$where}
                        GROUP BY vpos.symbol_id, vport.portfolio_id
                        HAVING current_value <> 0) AS summed
                        LEFT JOIN vtiger_contactdetails con ON con.contactid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn AND vcf.ssn != '' LIMIT 1)
                        LEFT JOIN vtiger_crmentity e ON e.crmid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn AND vcf.ssn != '' LIMIT 1)
                    GROUP BY summed.portfolio_id";
        $result = $adb->pquery($query, array());
        return $result;
    }

    public function UpdatePortfolioSummary($portfolio_id=null){
        global $adb;
        if(strlen($portfolio_id) > 1)
            $where = " WHERE vport.portfolio_id IN ({$portfolio_id})";

////REMOVED FOR NEW QUERY        $result = $this->CalculatePortfolioSummary($portfolio_id);
        $query =   "INSERT INTO vtiger_portfolio_summary (account_number, first_name, last_name, account_type, total_value, market_value, cash_value, annual_management_fee,
                    assigned_to, nickname, contact_id, advisor_id, origination, master_account, tax_id, inception, last_modified, 
                    household_account, production_number) 
                            SELECT summed.account_number, summed.first_name, summed.last_name, summed.account_type,
                                    SUM(summed.current_value) AS total_value, 
                                    SUM(CASE WHEN summed.type_id != 11 THEN summed.current_value END) AS market_value,
                                    SUM(CASE WHEN summed.type_id = 11 THEN summed.current_value END) AS cash_value,
                                    summed.trailing_12_fees,
                                    (CASE WHEN e.smownerid is null OR e.smownerid = 1 THEN (SELECT user_id FROM vtiger_pc_advisor_linking WHERE pc_id = summed.advisor_id LIMIT 1) ELSE e.smownerid END) AS assigned_to,		
                                    summed.nickname,
                                    (SELECT contactid FROM vtiger_contactscf vcf JOIN vtiger_crmentity e ON e.crmid = vcf.contactid WHERE e.deleted = 0 AND summed.tax_id = vcf.ssn AND vcf.ssn != '' LIMIT 1) AS contactid,
                                    summed.advisor_id,
                                    (SELECT interface_name FROM vtiger_pc_interface_originations o 
                                                       WHERE o.origination_id = (SELECT origination_id FROM vtiger_pc_transactions t WHERE t.portfolio_id = summed.portfolio_id LIMIT 1)) AS origination,
                                    summed.master_account, summed.tax_id, summed.inception, CURRENT_DATE() AS last_modified,
                                    con.accountid AS household_account,
                                    (SELECT pc_name FROM vtiger_pc_advisors WHERE pc_id = summed.advisor_id) AS production_number
                                    FROM
                                            (SELECT vport.advisor_id, vport.portfolio_account_number AS account_number, vport.portfolio_id, vport.portfolio_first_name AS first_name, vport.portfolio_last_name AS last_name, vport.portfolio_account_type AS account_type, 
                                                            vport.master_account, vport.portfolio_tax_id AS tax_id, vport.portfolio_inception_date AS inception, pac.nickname, o.interface_name AS origination,
                                                            st.security_type_name, c.code_description, s.security_symbol, SUM(current_value) AS current_value, st.security_type_id AS type_id, t12.cba_total AS trailing_12_fees
                                            FROM vtiger_positioninformation vpos
                                            LEFT JOIN vtiger_portfolios vport ON vpos.account_number = vport.portfolio_account_number
                                            LEFT JOIN (SELECT SUM(cost_basis_adjustment) AS cba_total, t.portfolio_id
                                                                    FROM vtiger_pc_transactions t
                                                                    WHERE report_as_type_id = 60 
                                                                    AND trade_date BETWEEN now() - interval 12 month AND now() GROUP BY portfolio_id) AS t12 ON t12.portfolio_id = vport.portfolio_id
                                            LEFT JOIN vtiger_pc_account_custom pac ON pac.account_number = vport.portfolio_account_number
                                            LEFT JOIN vtiger_securities s ON s.security_id = vpos.symbol_id
                                            LEFT JOIN vtiger_security_types st ON st.security_type_id = s.security_type_id
                                            LEFT JOIN vtiger_pc_security_codes sc ON sc.security_id = vpos.symbol_id AND sc.code_type_id = 20
                                            LEFT JOIN vtiger_pc_codes c ON c.code_id = sc.code_id
                                            LEFT JOIN vtiger_pc_transactions t ON t.transaction_id = (select transaction_id FROM vtiger_pc_transactions WHERE portfolio_id = vport.portfolio_id LIMIT 1)
                                            LEFT JOIN vtiger_pc_interface_originations o ON t.origination_id = o.origination_id
                                            {$where}
                                            GROUP BY vpos.symbol_id, vport.portfolio_id
                                            HAVING current_value <> 0) AS summed
                                            LEFT JOIN vtiger_contactdetails con ON con.contactid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn AND vcf.ssn != '' LIMIT 1)
                                            LEFT JOIN vtiger_crmentity e ON e.crmid = (SELECT contactid FROM vtiger_contactscf vcf WHERE summed.tax_id = vcf.ssn AND vcf.ssn != '' LIMIT 1)
                                    GROUP BY summed.portfolio_id
                    ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), account_type = VALUES(account_type), total_value = VALUES(total_value), 
                                        market_value=VALUES(market_value), cash_value=VALUES(cash_value), annual_management_fee=VALUES(annual_management_fee), assigned_to=VALUES(assigned_to),
                                        nickname=VALUES(nickname), contact_id=VALUES(contact_id), advisor_id=VALUES(advisor_id), origination=VALUES(origination), master_account=VALUES(master_account),
                                        tax_id=VALUES(tax_id), inception=VALUES(inception), last_modified=NOW(), household_account=VALUES(household_account), production_number=VALUES(production_number)";
        $adb->pquery($query, array());
        echo "Finished calculating Portfolio Summary... About to insert " . date('Y-m-d H:i:s') . "<br />\r\n";
        ob_flush();
        flush();
        /*REMOVED FOR NEW QUERY
				$query = "INSERT INTO vtiger_portfolio_summary (account_number, first_name, last_name, account_type, total_value, market_value, cash_value, annual_management_fee,
																assigned_to, nickname, contact_id, advisor_id, origination, master_account, tax_id, inception, last_modified,
																household_account, production_number) VALUES ";
				$update = " ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), account_type = VALUES(account_type), total_value = VALUES(total_value),
							market_value=VALUES(market_value), cash_value=VALUES(cash_value), annual_management_fee=VALUES(annual_management_fee), assigned_to=VALUES(assigned_to),
							nickname=VALUES(nickname), contact_id=VALUES(contact_id), advisor_id=VALUES(advisor_id), origination=VALUES(origination), master_account=VALUES(master_account),
							tax_id=VALUES(tax_id), inception=VALUES(inception), last_modified=NOW(), household_account=VALUES(household_account), production_number=VALUES(production_number)";
				$count = 0;
				$reset = 0;
				$num_results = $adb->num_rows($result);
				$query_extension = "";
				foreach($result AS $k => $v){
					$first_name = mysql_real_escape_string($v['first_name']);
					$last_name = mysql_real_escape_string($v['last_name']);
					$nickname = mysql_real_escape_string($v['nickname']);
					$account_number = mysql_real_escape_string($v['account_number']);
					$query_extension .= "('{$account_number}', '{$first_name}', '{$last_name}', '{$v['account_type']}', '{$v['total_value']}', '{$v['market_value']}',
										  '{$v['cash_value']}', '{$v['trailing_12_fees']}', '{$v['assigned_to']}', '{$nickname}', '{$v['contactid']}', '{$v['advisor_id']}',
										  '{$v['origination']}', '{$v['master_account']}', '{$v['tax_id']}', '{$v['inception']}', NOW(), '{$v['household_account']}', '{$v['production_number']}')";
					$count++;
					$reset++;
					if($count < $num_results && $reset < $this->reset){//If we need to reset, don't add a comma
						$query_extension .= ",";
					}

					if($reset >= $this->reset)
					{
						$reset = 0;//Reset the query insert
						$this->ExecuteQuery($query . $query_extension . $update, "Inserting into Portfolio Summary table ");
						$query_extension = '';
					}
				}

				$this->ExecuteQuery($query . $query_extension . $update, "Inserting into Portfolio Summary table -- Final insert ");
		 *
		 */
        /*        global $adb;
				$result = $this->CalculatePortfolioSummary($portfolio_id);
				echo "Finished calculating Portfolio Summary... About to insert " . date('Y-m-d H:i:s') . "<br />\r\n";
				ob_flush();
				flush();
				$query = "INSERT INTO vtiger_portfolio_summary (account_number, first_name, last_name, account_type, total_value, market_value, cash_value, annual_management_fee,
																assigned_to, nickname, contact_id, advisor_id, origination, master_account, tax_id, inception, last_modified,
																household_account, production_number) VALUES ";
				$update = " ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), account_type = VALUES(account_type), total_value = VALUES(total_value),
							market_value=VALUES(market_value), cash_value=VALUES(cash_value), annual_management_fee=VALUES(annual_management_fee), assigned_to=VALUES(assigned_to),
							nickname=VALUES(nickname), contact_id=VALUES(contact_id), advisor_id=VALUES(advisor_id), origination=VALUES(origination), master_account=VALUES(master_account),
							tax_id=VALUES(tax_id), inception=VALUES(inception), last_modified=NOW(), household_account=VALUES(household_account), production_number=VALUES(production_number)";
				$count = 0;
				$reset = 0;
				$num_results = $adb->num_rows($result);
				$query_extension = "";
				foreach($result AS $k => $v){
					$first_name = mysql_real_escape_string($v['first_name']);
					$last_name = mysql_real_escape_string($v['last_name']);
					$nickname = mysql_real_escape_string($v['nickname']);
					$account_number = mysql_real_escape_string($v['account_number']);
					$query_extension .= "('{$account_number}', '{$first_name}', '{$last_name}', '{$v['account_type']}', '{$v['total_value']}', '{$v['market_value']}',
										  '{$v['cash_value']}', '{$v['trailing_12_fees']}', '{$v['assigned_to']}', '{$nickname}', '{$v['contactid']}', '{$v['advisor_id']}',
										  '{$v['origination']}', '{$v['master_account']}', '{$v['tax_id']}', '{$v['inception']}', NOW(), '{$v['household_account']}', '{$v['production_number']}')";
					$count++;
					$reset++;
					if($count < $num_results && $reset < $this->reset){//If we need to reset, don't add a comma
						$query_extension .= ",";
					}

					if($reset >= $this->reset)
					{
						$reset = 0;//Reset the query insert
						$this->ExecuteQuery($query . $query_extension . $update, "Inserting into Portfolio Summary table ");
						$query_extension = '';
					}
				}

				$this->ExecuteQuery($query . $query_extension . $update, "Inserting into Portfolio Summary table -- Final insert ");*/
    }

    /**
     * Close accounts if needed
     */
    public function CloseAccounts(){
        global $adb;
        if(!$this->pc->connect())//Try connecting
            return false;

        $query = "SELECT * FROM Portfolios WHERE DataSetID IN ({$this->datasets}) AND PortfolioTypeID = 16 AND ClosedAccountFlag=1";
        $result = mssql_query($query);

        $info = array();//Holds all row info
        if(mssql_num_rows($result) > 0)
            while($row = mssql_fetch_array($result))
                $info[] = $row['PortfolioID'];
        $closed = SeparateArrayWithCommas($info);

        $query = "UPDATE vtiger_portfolios SET account_closed = 1 WHERE portfolio_id IN ({$closed})";
        $adb->pquery($query, array());
    }

    /**
     * Removes all null dataset ID's from the vtiger_portfolios table and backs them up into vtiger_bad_portfolios
     * @global type $adb
     */
    public function RemoveNullDatasetPortfolios(){
        global $adb;
        $query = "INSERT INTO vtiger_bad_portfolios
                    SELECT * FROM vtiger_portfolios p WHERE data_set_id IS NULL
                  ON DUPLICATE KEY UPDATE portfolio_id = p.portfolio_id;";
        $adb->pquery($query, array());
        $query = "DELETE FROM vtiger_portfolios WHERE data_set_id IS NULL";
        $adb->pquery($query, array());
    }

    public function MatchPCAndRemoveFromCRM(){
        global $adb;

        $pc = $this->GetAllPortfolioIDsFromPC();

        if($pc){
            $pids = array();
            foreach($pc AS $k => $v){
                $pids[] = $v['PortfolioID'];
            }
            $questions = generateQuestionMarks($pids);
            $query = "SELECT portfolio_id FROM vtiger_portfolios WHERE portfolio_id NOT IN ({$questions})";
            $result = $adb->pquery($query, array($pids));
            $bad = array();
            foreach($result AS $k => $v){
                $bad[] = $v['portfolio_id'];
            }

            $questions = generateQuestionMarks($bad);
            $query = "INSERT INTO vtiger_bad_portfolios 
                        SELECT * FROM vtiger_portfolios 
                        WHERE portfolio_id IN ({$questions})
                      ON DUPLICATE KEY UPDATE portfolio_id = VALUES(portfolio_id)";
            $adb->pquery($query, array($bad));

            $query = "DELETE FROM vtiger_portfolios WHERE portfolio_id IN ({$questions})";
            /**ENABLE THE NEXT LINE WHEN READY TO DELETE**/
            $adb->pquery($query, array($bad));

            return $bad;
        }
        return 0;
    }

    /**
     * Copy Portfolios from PC to the CRM.  If no account number is entered, it will snag them all.  If no date is entered, it will snag them all between now and the last known modified
     * portfolio date in the crm.
     * @global type $adb
     * @param type $account_number_override
     * @param type $date
     */
    public function CopyPortfoliosFromPCToCRM($pids=null, $date=null){
        global $adb;
//        if(strlen($date) < 2)
//            $date = $this->GetLastModifiedDate();

        $pc = $this->GetAllPortfoliosFromPC($pids, $date);
        if($pc && is_array($pc))
        {
            $count = 0;
            $reset = 0;
            $query = "INSERT INTO vtiger_portfolios (portfolio_id, portfolio_portfolio_type_id, portfolio_account_id, portfolio_first_name, portfolio_last_name, portfolio_account_number,
                                                         portfolio_inception_date, portfolio_birth_date, portfolio_user_id, portfolio_account_name, portfolio_account_type, portfolio_market_value,
                                                         portfolio_cash_value, portfolio_bond_value, portfolio_annual_revenue, portfolio_cost_basis, portfolio_total_value, portfolio_net_new_cash_trans,
                                                         portfolio_net_new_cash, portfolio_tax_id, portfolio_service_provider_id, portfolio_client_organization_id, created_date, created_by,
                                                         modified_date, modified_by, marked_for_deletion, advisor_fee, advisor_id, master_account, data_set_id, BillingInceptionDate, PerformanceInceptionDate, origination_id) VALUES ";
            $query_extension = "";
            $update = " ON DUPLICATE KEY UPDATE portfolio_id = VALUES(portfolio_id),
                                                portfolio_portfolio_type_id = VALUES(portfolio_portfolio_type_id),
                                                portfolio_account_id = VALUES(portfolio_account_id),
                                                portfolio_first_name = VALUES(portfolio_first_name),
                                                portfolio_last_name = VALUES(portfolio_last_name),
                                                portfolio_account_number = VALUES(portfolio_account_number),
                                                portfolio_inception_date = VALUES(portfolio_inception_date),
                                                portfolio_birth_date = VALUES(portfolio_birth_date),
                                                portfolio_account_name = VALUES(portfolio_account_name),
                                                portfolio_account_type = VALUES(portfolio_account_type),
                                                portfolio_market_value = VALUES(portfolio_market_value),
                                                portfolio_cash_value = VALUES(portfolio_cash_value),
                                                portfolio_bond_value = VALUES(portfolio_bond_value),
                                                portfolio_annual_revenue = VALUES(portfolio_annual_revenue),
                                                portfolio_cost_basis = VALUES(portfolio_cost_basis),
                                                portfolio_total_value = VALUES(portfolio_total_value),
                                                portfolio_net_new_cash_trans = VALUES(portfolio_net_new_cash_trans),
                                                portfolio_net_new_cash = VALUES(portfolio_net_new_cash),
                                                portfolio_tax_id = VALUES(portfolio_tax_id),
                                                portfolio_service_provider_id = VALUES(portfolio_service_provider_id),
                                                portfolio_client_organization_id = VALUES(portfolio_client_organization_id),
                                                created_date = VALUES(created_date),
                                                created_by = VALUES(created_by),
                                                modified_date = VALUES(modified_date),
                                                modified_by = VALUES(modified_by),
                                                marked_for_deletion = VALUES(marked_for_deletion),
                                                advisor_fee = VALUES(advisor_fee),
                                                advisor_id = VALUES(advisor_id),
                                                master_account = VALUES(master_account),
                                                data_set_id = VALUES(data_set_id),
                                                BillingInceptionDate = VALUES(BillingInceptionDate),
                                                PerformanceInceptionDate = VALUES(PerformanceInceptionDate),
                                                origination_id = VALUES(origination_id)";

            foreach($pc AS $k => $v)
            {
                $modified_date = $this->pc->ConvertDate($v['LastModifiedDate']);
                $created_on = $this->pc->ConvertDate($v['CreatedOn']);
                $inception_date = $this->pc->ConvertDate($v['PerformanceInceptionDate']);
                $birthdate = $this->pc->ConvertDate($v['BirthDate']);
                $BillingInceptionDate = $this->pc->ConvertDate($v['BillingInceptionDate']);
                $PerformanceInceptionDate = $this->pc->ConvertDate($v['PerformanceInceptionDate']);
                $firstname = mysql_real_escape_string($v['FirstName']);
                $lastname = mysql_real_escape_string($v['LastName']);
                $companyname = mysql_real_escape_string($v['CompanyName']);
                $account_description = mysql_real_escape_string($v['AccountTypeDescription']);

                $query_extension .= "(
                '{$v['PortfolioID']}',
                '{$v['PortfolioTypeID']}',
                '{$v['PortfolioAccountID']}',
                '{$firstname}',
                '{$lastname}',
                '{$v['AccountNumber']}',
                '{$inception_date}',
                '{$birthdate}',
                0,
                '{$companyname}',
                '{$account_description}',
                0, 0, 0, 0, 0, 0, 0, 0,
                '{$v['TaxID']}',
                0, 0,
                '{$created_on}',
                '{$v['CreatedBy']}',
                '{$modified_date}',
                '{$v['LastModifiedUserID']}',
                '{$v['ClosedAccountFlag']}',
                '{$v['advisor_fee']}',
                '{$v['AdvisorID']}',
                '{$v['MasterAccount']}',
                '{$v['DataSetID']}',
                '{$BillingInceptionDate}',
                '{$PerformanceInceptionDate}',
                '{$v['OriginationID']}')";

                $count++;
                $reset++;
                if($count < sizeof($pc) && $reset < $this->reset){//If we need to reset, don't add a comma
                    $query_extension .= ",";
                }

                if($reset >= $this->reset)
                {
                    $reset = 0;//Reset the query insert
                    $this->ExecuteQuery($query . $query_extension . $update, "Inserting portfolios into vtiger_portfolios table ");
                    $query_extension = '';
                }

            }
            $this->ExecuteQuery($query . $query_extension . $update, "Inserting portfolios into vtiger_portfolios table -- Final insert ");
        }
        else
        {
            $this->SyncPortfoliosFromPortfolioInformation();
        }
        echo "About to copy from portfolios to portfolio_summary (The basic stuff) " . date('Y-m-d H:i:s') . "<br />\r\n";
        ob_flush();
        flush();
        $this->CopyBasicPortfolInfoToPortfolioSummaryTable();
        echo "Finished the copy of portfolios to portfolio_summary " . date('Y-m-d H:i:s') . "<br />\r\n";
        ob_flush();
        flush();
    }

    /**
     * Get the portfolio value as of the specified date
     * @global type $adb
     * @param type $portfolio_ids
     * @param type $date
     * @return type
     */
    public function GetPortfolioValueAsOfDate($portfolio_ids, $date){
        global $adb;
        $query = "SELECT SUM(t2.security_value) AS total FROM 
                    (SELECT *, t1.quantity*t1.price AS security_value FROM 
                     (SELECT SUM(t.quantity) AS quantity, SUM(t.cost_basis_adjustment) AS cost_basis, s.security_symbol, s.security_description, t.symbol_id, t.portfolio_id, p.portfolio_account_number, p.advisor_id,
                                                                             st.security_type_name AS security_type, COUNT(*),
                     (SELECT CASE WHEN (st.security_type_name = 'Cash') THEN 1 ELSE (SELECT price 
                     * CASE WHEN (s.security_factor > 0) THEN s.security_price_adjustment * s.security_factor
                     ELSE s.security_price_adjustment END
                     FROM vtiger_pc_security_prices 
                      WHERE price_date = (SELECT max(price_date) FROM vtiger_pc_security_prices WHERE security_id=s.security_id AND price > 0 AND price_date <= ?) 
                      AND security_id=s.security_id) END) AS price
                       FROM vtiger_pc_transactions t
                       LEFT JOIN vtiger_pc_transactions_pricing tp ON t.transaction_id = tp.transaction_id
                       LEFT JOIN vtiger_securities s ON t.symbol_id = s.security_id
                       LEFT JOIN vtiger_security_types st ON st.security_type_id = s.security_type_id
                       LEFT JOIN vtiger_portfolios p ON p.portfolio_id = t.portfolio_id
                       WHERE s.security_data_set_id IN ({$this->datasets})
                       AND t.status_type_id = 100
                       AND p.portfolio_account_number != ''
                       AND p.portfolio_id IN ({$portfolio_ids})
                       AND t.trade_date <= ?
                       GROUP BY t.portfolio_id, t.symbol_id) AS t1
                     WHERE t1.quantity != 0) AS t2";

        $result = $adb->pquery($query, array($date, $date));

        return $adb->query_result($result, 0, "total");
    }

    public function GetFlowDataAsOfDates($pids, $start, $end){
        global $adb;
        $query = "SELECT SUM(t2.security_value) AS flow FROM 
                    (SELECT *, t1.quantity*t1.price AS security_value FROM 
                     (SELECT SUM(t.quantity) AS quantity, SUM(t.cost_basis_adjustment) AS cost_basis, s.security_symbol, s.security_description, t.symbol_id, t.portfolio_id, p.portfolio_account_number, p.advisor_id,
                                                                             st.security_type_name AS security_type, COUNT(*),
                     (SELECT CASE WHEN (st.security_type_name = 'Cash') THEN 1 ELSE (SELECT price 
                     * CASE WHEN (s.security_factor > 0) THEN s.security_price_adjustment * s.security_factor
                     ELSE s.security_price_adjustment END
                     FROM vtiger_pc_security_prices 
                      WHERE price_date = (SELECT max(price_date) FROM vtiger_pc_security_prices WHERE security_id=s.security_id AND price > 0 AND price_date <= ?) 
                      AND security_id=s.security_id) END) AS price
                       FROM vtiger_pc_transactions t
                       LEFT JOIN vtiger_pc_transactions_pricing tp ON t.transaction_id = tp.transaction_id
                       LEFT JOIN vtiger_securities s ON t.symbol_id = s.security_id
                       LEFT JOIN vtiger_security_types st ON st.security_type_id = s.security_type_id
                       LEFT JOIN vtiger_portfolios p ON p.portfolio_id = t.portfolio_id
                       WHERE s.security_data_set_id IN ({$this->datasets})
                       AND t.status_type_id = 100
                       AND p.portfolio_account_number != ''
                       AND p.portfolio_id IN ({$pids})
                       AND (t.activity_id IN (10, 50, 60, 80, 120, 130, 150, 160)
                       OR t.report_as_type_id IN (60, 70, 130)
                       OR (t.activity_id = 160 AND t.report_as_type_id = 80))
                       AND t.portfolio_id IN ({$pids})
                       AND (t.trade_date >= ? AND t.trade_date <= ?)
                       GROUP BY t.portfolio_id, t.symbol_id) AS t1
                     WHERE t1.quantity != 0) AS t2";//Buy 70, Sell 140 taken out
        $result = $adb->pquery($query, array($end, $start, $end));
        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, "flow");
    }
}

?>
