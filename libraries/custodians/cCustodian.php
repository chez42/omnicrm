<?php

spl_autoload_register(function ($className) {
    if (file_exists("libraries/custodians/TD/$className.php")) {
        include_once "libraries/custodians/TD/$className.php";
    }elseif (file_exists("libraries/custodians/Schwab/$className.php")){
        include_once "libraries/custodians/Schwab/$className.php";
    }elseif (file_exists("libraries/custodians/Pershing/$className.php")){
        include_once "libraries/custodians/Pershing/$className.php";
    }elseif (file_exists("libraries/custodians/Fidelity/$className.php")){
        include_once "libraries/custodians/Fidelity/$className.php";
    }elseif (file_exists("libraries/custodians/Axos/$className.php")){
        include_once "libraries/custodians/Axos/$className.php";
    }elseif (file_exists("libraries/custodians/Omniscient/$className.php")){
        include_once "libraries/custodians/Omniscient/$className.php";
    }elseif (file_exists("libraries/custodians/Traits/$className.php")){
        include_once "libraries/custodians/Traits/$className.php";
    }elseif (file_exists("libraries/custodians/$className.php")){
        include_once "libraries/custodians/$className.php";
    }elseif (file_exists("libraries/custodians/Parsing/$className.php")){
        include_once "libraries/custodians/Parsing/$className.php";
    }elseif (file_exists("libraries/custodians/$className.php")){
        include_once "libraries/custodians/$className.php";
    }
});
include_once("libraries/statusupdates/StatusUpdate.php");

class cCustodian{
    protected $custodian_name, $module, $database, $rep_codes, $account_numbers, $table, $portfolio_table, $columns;
    protected $repcode_mapping;//The rep code associated to the proper user
/*
    public function __construct($custodian_name = "TD", $database = "custodian_omniscient", $module = "transactions",
                                $table = "custodian_transactions_td", $portfolio_table="custodian_portfolios_td"){
*/
    /**
     * The table parameter refers to the "main table" that will join with the portfolios table when necessary.  It is the table to retrieve data from
     * cCustodian constructor.
     * @param string $custodian_name
     * @param string $database
     * @param string $module
     * @param string $portfolio_table
     * @param string $table (REFERS TO THE MAIN TABLE)
     * @param array $columns (The columns we want returned)
     */
    public function __construct(string $custodian_name, string $database, string $module,
                                string $portfolio_table, string $table, array $columns){
        $this->name = $custodian_name;
        $this->database = $database;
        $this->module = $module;
        $this->portfolio_table = $portfolio_table;
        $this->table = $table;
        $this->columns = $columns;
    }

    /**
     * Set the rep codes list
     * @param array $rep_codes
     */
    public function SetRepCodes(array $rep_codes){
        $this->rep_codes = $rep_codes;
        $this->AssignRepCodeMapping();
        $this->FillAccountNumbersFromRepCodes();
    }

    public function GetAccountNumbers(){
        return $this->account_numbers;
    }

    public function SetAccountNumbers(array $account_numbers){
        $this->account_numbers = $account_numbers;
    }

    public function SetColumns(array $columns){
        $this->columns = $columns;
    }

    protected function FillAccountNumbersFromRepCodes(){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->rep_codes);
        $params[] = $this->rep_codes;

