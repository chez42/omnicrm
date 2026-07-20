<?php

DEFINE("TD", array("portfolio" => "custodian_portfolios_td", "balance" => "custodian_balances_td", "positions" => "custodian_positions_td", "transactions" => "custodian_transactions_td", "prices" => "custodian_prices_td", "securities" => "custodian_securities_td"));
DEFINE("TD_FIELDS", array("balance_as_of_date" => "as_of_date"));
DEFINE("TD_BALANCE_FIELDS", array("value" => "account_value", "money_market" => "money_market", "cash_equivalent" => "cash_equivalent", "available_funds" => "available_funds", "todays_net_change" => "todays_net_change", "buying_power" => "buying_power", "net_balance" => "net_balance", "option_buying_power" => "option_buying_power", "date" => "as_of_date", "calculated" => "calculated"));

DEFINE("FIDELITY", array("portfolio" => "custodian_portfolios_fidelity", "balance" => "custodian_balances_fidelity", "positions" => "custodian_positions_fidelity", "transactions" => "custodian_transactions_fidelity", "prices" => "custodian_prices_fidelity", "securities" => "custodian_securities_fidelity"));
DEFINE("FIDELITY_FIELDS", array("balance_as_of_date" => "as_of_date"));
DEFINE("FIDELITY_BALANCE_FIELDS", array("account_number" => "account_number", "date" => "as_of_date", "value" => "net_worth", "buying_power" => "buying_power", "cash_available_to_borrow" => "cash_available_to_borrow", "cash_available_to_withdraw" => "cash_available_to_withdraw", "money_market" => "money_market_available", "outstanding_calls" => "outstanding_calls", "unsettled_cash" => "unsettled_cash", "margin_balance" => "margin_balance", "short_balance" => "short_balance", "core_cash_market_value" => "core_cash_market_value", "cash_settlement_date" => "cash_settlement_date", "margin_market_value" => "margin_market_value", "margin_settlement_date" => "margin_settlement_date", "short_market_value" => "short_market_value", "short_settlement_date" => "short_settlement_date", "trade_date_legal_balance" => "trade_date_legal_balance", "face_amount" => "face_amount", "death_benefit_amount" => "death_benefit_amount", "policy_account_value" => "policy_account_value", "cash_surrender_value" => "cash_surrender_value", "loan_balance" => "loan_balance", "regulatory_net_worth" => "regulatory_net_worth", "dividend_accrual" => "dividend_accrual", "account_source" => "account_source", "cash_settlement" => "cash_settlement", "core_money_market" => "core_money_market", "custom_short_name" => "custom_short_name", "fbsi_short_name" => "fbsi_short_name", "giving_account" => "giving_account", "loaned_securities_market_value" => "loaned_securities_market_value", "margin_settlement" => "margin_settlement", "match_account" => "match_account", "money_market" => "money_market", "money_source" => "money_source", "money_source_number" => "money_source_number", "net_cash" => "net_cash", "net_money_market" => "net_money_market", "non_core_money_markets" => "non_core_money_markets", "plan_name" => "plan_name", "plan_number" => "plan_number", "source_balance" => "source_balance", "ytd_contributions" => "ytd_contributions", "ytd_grants" => "ytd_grants", "ytd_miscellaneous" => "ytd_miscellaneous", "file_date" => "file_date", "filename" => "filename", "calculated" => "insert_date"));

DEFINE("SCHWAB", array("portfolio" => "custodian_portfolios_schwab", "balance" => "custodian_balances_schwab", "positions" => "custodian_positions_schwab", "transactions" => "custodian_transactions_schwab", "prices" => "custodian_prices_schwab", "securities" => "custodian_securities_schwab"));
DEFINE("SCHWAB_FIELDS", array("balance_as_of_date" => "as_of_date"));
DEFINE("SCHWAB_BALANCE_FIELDS", array("date" => "as_of_date", "value" => "account_value", "money_market" => "cash_balance", "net_cash" => "net_cash", "calculated" => "as_of_date"));

DEFINE("AXOS", array("portfolio" => "custodian_portfolios_axos", "balance" => "custodian_balances_axos", "positions" => "custodian_positions_axos", "transactions" => "custodian_transactions_axos", "prices" => "custodian_prices_axos", "securities" => "custodian_securities_axos"));
DEFINE("AXOS_FIELDS", array("balance_as_of_date" => "as_of_date"));
DEFINE("AXOS_BALANCE_FIELDS", array("date" => "as_of_date", "value" => "account_value", "money_market" => "net_cash", "cash_equivalent" => "net_cash", "available_funds" => "net_cash", "todays_net_change" => "net_cash", "buying_power" => "net_cash", "net_balance" => "net_cash", "option_buying_power" => "net_cash", "calculated" => "insert_date"));

DEFINE("PERSHING", array("portfolio" => "custodian_portfolios_pershing", "balance" => "custodian_balances_pershing", "positions" => "custodian_positions_pershing", "transactions" => "custodian_transactions_pershing", "prices" => "custodian_prices_pershing", "securities" => "custodian_securities_pershing"));


DEFINE("INCL", 1);
DEFINE("EXCL", 0);

class CustodianBalance{public $value, $money_market, $account_type, $account_description, $cash_equivalent, $available_funds, $todays_net_change, $buying_power, $net_balance, $option_buying_power, $date, $calculated;}

class CustodianAccess{
    private $account_number;
    private $custodian, $fields, $balance_fields;//used for accessing the custodian database
    public $balance, $positions;

    private $map;//This is the custodian mapping to give acess to cTDPortfolios for example

