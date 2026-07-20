<?php
class cAxosPortfolios {
    static public function CreateNewPortfoliosForRepCodes($rep_codes){
        global $adb;
        $custodian_accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromCustodianUsingRepCodes("Axos", $rep_codes);
        $crm_accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromRepCodeOpenAndClosed($rep_codes);

        $new = array_diff($custodian_accounts, $crm_accounts);

        if(!empty($new)) {
            $questions = generateQuestionMarks($new);

            $query = "SELECT p.account_number, 'Axos' AS custodian, p.name1 AS description, 
                             p.rep_id AS rep_code, NOW() AS generated_time, COALESCE(u.id, 1) AS userid
                      FROM custodian_omniscient.custodian_portfolios_axos p 
                      LEFT JOIN vtiger_users u ON (p.rep_id != '' AND u.advisor_control_number LIKE CONCAT('%',p.rep_id,'%'))
                      WHERE p.account_number IN ({$questions}) GROUP BY p.account_number";
            $result = $adb->pquery($query, array($new));

            if($adb->num_rows($result) > 0) {
                while ($v = $adb->fetchByAssoc($result)) {
                    $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");

                    $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], 1, $v['userid'], 1, 'PortfolioInformation', $v['generated_time'], $v['generated_time'], $v['account_number']));

                    $query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination)
                              VALUES (?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], $v['account_number'], $v['custodian']));

                    $query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, description, production_number)
                              VALUES (?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], $v['description'], $v['rep_code']));
                }
            }
        }
    }

    static public function UpdateAllPortfoliosForAccounts(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);

        // Derive derived balances for these accounts
        PortfolioInformation_ConvertCustodian_Model::GetBalancesAxosAndWrite($account_number, null);

        // Select latest derived balance
        $query = "SELECT f.name1, f.name2, f.address1, f.address2, f.city, f.state, f.zip, f.account_type, f.rep_id,
                         bal.account_value, bal.securities_value, bal.net_cash, bal.as_of_date, bal.filename,
                         0 AS accountclosed, p.portfolioinformationid
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid) 
                  JOIN custodian_omniscient.custodian_portfolios_axos f ON f.account_number = p.account_number
                  JOIN custodian_omniscient.custodian_balances_axos bal ON bal.account_number = p.account_number 
                       AND bal.as_of_date = (SELECT MAX(as_of_date) FROM custodian_omniscient.custodian_balances_axos WHERE account_number = p.account_number)
                  WHERE p.account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_number));

        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_portfolioinformation p 
                      JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                      SET p.first_name = ?, p.last_name = ?, cf.address1 = ?, cf.address2 = ?, cf.city = ?, cf.state = ?,
                          cf.zip = ?, p.account_type = ?, cf.production_number = ?, 
                          p.total_value = ?, cf.securities = ?, cf.cash = ?, cf.stated_value_date = ?, 
                          cf.custodian_source = ?, p.cash_value = ?, p.accountclosed = ?
                      WHERE p.portfolioinformationid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $name_parts = explode(' ', $v['name1'], 2);
                $first_name = isset($name_parts[1]) ? $name_parts[0] : '';
                $last_name = isset($name_parts[1]) ? $name_parts[1] : $v['name1'];

                $params = array(
                    $first_name,
                    $last_name,
                    $v['address1'],
                    $v['address2'],
                    $v['city'],
                    $v['state'],
                    $v['zip'],
                    $v['account_type'],
                    $v['rep_id'],
                    $v['account_value'],
                    $v['securities_value'],
                    $v['net_cash'],
                    $v['as_of_date'],
                    $v['filename'],
                    $v['net_cash'],
                    $v['accountclosed'],
                    $v['portfolioinformationid']
                );
                $adb->pquery($query, $params);
            }
        }
    }

    static public function GetBalanceAsOfDate(array $account_numbers, $date){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;
        $params[] = $date;

        $query = "SELECT account_number, account_value 
                  FROM custodian_omniscient.custodian_balances_axos 
                  WHERE account_number IN ({$questions}) 
                  AND as_of_date = ?";
        $result = $adb->pquery($query, $params);

        $data = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $data[$r['account_number']] = $r['account_value'];
            }
        }
        return $data;
    }

    static public function GetBeginningBalanceAsOfDate(array $account_numbers, $date){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;
        $params[] = $date;

        $query = "SELECT account_number, account_value AS value, as_of_date AS date
                  FROM custodian_omniscient.custodian_balances_axos 
                  WHERE account_number IN ({$questions}) 
                  AND as_of_date < ?
                  ORDER BY as_of_date 
                  DESC LIMIT 1";
        $result = $adb->pquery($query, $params);

        $data = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $data[$r['account_number']] = $r;
            }
        }
        return $data;
    }

    static public function GetEndingBalanceAsOfDate(array $account_numbers, $date){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;
        $params[] = $date;

        $query = "SELECT account_number, account_value AS value, as_of_date AS date
                  FROM custodian_omniscient.custodian_balances_axos 
                  WHERE account_number IN ({$questions}) 
                  AND as_of_date <= ?
                  ORDER BY as_of_date 
                  DESC LIMIT 1";
        $result = $adb->pquery($query, $params);

        $data = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $data[$r['account_number']] = $r;
            }
        }
        return $data;
    }

    static public function BalanceBetweenDates(array $account_number, $sdate, $edate){
        global $adb;
        $questions = generateQuestionMarks($account_number);
        $params = array();
        $params[] = $account_number;
        $params[] = $sdate;
        $params[] = $edate;

        $query = "SELECT account_number, account_value AS value, as_of_date AS date
                  FROM custodian_omniscient.custodian_balances_axos 
                  WHERE account_number IN ({$questions}) 
                  AND as_of_date BETWEEN ? AND ?
                  ORDER BY as_of_date";
        $result = $adb->pquery($query, $params);

        $data = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $data[$r['account_number']][] = $r;
            }
        }
        return $data;
    }

    static public function GetLatestBalance($account_number){
        global $adb;
        $query = "SELECT * 
                  FROM custodian_omniscient.custodian_balances_axos 
                  WHERE account_number = ?
                  ORDER BY as_of_date 
                  DESC LIMIT 1";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'account_value');
        }
        return null;
    }

    static public function GetEarliestBalanceAndDate(array $account_numbers){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;

        $query = "SELECT account_number, account_value, MIN(as_of_date) AS as_of_date
                  FROM custodian_omniscient.custodian_balances_axos 
                  WHERE account_number IN ({$questions}) 
                  GROUP BY account_number";
        $result = $adb->pquery($query, $params);

        $data = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $data[$r['account_number']] = array("account_value" => $r['account_value'],
                    "as_of_date" => $r['as_of_date']);
            }
        }
        return $data;
    }
}
