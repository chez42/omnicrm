<?php

class ReconcileTransactions{
    public $account_number, $date, $types, $custodian, $cashname;

    public function __construct($account_number, $date, array $types){
        $this->account_number = $account_number;
        $this->date = $date;
        $this->types = $types;
        $this->custodian = PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($account_number);
        switch(strtoupper($this->custodian)){
            case "TD":
                $this->cashname = "TDCASH";
                break;
            case "FIDELITY":
                $this->cashname = "FCASH";
                break;
            case "SCHWAB":
                $this->cashname = "SCASH";
                break;
            case "PERSHING":
                $this->cashname = "PCASH";
                break;
            case "AXOS":
                $this->cashname = "CASHTCA";
                break;
        }

        self::GenerateReleventTransactions();
    }

    private function GenerateReleventTransactions(){
        global $adb;

        $query = "DROP TABLE IF EXISTS ReleventTransactions";
        $adb->pquery($query, array());

        $type_questions = generateQuestionMarks($this->types);
        $query = "CREATE TEMPORARY TABLE ReleventTransactions
                  WITH DATA AS (
                  SELECT t.account_number, t.transactionsid, CASE WHEN t.security_symbol = '' AND transaction_type IN ('Flow', 'Income', 'Expense') THEN '{$this->cashname}' ELSE t.security_symbol END AS security_symbol, 
                         CONCAT(t.operation, t.quantity) AS quantity, t.operation, t.trade_date, 
                         CONCAT(t.operation, cf.net_amount) AS net_amount, transaction_type, transaction_activity
                  FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  WHERE account_number IN (?)
                  AND trade_date <= ?
                  AND transaction_type IN ({$type_questions})
                  GROUP BY security_symbol, trade_date, transaction_type)
                  
                  SELECT DATA.*, SUM(quantity) OVER (PARTITION BY security_symbol ORDER BY trade_date) AS cumulative_quantity
                  FROM DATA
                  GROUP BY security_symbol, trade_date, transaction_type
                  ORDER BY trade_date";
        $adb->pquery($query, array($this->account_number, $this->date, $this->types));
    }

    public function GetReleventTransactions(){
        global $adb;
        $query = "SELECT * FROM ReleventTransactions ORDER BY trade_date ASC";
        $result = $adb->pquery($query, array());
        $data = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $data[] = $v;
            }
            return $data;
        }
        return null;
    }

    public function GetSymbolQuantitiesSummedAsOfDate(array $symbols, $date){
        global $adb;
        $questions = generateQuestionMarks($symbols);
        $data = array();

        $query = "SELECT security_symbol, SUM(quantity) AS quantity, trade_date
                  FROM ReleventTransactions
                  WHERE security_symbol IN ({$questions})
                  AND trade_date <= ?
                  GROUP BY account_number, security_symbol
                  ORDER BY trade_date DESC";
        $result = $adb->pquery($query, array($symbols, $date));
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $data[$v['security_symbol']] = $v['quantity'];
            }
            return $data;
        }
        return null;
    }

    public function CompareQuantiesToPositions(array $positions){

    }
}