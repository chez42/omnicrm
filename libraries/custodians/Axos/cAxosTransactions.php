<?php
class cAxosTransactions {
    static public function CreateNewTransactionsForAccounts(array $account_number){
        global $adb;
        $account_questions = generateQuestionMarks($account_number);
        
        $query = "SELECT cloud_transaction_id 
                  FROM vtiger_transactions 
                  WHERE origination = 'Axos'
                  AND account_number IN ({$account_questions})";
        $result = $adb->pquery($query, array($account_number));
        
        $params = array();
        $transaction_ids = "";
        $params[] = $account_number;

        if($adb->num_rows($result) > 0){
            $transaction_ids = " AND t.journal_id NOT IN (SELECT cloud_transaction_id 
            FROM vtiger_transactions WHERE origination = 'Axos'
            AND account_number IN ({$account_questions})) ";
            $params[] = $account_number;
        }

        $query = "SELECT 1 AS ownerid, 
                         COALESCE(map.transaction_type, 'Income') AS transaction_type, 
                         COALESCE(map.transaction_activity, 'Interest') AS transaction_activity, 
                         t.journal_id AS transaction_id, t.account_number, COALESCE(t.trade_date, t.journal_date) AS trade_date, t.activity_code, 
                         CASE WHEN t.symbol = '' THEN 'SCASH' ELSE t.symbol END AS symbol, 
                         t.amount AS net_amount, t.units AS quantity, t.trade_fee, t.unit_cost, 
                         t.filename, t.rep_id, t.book_value, t.fed_withholding, t.security_fees,
                         COALESCE(NULLIF(map.description, ''), sec.security_name, CONCAT('Axos transaction ', t.activity_code)) AS description
                  FROM custodian_omniscient.custodian_transactions_axos t 
                  LEFT JOIN custodian_omniscient.axosmapping map ON map.activity_code = t.activity_code
                  LEFT JOIN vtiger_modsecurities sec ON sec.security_symbol = CASE WHEN t.symbol = '' THEN 'SCASH' ELSE t.symbol END
                  WHERE t.account_number IN ({$account_questions}) {$transaction_ids}  
                  GROUP BY t.journal_id";
                  
        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");

                if($v['quantity'] == 0 || $v['quantity'] === null)
                    $v['quantity'] = $v['net_amount'];
                
                $v['ownerid'] = PortfolioInformation_Module_Model::GetAccountOwnerFromAccountNumber($v['account_number']);
                $price = $v['unit_cost'] ? $v['unit_cost'] : 1;

                $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                          VALUES (?, ?, ?, ?, 'Transactions', NOW(), NOW(), ?)";
                $adb->pquery($query, array($v['crmid'], $v['ownerid'], $v['ownerid'], $v['ownerid'], $v['symbol']));

                $query = "INSERT INTO vtiger_transactions (transactionsid, account_number, security_symbol, security_price, quantity, trade_date, origination, cloud_transaction_id)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], $v['account_number'], $v['symbol'], $price, $v['quantity'], $v['trade_date'], 'Axos', $v['transaction_id']));

                $query = "INSERT INTO vtiger_transactionscf (transactionsid, custodian, transaction_type, transaction_activity, net_amount, broker_fee, other_fee, description, filename)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], 'Axos', $v['transaction_type'], $v['transaction_activity'], $v['net_amount'], $v['trade_fee'],
                                           $v['security_fees'] + $v['fed_withholding'], $v['description'], $v['filename']));
            }
        }
    }
}
