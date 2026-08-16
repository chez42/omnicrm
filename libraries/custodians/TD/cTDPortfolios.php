<?php
require_once("libraries/custodians/cCustodian.php");

class cTDPortfolioData{
    public $account_number, $custodian, $first_name, $last_name, $account_type,
        $account_value, $money_market;//PortfolioInformation
    public $as_of_date, $street, $address2, $address3, $address4, $address5, $address6, $city, $state, $zip, $rep_code,
        $master_rep_code, $omni_code;//PortfolioInformationCF

    public function __construct($data){
        $this->rep_code = $data['personal']['rep_code'];
        $this->custodian = 'TD';
        $this->account_value = $data['balance']['account_value'];
        $this->money_market = $data['balance']['money_market'];
        $this->as_of_date = $data['balance']['as_of_date'];
        $this->account_number = $data['personal']['account_number'];
        $this->last_name = $data['personal']['last_name'];
        $this->first_name = $data['personal']['first_name'];
        $this->account_type = $data['personal']['account_type'];
        $this->street = $data['personal']['street'];
        $this->address2 = $data['personal']['address2'];
        $this->address3 = $data['personal']['address3'];
        $this->address4 = $data['personal']['address4'];
        $this->address5 = $data['personal']['address5'];
        $this->address6 = $data['personal']['address6'];
        $this->city = $data['personal']['city'];
        $this->state = $data['personal']['state'];
        $this->zip = $data['personal']['zip'];
        $this->omni_code = $data['personal']['omni_code'];
    }
}

/**
 * Class cTDPortfolios
 * This class allows the pulling of data from the custodian database
 */
class cTDPortfolios extends cCustodian {
    use tPortfolios;
    private $portfolio_data;//Holds both personal and balance information

    /**
     * cTDPortfolios constructor.
     * @param string $custodian_name
     * @param string $database
     * @param string $module
     * @param string $portfolio_table
     * @param string $table (REFERS TO BALANCE TABLE)
     */
    public function __construct(string $custodian_name, string $database, string $module,
                                string $portfolio_table, string $balance_table, array $rep_codes, $columns=array('*')){
        $this->name = $custodian_name;
        $this->database = $database;
        $this->module = $module;
        $this->portfolio_table = $portfolio_table;
        $this->table = $balance_table;
        $this->columns = $columns;
        if(!empty($rep_codes)) {
            $this->SetRepCodes($rep_codes);
            $this->GetPortfolioPersonalData();
            $this->GetPortfolioBalanceData();
            $this->SetupPortfolioComparisons();
        }
    }

    public function SetAccountNumbers(array $account_numbers)
    {
        parent::SetAccountNumbers($account_numbers);
        $this->GetPortfolioPersonalData();
        $this->GetPortfolioBalanceData();
        $this->SetupPortfolioComparisons();
    }

    protected function GetPortfolioPersonalData(){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->account_numbers);
        $params[] = $this->account_numbers;

        if(empty($this->columns))
            $fields = "*";
        else{
            $fields = implode ( ", ", $this->columns );
        }