    public function __construct($account_number){
        $this->account_number = $account_number;
        $this->balance = new CustodianBalance();
        $this->map = new CustodianClassMapping($account_number);
        switch(strtoupper(PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($account_number))){
            case "TD":
                $this->custodian = TD;
                $this->fields = TD_FIELDS;
                $this->balance_fields = TD_BALANCE_FIELDS;
                $this->positions = cTDPositions::GetPositionDataAsOfDate(array($account_number), date("Y-m-d"));
                break;
            case "FIDELITY":
                $this->custodian = FIDELITY;
                $this->fields = FIDELITY_FIELDS;
                $this->balance_fields = FIDELITY_BALANCE_FIELDS;
                $this->positions = cFidelityPositions::GetPositionDataAsOfDate(array($account_number), date("Y-m-d"));
                break;
            case "SCHWAB":
                $this->custodian = SCHWAB;
                $this->fields = SCHWAB_FIELDS;
                $this->balance_fields = SCHWAB_BALANCE_FIELDS;
                $this->positions = cSchwabPositions::GetPositionDataAsOfDate(array($account_number), date("Y-m-d"));
                break;
            case "AXOS":
                $this->custodian = AXOS;
                $this->fields = AXOS_FIELDS;
                $this->balance_fields = AXOS_BALANCE_FIELDS;
                $this->positions = cAxosPositions::GetPositionDataAsOfDate(array($account_number), date("Y-m-d"));
                break;
            case "PERSHING":
                $this->custodian = PERSHING;
                break;
        }
    }
    /**
     * Get the balance for the account number as of the passed in date.  If no date given, it returns the latest balance
     * @param null $date
     */
    public function GetBalance($date = null){
        global $adb;
        if($date == null)
            $date = $this->GetLatestBalanceDate();

        $query = "SELECT * FROM custodian_omniscient.{$this->custodian['balance']} WHERE account_number = ? AND {$this->fields['balance_as_of_date']} = ?";
        $result = $adb->pquery($query, array($this->account_number, $date));
        if($adb->num_rows($result) > 0){
            $this->balance->value = $adb->query_result($result, 0, $this->balance_fields['value']);
            $this->balance->money_market = $adb->query_result($result, 0, $this->balance_fields['money_market']);
            $this->balance->cash_equivalent = $adb->query_result($result, 0, $this->balance_fields['cash_equivalent']);
            $this->balance->available_funds = $adb->query_result($result, 0, $this->balance_fields['available_funds']);
            $this->balance->todays_net_change = $adb->query_result($result, 0, $this->balance_fields['todays_net_change']);
            $this->balance->buying_power = $adb->query_result($result, 0, $this->balance_fields['buying_power']);
            $this->balance->net_balance = $adb->query_result($result, 0, $this->balance_fields['net_balance']);
            $this->balance->option_buying_power = $adb->query_result($result, 0, $this->balance_fields['option_buying_power']);
            $this->balance->date = $adb->query_result($result, 0, $this->balance_fields['date']);
            $this->balance->calculated = $adb->query_result($result, 0, $this->balance_fields['calculated']);
        }

        return $this->balance;
    }

    /**
     * Get the latest balance date for the account number
     * between them.
     * @param string $portfolio_table
     * @param string $balance_table
     * @param string $as_of_field
     */
    public function GetLatestBalanceDate($as_of_field="as_of_date"){
        global $adb;
        $query = "SELECT MAX({$as_of_field}) AS date 
                  FROM custodian_omniscient.{$this->custodian['balance']} 
                  WHERE account_number = ?";
        $result = $adb->pquery($query, array($this->account_number), true);
        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');

        return 0;
    }

    /**
     * If no date is passed in, it will return the results of 'GetLatestBalanceDate'
     * @param null $date
     * @return int|string|string[]|null
     * @throws Exception
     */
    public function GetNearestValidBalanceDate($date=null){
        global $adb;
        if(is_null($date))
            return $this->GetLatestBalanceDate();

        $query = "SELECT {$this->fields['balance_as_of_date']} AS date 
                  FROM custodian_omniscient.{$this->custodian['balance']} 
                  WHERE account_number = ?
                  AND {$this->fields['balance_as_of_date']} <= ?
                  ORDER BY {$this->fields['balance_as_of_date']} DESC";

        $result = $adb->pquery($query, array($this->account_number, $date), true);
        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');

        return 0;
    }

    public function GetBalanceExcludingPositions(array $positions, $date){
        $bal = $this->GetBalance($date);
        $pos = $this->GetPositions($date, $positions);//We are excluding these positions, so we want to get ONLY the positions we want to exclude so we can subtract
        $total = 0;

        foreach($pos AS $k => $v){
            $total += $v['market_value'];
        }

        $bal->value -= $total;
        return $bal;
    }


    /**
     * Returns a list of positions including or excluding those passed in.  This uses in_array so is CASE SENSITIVE!!
     * @param $date
     * @param array|null $positions
     * @param int $position_rule
     * @return array
     */
    public function GetPositions($date, array $positions = null, $position_rule = INCL){
        $tmp_positions = $this->map->positions::GetPositionDataAsOfDate(array($this->account_number), $date)[$this->account_number];//The function returns an array of positions belonging to the account number

        $return_positions = array();

        if(!empty($positions)){
            switch($position_rule){
                case EXCL:
                    foreach($tmp_positions AS $k => $v){
                        if(!in_array($v['symbol'], $positions))
                            $return_positions[] = $v;
                    }
                    break;
                case INCL:
                    foreach($tmp_positions AS $k => $v){
                        if(in_array($v['symbol'], $positions))
                            $return_positions[] = $v;
                    }
                default:
            }
        }else{
            $return_positions = $tmp_positions;
        }

        return $return_positions;
    }

}