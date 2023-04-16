<?php

class Transactions_Module_Model extends Vtiger_Module_Model {
    public static function GetSecurityIdBySymbol($symbol){
        global $adb;
        $query = "SELECT security_id FROM vtiger_modsecurities WHERE security_symbol = ?";
        $result = $adb->pquery($query, array($symbol));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'security_id');
        } else
            return 0;
    }

    public static function ConvertPCTransactionsTableToModule(){
        global $adb;
        $query = "SELECT * FROM vtiger_pc_transactions WHERE data_set_id IN (1,28)";
        echo 'here';exit;
        $result = $adb->pquery($query, array());
        foreach($result AS $k => $v){
            print_r($v);exit;
        }
    }
/*
    public static function GetSymbolPriceForDate($symbol, $date){
        global $adb;
        $query = "SELECT price FROM vtiger_custodian_prices WHERE symbol = ? AND trade_date = ?";
        echo "PASSED IN: {$symbol}, {$date}<br />";
        $result = $adb->pquery($query, array($symbol, $date));
        if($adb->num_rows($result) > 0) {
            echo "RETURNING: " . $adb->query_result($result, 0, 'price') . " -- {$symbol}, {$date}<br />";
            return $adb->query_result($result, 0, 'price');
        }
        else {
            echo "RETURNING 0 -- {$tsymbol}, {$date}<br />";
            return 0;
        }
    }
*/
    public static function GetSymbolPriceForDate($symbol, $date){
        global $adb;
        $query = "SELECT price FROM vtiger_pc_security_prices WHERE symbol = ? AND price_date = ? AND data_set_id IN (1,28) LIMIT 1";

#        echo "PASSED IN: {$symbol}, {$date}<br />";
        $result = $adb->pquery($query, array($symbol, $date));
        if($adb->num_rows($result) > 0) {
#            echo "RETURNING: " . $adb->query_result($result, 0, 'price') . " -- {$symbol}, {$date}<br />";
            return $adb->query_result($result, 0, 'price');
        }
        else {
#            echo "RETURNING 0 -- {$symbol}, {$date}<br />";
            return 0;
        }
    }

    private static function CreatePortfolioOwnersTable($field = "account_number"){
        global $adb;

        $query = "DROP TABLE IF EXISTS PortfolioOwners";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE PortfolioOwners
        SELECT p.{$field} AS account_number, e.smownerid AS portfolio_owner
        FROM vtiger_portfolioinformation p
        JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid";

        $adb->pquery($query, array());
    }

    private static function UpdateTransactionOwnersFromOwnerTable(){
        global $adb;

        $query = "UPDATE vtiger_transactions t
        JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
        JOIN PortfolioOwners o ON t.account_number = o.account_number
        SET e.smownerid = o.portfolio_owner
        WHERE e.setype = 'Transactions' AND e.smownerid=1";

        $adb->pquery($query, array());
    }

    public static function AssignOwnerBasedOnAccountNumber(){
        self::CreatePortfolioOwnersTable("account_number");
        self::UpdateTransactionOwnersFromOwnerTable();
        self::CreatePortfolioOwnersTable("dashless");
        self::UpdateTransactionOwnersFromOwnerTable();
    }

    public static function GetPCPriceForDate($symbol, $date){
        global $adb;
        $query = "SELECT price FROM vtiger_pc_security_prices WHERE security_id = (SELECT security_id FROM vtiger_securities WHERE security_symbol = ? AND security_data_set_id IN (1,28) LIMIT 1) AND price_date = ? LIMIT 1";

#        echo "PASSED IN: {$symbol}, {$date}<br />";
        $result = $adb->pquery($query, array($symbol, $date));
        if($adb->num_rows($result) > 0) {
#            echo "RETURNING: " . $adb->query_result($result, 0, 'price') . " -- {$symbol}, {$date}<br />";
            return $adb->query_result($result, 0, 'price');
        }
        else {
#            echo "RETURNING 0 -- {$symbol}, {$date}<br />";
            return 0;
        }
    }
	
	
	function getWidgetTransactions($headerColumns, $pagingModel, $tradeDates, $transaction_activity){
	
		$db = PearDatabase::getInstance();

		$moduleName = $this->getName();
		
		$currentUserModel = Users_Record_Model::getCurrentUserModel();
		
		$queryGenerator = new QueryGenerator($moduleName, $currentUserModel);
	
		$headerColumns = array_merge($headerColumns, array("id", "transaction_type"));
		
		$queryGenerator->setFields( $headerColumns );

		$listviewController = new ListViewController($db, $currentUserModel, $queryGenerator);

		$query = $queryGenerator->getQuery();
		$query .= " AND ( ";
		$activityCount = count($transaction_activity);
		$act = 0;
		
		foreach($transaction_activity as $transactionActivity){
		    if($act > 0 && $act < $activityCount)
		        $query .= " OR ";
		    if($transactionActivity == 'Buy' || $transactionActivity == 'Sell'){
		        $query .= " ( vtiger_transactionscf.transaction_type = 'Trade' AND transaction_activity = '".$transactionActivity."' ) ";
		    }else{
		        $query .= " ( vtiger_transactionscf.transaction_type = 'Flow' AND transaction_activity = '".$transactionActivity."' ) ";
		    }
		    $act++;
		}
		$query .= ' ) ';	
		$startDate = (isset($tradeDates['start_date']))?$tradeDates['start_date']:"";
		
		if($startDate)
			$query .= " AND vtiger_transactions.trade_date >= '" . $startDate . "'";
		
		
		$endDate = (isset($tradeDates['end_date']))?$tradeDates['end_date']:"";
		
		if($endDate)
			$query .= " AND vtiger_transactions.trade_date <= '".$endDate."' ";
		
		$yesterdayTransactions = (isset($tradeDates['yesterday_transactions']))?$tradeDates['yesterday_transactions']:"";
		
		if($yesterdayTransactions)
			$query .= " AND vtiger_transactions.trade_date = '".$yesterdayTransactions."' ";
				
		$query = str_replace(" FROM ", ",vtiger_crmentity.crmid as id FROM ", $query);
		
		$query  .= "ORDER BY vtiger_transactionscf.net_amount DESC LIMIT ". $pagingModel->getStartIndex() .", ". ($pagingModel->getPageLimit()+1);
		
		$result = $db->pquery($query, array());
		
		$numOfRows = $db->num_rows($result);

		$moduleFocus= CRMEntity::getInstance($moduleName);

		$entries = $listviewController->getListViewRecords($moduleFocus,$moduleName,$result);

		$pagingModel->calculatePageRange($activities);
		
		if($numOfRows > $pagingModel->getPageLimit()){
			array_pop($entries);
			$pagingModel->set('nextPageExists', true);
		} else {
			$pagingModel->set('nextPageExists', false);
		}
		
		$listviewRecords = array();
		$index = 0;
		foreach ($entries as $id => $record) {
			$rawData = $db->query_result_rowdata($result, $index++);
			$record['id'] = $id;
			$listviewRecords[$id] = $this->getRecordFromArray($record, $rawData);
		}

		return $listviewRecords;
	}
	
	function getWidgetLinkURL($trade_dates, $transaction_activity){
		
		$listSearchParams = array();
		
		//Jim modified 'Flow' to 'Trade' - Mar. 7, 2023
		//$listSearchParams[0][0] = array('transaction_type', 'e', 'Flow');
		$listSearchParams[0][0] = array('transaction_type', 'e', 'Trade');
		$listSearchParams[0][1] = array('transaction_activity', 'e', $transaction_activity);
		$listSearchParams[0][2] = array('trade_date', 'bw', $trade_dates);
			
		$baseModuleListLink = $this->getListViewUrlWithAllFilter();
		$baseModuleListLink = str_replace("&view=List", "&view=GraphFilterList", $baseModuleListLink);
		return $baseModuleListLink.'&search_params='. json_encode($listSearchParams);
	}

	static public function GetGeneratedTransactionID($account_number, $symbol){
        global $adb;

        $query = "SELECT transactionsid 
                  FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid) 
                  WHERE account_number = ? AND security_symbol = ? AND system_generated = 1";
        $result = $adb->pquery($query, array($account_number, $symbol));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'transactionsid');
        }
        return 0;
    }

    static public function GetTDAccountsMissingNetAmountsReceiptOfSecurities(){
        global $adb;

        $query = "SELECT account_number 
                  FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING(transactionsid)
                  WHERE key_mnemonic_description = 'REC'
                  AND (net_amount IS NULL OR net_amount = 0)
                  AND origination = 'TD'
                  GROUP BY account_number";
        $result = $adb->pquery($query, array());
        $account_numbers = array();
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetch_array($result)) {
                $account_numbers[] = $x['account_number'];
            }
            return $account_numbers;
        }
        return 0;
    }

    static public function MarkDupes($account_number = null){
        global $adb;
        $where = "";
        $params = array();
        $questions = generateQuestionMarks($account_number);
        if($account_number){
            $where .= " WHERE account_number IN ({$questions}) ";
            $params[] = $account_number;
        }
        $query = "SELECT account_number, trade_date, crmids, cloudids 
                  FROM DupeTransactionsToManipulate 
                  {$where} ";
        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetch_array($result)) {
                self::MarkDupesFromCloudIDs($x['cloudids']);
            }
        }
    }

    static public function MarkDupesFromCloudIDs($cloudIDs){
        $ids = explode(",", $cloudIDs);
        $tmp = array();
        $safeID = substr($ids[0], 0, 3);//Safe ID represents the first cloud ID.  Anything that doesn't start with this ID is removed for this transaction only.  It allows for 412 being valid for one row and 420 to be valid for another for example
        foreach($ids AS $k => $v){
            $directoryID = substr($v, 0, 3);
            if($directoryID != $safeID){
                self::MarkCloudIDAsDupe($v);
            }
        }
    }

    static public function MarkCloudIDAsDupe($cloudID){
        global $adb;
        $query = "UPDATE vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  SET dupe_flag = 1, transaction_type = CONCAT(transaction_type,  ' (DUPE)'), transaction_activity = CONCAT(transaction_activity, ' (DUPE)')
                  WHERE cloud_transaction_id = ? AND dupe_flag != 1";
        $adb->pquery($query, array($cloudID));
    }

    static public function DoTransactionsExistForDateAlready($account_number, $date){
        global $adb;
        $query = "SELECT COUNT(*) AS count FROM vtiger_transactions WHERE account_number = ? AND trade_date = ?";
        $result = $adb->pquery($query, array($account_number, $date));

        if($adb->num_rows($result) > 0){
            if($adb->query_result($result, 0, 'count') > 0)
                return true;
        }
        return false;
    }

    static public function GetEarliestPositionDateForTD($account_number){
        global $adb;
        $query = "SELECT MIN(date) as min_date
                  FROM custodian_omniscient.custodian_positions_td 
                  WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'min_date');
        }
        return 0;
    }

    static public function CreateReceiptOfSecuritiesFromTDPositions($account_number){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        $created = array();
        $count = 0;

        $date = self::GetEarliestPositionDateForTD($account_number);
        if(self::DoTransactionsExistForDateAlready($account_number, $date)){
            $created['result'] = array("success" => 0,
                                       "message" => "Transactions already exist for the earliest position date, not creating anything!");
            echo json_encode($created);
            return;
        }
        $query = "SELECT p.symbol, pr.price, pr.factor, s.securitytype, cf.aclass,
                        (p.quantity + p.amount) AS quantity, (p.quantity + p.amount) * s.security_price * CASE WHEN cf.security_price_adjustment = 0 THEN 1 ELSE cf.security_price_adjustment END * CASE WHEN pr.factor > 0 THEN pr.factor ELSE 1 END AS total_value
                  FROM custodian_omniscient.custodian_positions_td p 
                  JOIN custodian_omniscient.custodian_prices_td pr ON p.date = pr.date AND P.symbol = pr.symbol
                  JOIN {$db_name}.vtiger_modsecurities s ON s.security_symbol = p.symbol
                  JOIN {$db_name}.vtiger_modsecuritiescf cf USING (modsecuritiesid)
                  WHERE account_number = ?
                  AND p.date = ?";
        $result = $adb->pquery($query, array($account_number, $date));
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetch_array($result)) {
                $symbol = $x['symbol'];
                $trade_date = $date;
                $quantity = $x['quantity'];
                $price = $x['price'];
                $net_amount = $x['total_value'];
                $asset_class = $x['aclass'];
                $security_type = $x['securitytype'];
                $record = Vtiger_Record_Model::getCleanInstance("Transactions");
                $record->set('mode', 'create');
                $data = $record->getData();
                $data['account_number'] = $account_number;
                $data['security_symbol'] = $symbol;
                $data['security_price'] = $price;
                $data['quantity'] = $quantity;
                $data['trade_date'] = $trade_date;
                $data['net_amount'] = $net_amount;
                $data['transaction_type'] = 'Flow';
                $data['transaction_activity'] = 'Receipt of securities';
                $data['security_type'] = $security_type;
                $data['base_asset_class'] = $asset_class;
                $data['asset_backed_factor'] = $asset_class;
                $data['system_generated'] = 1;
                $record->setData($data);
                $record->save();
                $count++;
            }
        }
        $created['result'] = array('success' => 1,
                                   'message' => 'Created ' . $count . ' new transactions for ' . $account_number . ' on ' . $date);
        echo json_encode($created);
        return;
    }

    static public function GetDistinctAccountNumbers(){
        global $adb;
        $query = "SELECT DISTINCT account_number FROM vtiger_transactions";
        $result = $adb->pquery($query, array());
        $account_numbers = array();
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetch_array($result)) {
                $account_numbers[] = $x['account_number'];
            }
        }
        return $account_numbers;
    }

    /**
     * Delete everything from the transactions module, including the vtiger_crmentity table
     * @param array $account_numbers
     */
    static public function RemoveTransactionsBelongingToAccounts(array $account_numbers){
        global $adb;
        if(sizeof($account_numbers) < 1)
            return;
        $questions = generateQuestionMarks($account_numbers);
        $query = "DELETE vtiger_transactions, vtiger_transactionscf, vtiger_crmentity 
                  FROM vtiger_transactions 
                  JOIN vtiger_transactionscf ON vtiger_transactions.transactionsid = vtiger_transactionscf.transactionsid
                  JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_transactions.transactionsid
                  WHERE account_number IN({$questions})";
        $adb->pquery($query, array($account_numbers));
    }

    static public function GetTransactionCount(array $account_numbers){
        global $adb;

        if(empty($account_numbers))
            return null;

        $questions = generateQuestionMarks($account_numbers);

        $query = "SELECT account_number, COUNT(*) as count
                  FROM vtiger_transactions 
                  WHERE account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_numbers));

        if($adb->num_rows($result) > 0){
            $data = array();
            while($v = $adb->fetchByAssoc($result)){
                $data[$v['account_number']] = $v['count'];
            }
            return $data;
        }
        return null;
    }

    /**
     * Get all CRM transactions up until the given date for the passed in accounts.  Returns an array key with account number, value is transactions
     * @param array $account_number
     * @param $date
     * @return array|null
     */
    static public function GetTransactionDataUpUntilDate(array $account_number, $date, array $transaction_type = null, array $transaction_activity = null){
        global $adb;
        $questions = generateQuestionMarks($account_number);

        $params = array();
        $params[] = $account_number;
        $params[] = $date;

        if($transaction_type != null){
            $questionType = generateQuestionMarks($transaction_type);
            $type = " AND transaction_type IN ({$questionType}) ";
            $params[] = $transaction_type;
        }

        if($transaction_activity != null){
            $questionType = generateQuestionMarks($transaction_activity);
            $activity = " AND transaction_activity IN (?) ";
            $params[] = $transaction_activity;
        }

        //TODO Add a function get latest position date as of provide date here!!
        $query = "SELECT t.account_number, t.security_symbol, t.quantity, t.operation, CONCAT(t.operation, cf.net_amount) AS net_amount
                  FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  WHERE account_number IN ({$questions}) 
                  AND trade_date <= ?
                  {$type} 
                  {$activity}";
        $result = $adb->pquery($query, $params);

        if($adb->num_rows($result) > 0){
            $data = array();
            while($v = $adb->fetchByAssoc($result)){
                $v['net_amount'] = $v['net_amount'];
                $data[$v['account_number']][$v['security_symbol']][] = $v;
            }
            return $data;
        }

        return null;
    }
}