        $query = "SELECT {$fields} FROM {$this->database}.{$this->portfolio_table} WHERE account_number IN ({$questions}) AND account_number != ''";
        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $this->portfolio_data[$r['account_number']]['personal'] = $r;
            }
        }
        return $this->portfolio_data;
    }

    protected function GetPortfolioBalanceData($date=null){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->account_numbers);
        $params[] = $this->account_numbers;

        if(empty($this->columns))
            $fields = "*";
        else{
            $fields = implode ( ", ", $this->columns );
        }

        if(!$date)
            $date = $this->GetLatestBalanceDate("as_of_date");

        $params[] = $date;
        $query = "SELECT {$fields} FROM {$this->database}.{$this->table} 
                  WHERE account_number IN ({$questions}) AND as_of_date = ? AND account_number != ''";
        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $this->portfolio_data[$r['account_number']]['balance'] = $r;
            }
        }
        return $this->portfolio_data;
    }

    /**
     * Returns both the personal and balance data
     * @return mixed
     */
    public function GetPortfolioData(){
        return $this->portfolio_data;
    }

    /**
     * Create the new entity in the crmentity table
     * @param $crmid
     * @param $owner
     * @param cTDPortfolioData $data
     */
    protected function FillEntityTable($crmid, $owner, cTDPortfolioData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = 1;
        $params[] = $owner;
        $params[] = 1;
        $params[] = 'PortfolioInformation';
        $params[] = $data->account_number;
        $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                  VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";
        $adb->pquery($query, $params, true);
    }

    /**
     * Creates data in the vtiger_portfolioinformation table
     * @param $crmid
     * @param cTDPortfolioData $data
     */
    protected function FillPortfolioTable($crmid, cTDPortfolioData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = $data->account_number;
        $params[] = 'TD';
        $params[] = $data->account_type;
        $params[] = $data->first_name;
        $params[] = $data->last_name;
        $params[] = $data->account_value;
        $params[] = $data->money_market;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination, account_type, first_name, last_name, total_value, money_market_funds)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    /**
     * Creates data in the vtiger_portfolioinformationcf table
     * @param $crmid
     * @param cTDPortfolioData $data
     */
    protected function FillPortfolioCFTable($crmid, cTDPortfolioData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = $data->rep_code;
        $params[] = $data->street;
        $params[] = $data->address2;
        $params[] = $data->address3;
        $params[] = $data->address4;
        $params[] = $data->address5;
        $params[] = $data->address6;
        $params[] = $data->city;
        $params[] = $data->state;
        $params[] = $data->zip;
        $params[] = 1;
        $params[] = $data->as_of_date;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, production_number, address1, address2, address3, address4, address5, address6, city, state, zip, system_generated, stated_value_date)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    protected function UpdatePortfolios(cTDPortfolioData $data){
        global $adb;
        $params[] = 'TD';
        $params[] = $data->account_type;
        $params[] = $data->first_name;
        $params[] = $data->last_name;
        $params[] = $data->account_value;
        $params[] = $data->money_market;
        $params[] = $data->rep_code;
        $params[] = $data->street;
        $params[] = $data->address2;
        $params[] = $data->address3;
        $params[] = $data->address4;
        $params[] = $data->address5;
        $params[] = $data->address6;
        $params[] = $data->city;
        $params[] = $data->state;
        $params[] = $data->zip;
        $params[] = $data->as_of_date;
        $params[] = $data->account_number;

        $query = "UPDATE vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  SET origination='TD', account_type=?, first_name=?, last_name=?, total_value=0, money_market_funds=0,
                      accountclosed='1', closingdate='2023-09-01',
                      production_number=?, address1=?, address2=?, address3=?, address4=?, address5=?, address6=?, city=?, 
                      state=?, zip=?, stated_value_date=? WHERE account_number = ?";
        $adb->pquery($query, $params, true);
    }

    /**
     * Using the cTDPortfolioData class, create the portfolios.  Used with a pre-filled in cTDPortfolioData class (done manually)
     * @param cTDPortfolioData $data
     * @throws Exception
     */
    public function CreateNewPortfolioUsingcTDPortfolioData(cTDPortfolioData $data){
        if(!$this->DoesAccountNumberExistInCRM($data->account_number)) {//If the account number doesn't exist yet, create it
            $crmid = $this->UpdateEntitySequence();
            $owner = $this->repcode_mapping[strtoupper($data->rep_code)];

            $this->FillEntityTable($crmid, $owner, $data);
            $this->FillPortfolioTable($crmid, $data);
            $this->FillPortfolioCFTable($crmid, $data);
            if($this->DoesAccountNumberExistInCRM($data->account_number))//Confirm the account now exists in the CRM
                $this->existing_accounts[] = $data->account_number;//Add the newly created account to existing accounts because it now exists
        }
    }

    /**
     * Auto creates the portfolio's based on the data loaded into the $portfolio_data member.  If the account number exists in this data, it will be created
     */
    public function CreateNewPortfoliosFromPortfolioData(array $account_numbers){
        if(!empty($account_numbers)) {
            foreach ($account_numbers AS $k => $v) {
                StatusUpdate::UpdateMessage("TDUPDATER", "Creating Portfolio {$v}");
                $data = $this->portfolio_data[$v];
                if (!empty($data)) {
                    $tmp = new cTDPortfolioData($data);
                    $this->CreateNewPortfolioUsingcTDPortfolioData($tmp);
                }
            }
        }
    }

    /**
     * Auto creates the portfolio's based on the data loaded into the $portfolio_data member.  If the account number exists in this data, it will be created
     */
    public function UpdatePortfoliosFromPortfolioData(array $account_numbers){
        if(!empty($account_numbers)) {
            foreach ($account_numbers AS $k => $v) {
                StatusUpdate::UpdateMessage("TDUPDATER", "Updating Portfolio {$v}");
                $data = $this->portfolio_data[$v];
                if (!empty($data)) {
                    $tmp = new cTDPortfolioData($data);
                    $this->UpdatePortfolios($tmp);
                }
#                PortfolioInformation_GlobalSummary_Model::CalculateAllAccountAssetAllocationValuesForAccount($k);
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
                  FROM custodian_omniscient.custodian_balances_td 
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
                  FROM custodian_omniscient.custodian_balances_td 
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
                  FROM custodian_omniscient.custodian_balances_td 
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
                  FROM custodian_omniscient.custodian_balances_td 
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
                  FROM custodian_omniscient.custodian_balances_td 
                  WHERE account_number = ?
                  ORDER BY as_of_date 
                  DESC LIMIT 1";

        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'account_value');
        }
        return null;
    }

    /**
     * Returns the earliest date and balance for passed in account numbers
     * @param array $account_numbers
     * @return array
     */
    static public function GetEarliestBalanceAndDate(array $account_numbers){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;

        $query = "SELECT account_number, account_value, MIN(as_of_date) AS as_of_date
                  FROM custodian_omniscient.custodian_balances_td 
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


    static public function CreateNewPortfoliosForRepCodes($rep_codes){
        global $adb;
        $custodian_accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromCustodianUsingRepCodes("TD", $rep_codes);
        $crm_accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromRepCodeOpenAndClosed($rep_codes);

        $new = array_diff($custodian_accounts, $crm_accounts);
        if(!empty($new)){
            $questions = generateQuestionMarks($new);
            $query = "SELECT p.account_number, 'TD' AS custodian, p.first_name, p.last_name, 
                             p.street, p.address2, p.address3, p.address4, p.address5, p.address6, p.city, p.state, p.zip, p.account_type, 
                             p.rep_code, cust.system_generated, NOW() AS generatedtime, p.rep_code, u.id AS userid, p.insert_date
                          FROM custodian_omniscient.custodian_portfolios_td p 
                          LEFT JOIN custodian_omniscient.custodian_portfolio_custom_properties cust ON p.account_number = cust.account_number 
                          JOIN vtiger_users u ON u.advisor_control_number LIKE CONCAT('%',rep_code,'%')
                          WHERE p.account_number IN ({$questions})";
            $result = $adb->pquery($query, array($new));

            if($adb->num_rows($result) > 0){
                while($v = $adb->fetchByAssoc($result)){
                    $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");

                    $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], 1, $v['userid'], 1, 'PortfolioInformation', $v['generatedtime'], $v['generatedtime'], $v['account_number']));

                    $query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination, account_type, first_name, last_name)
                              VALUES (?, ?, ?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], $v['account_number'], $v['custodian'], $v['account_type'], $v['first_name'], $v['last_name']));

                    $query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, production_number, address1, address2, address3, address4, address5, address6, city, state, zip, system_generated, custodian_inception)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], $v['rep_code'], $v['street'], $v['address2'], $v['address3'], $v['address4'], $v['address5'],
                        $v['address6'], $v['city'], $v['state'], $v['zip'], $v['system_generated'], $v['insert_date']));
                }
            }
        }
    }

    static public function UpdateAllPortfoliosForAccounts(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);

        $query = "UPDATE vtiger_portfolioinformation p 
                  SET p.total_value = 0, p.money_market_funds = 0, p.market_value = 0
                  WHERE account_number IN ({$questions})";
        $adb->pquery($query, array($account_number));

        $query = "SELECT f.account_value, f.money_market, f.money_market as cash_value, 0 AS market_value, f.as_of_date, f2.street, f2.address2, f2.address3, f2.address4, 
                  f2.address5, f2.address6, f2.city, f2.state, f2.zip, f2.account_type, f2.rep_code, f2.master_rep_code, f2.omni_code, 
                  0 as accountclosed, f2.insert_date, f2.insert_date AS insert_date2, p.portfolioinformationid
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                  JOIN custodian_omniscient.custodian_balances_td f ON f.account_number = p.account_number 
                  JOIN custodian_omniscient.custodian_portfolios_td f2 ON f2.account_number = f.account_number
                  JOIN custodian_omniscient.latestpositiondates lpd ON lpd.rep_code = cf.production_number
                  WHERE f.as_of_date = lpd.last_position_date
                  AND f.account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_number));

        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_portfolioinformation p 
                      JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                      SET p.total_value = ?, p.money_market_funds = ?, p.cash_value = ?, p.market_value = ?, cf.stated_value_date = ?, 
                          cf.address1 = ?, cf.address2 = ?, cf.address3 = ?, cf.address4 = ?, cf.address5 = ?, 
                          cf.address6 = ?, cf.city = ?, cf.state = ?, cf.zip = ?, p.account_type = ?, 
                          cf.production_number = ?, cf.master_production_number = ?, cf.omniscient_control_number = ?, p.accountclosed = ?,
                          cf.custodian_inception = CASE WHEN cf.custodian_inception = '' OR cf.custodian_inception IS NULL THEN ? ELSE cf.custodian_inception END, 
                          p.inceptiondate = CASE WHEN p.inceptiondate = '' OR p.inceptiondate IS NULL THEN ? ELSE p.inceptiondate END
                      WHERE p.portfolioinformationid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, $v);
            }
        }
    }

    static public function UpdateAllPortfolios(){
        $rep_codes = PortfolioInformation_Module_Model::GetRepCodeListFromUsersTable();
        $accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromRepCodeOpenAndClosed($rep_codes);
        self::UpdateAllPortfoliosForAccounts($accounts);
    }

    static public function CalculateBalances(array $account_number, $sdate, $edate){
        global $adb;
        $values = array();

        $questions = generateQuestionMarks($account_number);
        $params[] = $sdate;
        $params[] = $edate;
        $params[] = $account_number;

        $query = "SELECT pos.account_number, pos.symbol, SUM(quantity) AS quantity, SUM(amount) AS amount, pos.date, 
                  CASE WHEN pr.price IS NULL THEN 1 ELSE pr.price END AS price, 
                  CASE WHEN pr.factor IS NULL THEN 1 ELSE pr.factor END AS factor,
                  mcf.aclass, mcf.security_price_adjustment, mcf.security_sector
                  FROM custodian_omniscient.custodian_positions_td pos 
                  LEFT JOIN custodian_omniscient.custodian_prices_td pr ON pos.symbol = pr.symbol AND pos.date = pr.date
                  LEFT JOIN custodian_omniscient.custodian_balances_td bal ON bal.account_number = pos.account_number AND bal.as_of_date = pos.date
                  JOIN vtiger_modsecurities m ON pos.symbol = m.security_symbol
                  JOIN vtiger_modsecuritiescf mcf USING (modsecuritiesid)
                  WHERE pos.date BETWEEN ? AND ?
                  AND pos.account_number IN ({$questions})
                  GROUP BY account_number, pos.symbol, pos.date";

        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $cash_value = 0;

                if(strtoupper($x['symbol']) == 'CASH') {
                    $x['symbol'] = 'TDCASH';
                    $x['aclass'] = 'Cash';
                }

                if(strtoupper($x['aclass']) == 'CASH')
                    $cash_value = $x['amount'];

                if(is_null($x['price']))
                    $x['price'] = 0;
                if($x['security_price_adjustment'] == 0 && strtoupper($x['aclass']) == 'BONDS') {
                    $x['security_price_adjustment'] = 0.01;
                }
                else if($x['security_price_adjustment'] == 0)
                    $x['security_price_adjustment'] = 1;
                if($x['factor'] == 0)
                    $x['factor'] = 1;

                if($x['symbol'] == 'TDCASH'){
                    $x['market_value'] = $x['amount'];
                }else
                    $x['market_value'] = ($x['quantity'] + $x['amount']) * $x['price'] * $x['security_price_adjustment'] * $x['factor'];
                $values[$x['account_number']][$x['date']]['market_value'] += $x['market_value'];
                $values[$x['account_number']][$x['date']]['cash_value'] += $cash_value;
            }
        }

        return $values;
    }

    static public function CalculateAndWriteBalances(array $account_number, $sdate, $edate){
        global $adb;
        $counter = 0;
        $params = array();
        $writer = "";

        $balances = self::CalculateBalances($account_number, $sdate, $edate);

        foreach($balances AS $account => $values){
            foreach($values AS $date => $value){
                if($counter >= 100) {
                    $writer = rtrim($writer, ', ');
                    $query = "INSERT INTO custodian_omniscient.custodian_balances_td (account_number, account_value, money_market, as_of_date, calculated) 
                              VALUES {$writer}
                              ON DUPLICATE KEY UPDATE account_value = VALUES(account_value), money_market = VALUES(money_market)";
                    $adb->pquery($query, $params, true);
                    $counter = 0;
                    $writer = "";
                    $params = array();
                }
                $writer .= "(?, ?, ?, ?, NOW()), ";
                $params[] = array($account, $value['market_value'], $value['cash_value'], $date);
                $counter++;
            }
        }

        if(!empty($params)) {
            $writer = rtrim($writer, ', ');
            $query = "INSERT INTO custodian_omniscient.custodian_balances_td (account_number, account_value, money_market, as_of_date, calculated) 
                      VALUES {$writer}
                      ON DUPLICATE KEY UPDATE account_value = VALUES(account_value), money_market = VALUES(money_market)";
            $adb->pquery($query, $params, true);
        }
    }
}