        $query = "SELECT account_number 
                  FROM {$this->database}.{$this->portfolio_table} 
                  WHERE rep_code IN ({$questions})
                  ORDER BY file_date DESC";
        $result = $adb->pquery($query, array($this->rep_codes), true);
        if($adb->num_rows($result) > 0)
            while($r = $adb->fetchByAssoc($result)){
                $this->account_numbers[] = $r;
            }
    }

    protected function AssignRepCodeMapping(){
        global $adb;
        foreach($this->rep_codes AS $k => $v){
            $query = "SELECT id FROM vtiger_users WHERE advisor_control_number LIKE ('%{$v}%') LIMIT 1";
            $result = $adb->pquery($query, array());
            if($adb->num_rows($result) > 0){
                $this->repcode_mapping[strtoupper($v)] = $adb->query_result($result, 0, 'id');
            }
        }
    }

    /**
     * Checks if rep codes have been set or not
     * @return bool
     */
    protected function HaveRepcodesBeenDefined(){
        if(empty($this->rep_codes))
            return false;
        return true;
    }

    /**
     * Get the latest balance date for the provided rep codes.  This is a MAX date for all passed in and only returns the highest date
     * between them.
     * @param string $portfolio_table
     * @param string $balance_table
     * @param string $as_of_field
     */
    public function GetLatestBalanceDate($as_of_field="as_of_date"){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->account_numbers);
        $params[] = $this->account_numbers;

        $query = "SELECT MAX({$as_of_field}) AS date 
                  FROM {$this->database}.{$this->table} 
                  WHERE account_number IN ({$questions})";
        $result = $adb->pquery($query, array($this->account_numbers), true);
        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');

        return 0;
    }

    public function GetAccountsMissingBalancesAsOfDate($as_of_field="as_of_date", $balance_field="account_value", $as_of_date){
        global $adb;
        $missing = array();

        $query = "DROP TABLE IF EXISTS AccountsToCheck";
        $adb->pquery($query, array());

        $query = "CREATE TABLE AccountsToCheck (account_number VARCHAR(50) NOT NULL, 
                                                balance DECIMAL(20,2))";
        $adb->pquery($query, array(), true);

        foreach($this->account_numbers AS $k => $v){
            $query = "INSERT INTO AccountsToCheck (account_number) VALUES (?)";
            $adb->pquery($query, array($v), true);
        }

        $query = "UPDATE AccountsToCheck 
                  JOIN {$this->database}.{$this->table} USING (account_number)
                  SET balance = {$balance_field}
                  WHERE {$as_of_field} = ?";
        $adb->pquery($query, array($as_of_date), true);

        $query = "SELECT account_number FROM AccountsToCheck WHERE balance IS NULL";
        $result = $adb->pquery($query, array(), true);
        if($adb->num_rows($result) > 0)
            while($r = $adb->fetchByAssoc($result)){
                $missing[] = $r['account_number'];
            }
        return $missing;
    }

    /**
     * Get the latest positions date for the provided rep codes.  This is a MAX date for all passed in and only returns the highest date
     * between them.
     * @param string $as_of_field
     */
    public function GetLatestPositionsDate($as_of_field="date"){
        global $adb;
        $accounts = array_slice($this->account_numbers, 0, 50);
        $questions = generateQuestionMarks($accounts);

        $query = "SELECT MAX({$as_of_field}) AS date 
                  FROM {$this->database}.{$this->table} 
                  WHERE account_number IN ({$questions})";

        $result = $adb->pquery($query, array($accounts), true);

        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');

        return 0;
    }

    /**
     * Get the latest transactions date for the provided rep codes.  This is a MAX date for all passed in and only returns the highest date
     * between them.
     * @param string $as_of_field
     */
    public function GetLatestTransactionsDate($trade_field="trade_date"){
        global $adb;
        $params = array();
        $questions = generateQuestionMarks($this->account_numbers);
        $params[] = $this->account_numbers;

        $query = "SELECT MAX({$trade_field}) AS date 
                  FROM {$this->database}.{$this->table} 
                  WHERE account_number IN ({$questions})";
        $result = $adb->pquery($query, $params, true);

        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');

        return 0;
    }

    /**
     * Update the vtiger crmentity sequence
     * @return string|string[]|null
     * @throws Exception
     */
    protected function UpdateEntitySequence(){
        global $adb;
        $update_query = "UPDATE vtiger_crmentity_seq SET id = id+1";
        $adb->pquery($update_query, array(), true);
        $query = "SELECT id FROM vtiger_crmentity_seq";
        $result = $adb->pquery($query, array());
        return $adb->query_result($result, 0, 'id');
    }

    /**
     * Determine if the account number exists in the CRM already
     * @param $account_number
     * @return bool
     */
    public function DoesAccountNumberExistInCRM($account_number){
        global $adb;
        $query = "SELECT account_number FROM vtiger_portfolioinformation WHERE account_number = ?";
        $result = $adb->pquery($query, array($account_number));
        if($adb->num_rows($result) > 0)
            return true;
        return false;
    }

    static public function UpdateLatestPositionsTable($custodian, $date_field, $rep_code = null){
        global $adb;
        $params = array();
        if($rep_code != null){
            $and = " AND por.rep_code = ? ";
            $params[] = $rep_code;
        }

        $query = "SELECT por.rep_code, MAX(pos.{$date_field}) AS as_of_date
                  FROM custodian_omniscient.custodian_portfolios_{$custodian} por
                  JOIN custodian_omniscient.custodian_positions_{$custodian} pos ON por.account_number = pos.account_number
                  WHERE por.rep_code IS NOT NULL AND por.rep_code != ''
                  AND pos.{$date_field} > (NOW() - INTERVAL 7 DAY)
                  {$and}
                  GROUP BY por.rep_code
                  ORDER BY pos.{$date_field} DESC";
        $result = $adb->pquery($query, $params);

        if($adb->num_rows($result) > 0){
            $query = "INSERT INTO custodian_omniscient.latestpositiondates VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE last_position_date = VALUES(last_position_date)";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, array($v['rep_code'], $v['as_of_date']));
#                echo "Updated " . $v['rep_code'] . " to " . $v['as_of_date'] . '<br />';
            }
        }
    }
}