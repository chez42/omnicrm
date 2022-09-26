<?php
require_once("libraries/custodians/cCustodian.php");


class cFidelityPortfolioData{
    public  $account_number, $as_of_date, $net_worth, $buying_power, $cash_available_to_borrow, $cash_available_to_withdraw,
            $money_market_available, $outstanding_calls, $unsettled_cash, $margin_balance, $short_balance, $core_cash_market_value,
            $cash_settlement_date, $margin_market_value, $margin_settlement_date, $short_market_value, $short_settlement_date,
            $trade_date_legal_balance, $face_amount, $death_benefit_amount, $policy_account_value, $cash_surrender_value, $loan_balance,
            $regulatory_net_worth, $dividend_accrual, $account_source, $cash_settlement, $core_money_market, $custom_short_name,
            $fbsi_short_name, $giving_account, $loaned_securities_market_value, $margin_settlement, $match_account, $money_market,
            $money_source, $money_source_number, $net_cash, $net_money_market, $non_core_money_markets, $plan_name, $plan_number,
            $source_balance, $ytd_contributions, $ytd_grants, $ytd_miscellaneous, $file_date, $filename, $insert_date;

    public function __construct($data){
        $this->custodian = 'FIDELITY';
        $this->account_number = $data['personal']['account_number'];
        $this->as_of_date = $data['balance']['as_of_date'];
        $this->net_worth = $data['balance']['net_worth'];
        $this->buying_power = $data['balance']['buying_power'];
        $this->cash_available_to_borrow = $data['balance']['cash_available_to_borrow'];
        $this->cash_available_to_withdraw = $data['balance']['cash_available_to_withdraw'];
        $this->money_market_available = $data['balance']['money_market_available'];
        $this->outstanding_calls = $data['balance']['outstanding_calls'];
        $this->unsettled_cash = $data['balance']['unsettled_cash'];
        $this->margin_balance = $data['balance']['margin_balance'];
        $this->short_balance = $data['balance']['short_balance'];
        $this->core_cash_market_value = $data['balance']['core_cash_market_value'];
        $this->cash_settlement_date = $data['balance']['cash_settlement_date'];
        $this->margin_market_value = $data['balance']['margin_market_value'];
        $this->margin_settlement_date = $data['balance']['margin_settlement_date'];
        $this->short_market_value = $data['balance']['short_market_value'];
        $this->short_settlement_date = $data['balance']['short_settlement_date'];
        $this->trade_date_legal_balance = $data['balance']['trade_date_legal_balance'];
        $this->face_amount = $data['balance']['face_amount'];
        $this->death_benefit_amount = $data['balance']['death_benefit_amount'];
        $this->policy_account_value = $data['balance']['policy_account_value'];
        $this->cash_surrender_value = $data['balance']['cash_surrender_value'];
        $this->loan_balance = $data['balance']['loan_balance'];
        $this->regulatory_net_worth = $data['balance']['regulatory_net_worth'];
        $this->dividend_accrual = $data['balance']['dividend_accrual'];
        $this->account_source = $data['balance']['account_source'];
        $this->cash_settlement = $data['balance']['cash_settlement'];
        $this->core_money_market = $data['balance']['core_money_market'];
        $this->custom_short_name = $data['balance']['custom_short_name'];
        $this->fbsi_short_name = $data['balance']['fbsi_short_name'];
        $this->giving_account = $data['balance']['giving_account'];
        $this->loaned_securities_market_value = $data['balance']['loaned_securities_market_value'];
        $this->margin_settlement = $data['balance']['margin_settlement'];
        $this->match_account = $data['balance']['match_account'];
        $this->money_market = $data['balance']['money_market'];
        $this->money_source = $data['balance']['money_source'];
        $this->money_source_number = $data['balance']['money_source_number'];
        $this->net_cash = $data['balance']['net_cash'];
        $this->net_money_market = $data['balance']['net_money_market'];
        $this->non_core_money_markets = $data['balance']['non_core_money_markets'];
        $this->plan_name = $data['balance']['plan_name'];
        $this->plan_number = $data['balance']['plan_number'];
        $this->source_balance = $data['balance']['source_balance'];
        $this->ytd_contributions = $data['balance']['ytd_contributions'];
        $this->ytd_grants = $data['balance']['ytd_grants'];
        $this->ytd_miscellaneous = $data['balance']['ytd_miscellaneous'];
        $this->file_date = $data['balance']['file_date'];
        $this->filename = $data['balance']['filename'];
        $this->insert_date = $data['balance']['insert_date'];

        $this->account_name = $data['personal']['account_name'];
        $this->t_account = $data['personal']['t_account'];
        $this->registration = $data['personal']['registration'];
        $this->disposal_method = $data['personal']['disposal_method'];
        $this->s_corp_indicator = $data['personal']['s_corp_indicator'];
        $this->production_number = $data['personal']['production_number'];
        $this->establishment_date = $data['personal']['establishment_date'];
        $this->name = $data['personal']['name'];
        $this->source = $data['personal']['source'];
        $this->address1_line1 = $data['personal']['address1_line1'];
        $this->address1_line2 = $data['personal']['address1_line2'];
        $this->address1_line3 = $data['personal']['address1_line3'];
        $this->address1_type_indicator = $data['personal']['address1_type_indicator'];
        $this->address2_line1 = $data['personal']['address2_line1'];
        $this->address2_line2 = $data['personal']['address2_line2'];
        $this->address2_line3 = $data['personal']['address2_line3'];
        $this->address2_type_indicator = $data['personal']['address2_type_indicator'];
        $this->agency = $data['personal']['agency'];
        $this->all_cost_basis_known = $data['personal']['all_cost_basis_known'];
#        $this->as_of_date = $data['personal']['as_of_date'];
        $this->ats = $data['personal']['ats'];
        $this->ats_pi_indicator = $data['personal']['ats_pi_indicator'];
        $this->ats_tla_indicator = $data['personal']['ats_tla_indicator'];
        $this->attention = $data['personal']['attention'];
        $this->benchmark_ror = $data['personal']['benchmark_ror'];
        $this->capital_gains_option = $data['personal']['capital_gains_option'];
        $this->childrens_term_rider = $data['personal']['childrens_term_rider'];
        $this->city1 = $data['personal']['city1'];
        $this->city2 = $data['personal']['city2'];
        $this->commission_schedule = $data['personal']['commission_schedule'];
        $this->contract_date = $data['personal']['contract_date'];
        $this->core_fund = $data['personal']['core_fund'];
        $this->core_fund_cusip = $data['personal']['core_fund_cusip'];
        $this->core_fund_description = $data['personal']['core_fund_description'];
        $this->core_fund_id = $data['personal']['core_fund_id'];
        $this->core_fund_name = $data['personal']['core_fund_name'];
        $this->cost_basis_retirement_indicator = $data['personal']['cost_basis_retirement_indicator'];
        $this->country1 = $data['personal']['country1'];
        $this->country2 = $data['personal']['country2'];
        $this->custom_short_name = $data['personal']['custom_short_name'];
        $this->date_of_birth3 = $data['personal']['date_of_birth3'];
        $this->date_of_birth4 = $data['personal']['date_of_birth4'];
        $this->date_of_first_income_payment = $data['personal']['date_of_first_income_payment'];
        $this->date_of_next_income_payment = $data['personal']['date_of_next_income_payment'];
        $this->day_phone = $data['personal']['day_phone'];
        $this->day_phone_country_code = $data['personal']['day_phone_country_code'];
        $this->day_phone_extension = $data['personal']['day_phone_extension'];
        $this->death_benefit_amount = $data['personal']['death_benefit_amount'];
        $this->death_benefit_option = $data['personal']['death_benefit_option'];
        $this->disability_waiver_monthly_deductions_rider = $data['personal']['disability_waiver_monthly_deductions_rider'];
        $this->disability_waiver_payment_rider_insured1 = $data['personal']['disability_waiver_payment_rider_insured1'];
        $this->disability_waiver_payment_rider_insured2 = $data['personal']['disability_waiver_payment_rider_insured2'];
        $this->dividend_instructions = $data['personal']['dividend_instructions'];
        $this->eft = $data['personal']['eft'];
        $this->evening_phone = $data['personal']['evening_phone'];
        $this->evening_phone_country_code = $data['personal']['evening_phone_country_code'];
        $this->evening_phone_extension = $data['personal']['evening_phone_extension'];
        $this->faab_amount = $data['personal']['faab_amount'];
        $this->fbsi_short_name = $data['personal']['fbsi_short_name'];
        $this->guarantee_period = $data['personal']['guarantee_period'];
        $this->income_payment_frequency = $data['personal']['income_payment_frequency'];
        $this->income_payment_options = $data['personal']['income_payment_options'];
        $this->income_payment_value = $data['personal']['income_payment_value'];
        $this->international_postal_code1 = $data['personal']['international_postal_code1'];
        $this->international_postal_code2 = $data['personal']['international_postal_code2'];
        $this->investment_objectives = $data['personal']['investment_objectives'];
        $this->liquidity_value = $data['personal']['liquidity_value'];
        $this->maintenance_date = $data['personal']['maintenance_date'];
        $this->margin_agreement = $data['personal']['margin_agreement'];
        $this->master_security_lending_agreement = $data['personal']['master_security_lending_agreement'];
        $this->mmkt_account_designation = $data['personal']['mmkt_account_designation'];
        $this->model_name = $data['personal']['model_name'];
        $this->modified_endowment_contract_status = $data['personal']['modified_endowment_contract_status'];
        $this->money_market_available = $data['personal']['money_market_available'];
        $this->name3 = $data['personal']['name3'];
        $this->name4 = $data['personal']['name4'];
        $this->net_cash = $data['personal']['net_cash'];
        $this->option_agreement = $data['personal']['option_agreement'];
        $this->option_level = $data['personal']['option_level'];
        $this->plan_id = $data['personal']['plan_id'];
        $this->plan_name = $data['personal']['plan_name'];
        $this->plan_number = $data['personal']['plan_number'];
        $this->plan_type = $data['personal']['plan_type'];
        $this->primary_account_owner = $data['personal']['primary_account_owner'];
        $this->primary_email = $data['personal']['primary_email'];
        $this->primary_owner_first_name = $data['personal']['primary_owner_first_name'];
        $this->primary_owner_last_name = $data['personal']['primary_owner_last_name'];
        $this->primary_owner_middle_name = $data['personal']['primary_owner_middle_name'];
        $this->primary_owner_birth_date = $data['personal']['primary_owner_birth_date'];
        $this->prime_broker_indicator = $data['personal']['prime_broker_indicator'];
        $this->product_name = $data['personal']['product_name'];
        $this->product_type = $data['personal']['product_type'];
        $this->province1 = $data['personal']['province1'];
        $this->province2 = $data['personal']['province2'];
        $this->reg_rep1 = $data['personal']['reg_rep1'];
        $this->reg_rep2 = $data['personal']['reg_rep2'];
        $this->reg_style = $data['personal']['reg_style'];
        $this->scorp_indicator = $data['personal']['scorp_indicator'];
        $this->schedule_premium_amount = $data['personal']['schedule_premium_amount'];
        $this->schedule_premium_mode = $data['personal']['schedule_premium_mode'];
        $this->secondary_account_owner = $data['personal']['secondary_account_owner'];
        $this->secondary_email = $data['personal']['secondary_email'];
        $this->secondary_owner_birth_date = $data['personal']['secondary_owner_birth_date'];
        $this->source_of_monthly_deduction = $data['personal']['source_of_monthly_deduction'];
        $this->special_programs_arb = $data['personal']['special_programs_arb'];
        $this->special_programs_dca = $data['personal']['special_programs_dca'];
        $this->special_programs_eft = $data['personal']['special_programs_eft'];
        $this->special_programs_swp = $data['personal']['special_programs_swp'];
        $this->state1 = $data['personal']['state1'];
        $this->state2 = $data['personal']['state2'];
        $this->state_premium_tax_rate = $data['personal']['state_premium_tax_rate'];
        $this->successor1_allocation = $data['personal']['successor1_allocation'];
        $this->successor1_name = $data['personal']['successor1_name'];
        $this->successor2_allocation = $data['personal']['successor2_allocation'];
        $this->successor2_name = $data['personal']['successor2_name'];
        $this->successor3_allocation = $data['personal']['successor3_allocation'];
        $this->successor3_name = $data['personal']['successor3_name'];
        $this->successor4_allocation = $data['personal']['successor4_allocation'];
        $this->successor4_name = $data['personal']['successor4_name'];
        $this->successor5_allocation = $data['personal']['successor5_allocation'];
        $this->successor5_name = $data['personal']['successor5_name'];
        $this->successor6_allocation = $data['personal']['successor6_allocation'];
        $this->successor6_name = $data['personal']['successor6_name'];
        $this->successor7_allocation = $data['personal']['successor7_allocation'];
        $this->successor7_name = $data['personal']['successor7_name'];
        $this->successor8_allocation = $data['personal']['successor8_allocation'];
        $this->successor8_name = $data['personal']['successor8_name'];
        $this->taccount = $data['personal']['taccount'];
        $this->tpa_plan_id = $data['personal']['tpa_plan_id'];
        $this->trust_name = $data['personal']['trust_name'];
        $this->underlying_asset_value = $data['personal']['underlying_asset_value'];
        $this->unsettled_cash = $data['personal']['unsettled_cash'];
        $this->wire = $data['personal']['wire'];
        $this->withdrawal_option = $data['personal']['withdrawal_option'];
        $this->ytd_long_term_disallowed = $data['personal']['ytd_long_term_disallowed'];
        $this->ytd_long_term_realized_gl = $data['personal']['ytd_long_term_realized_gl'];
        $this->ytd_net_long_term_gl = $data['personal']['ytd_net_long_term_gl'];
        $this->ytd_net_short_term_gl = $data['personal']['ytd_net_short_term_gl'];
        $this->ytd_net_total_realized_gl = $data['personal']['ytd_net_total_realized_gl'];
        $this->ytd_short_term_disallowed = $data['personal']['ytd_short_term_disallowed'];
        $this->ytd_short_term_realized_gl = $data['personal']['ytd_short_term_realized_gl'];
        $this->zip_code1 = $data['personal']['zip_code1'];
        $this->zip_code2 = $data['personal']['zip_code2'];
        $this->file_date = $data['personal']['file_date'];
        $this->filename = $data['personal']['filename'];
        $this->insert_date = $data['personal']['insert_date'];
        $this->rep_code = $data['personal']['rep_code'];
        $this->master_rep_code = $data['personal']['master_rep_code'];
        $this->omni_code = $data['personal']['omni_code'];
        $this->rep_code_multiple = $data['personal']['rep_code_multiple'];

    }
}

/**
 * Class cFidelityPortfolios
 * This class allows the pulling of data from the custodian database
 */
class cFidelityPortfolios extends cCustodian {
    use tPortfolios;
    private $portfolio_data;//Holds both personal and balance information

    /**
     * cFidelityPortfolios constructor.
     * @param string $custodian_name
     * @param string $database
     * @param string $module
     * @param string $portfolio_table
     * @param string $table (REFERS TO BALANCE TABLE)
     */
    public function __construct(string $custodian_name, string $database, string $module,
                                string $portfolio_table, string $balance_table, array $rep_codes, $pull_all=true){
        $this->name = $custodian_name;
        $this->database = $database;
        $this->module = $module;
        $this->portfolio_table = $portfolio_table;
        $this->table = $balance_table;
        if(!empty($rep_codes) && $pull_all == true) {
            $this->SetRepCodes($rep_codes);
            $this->GetPortfolioPersonalData();
            $this->GetPortfolioBalanceData();
            $this->SetupPortfolioComparisons();
        }
    }

    public function SetAccountNumbers(array $account_numbers){
        parent::SetAccountNumbers($account_numbers);
        $this->GetPortfolioPersonalData();
        $this->GetPortfolioBalanceData();
        $this->SetupPortfolioComparisons();
    }


    public function GetPortfolioPersonalData(){
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

    public function GetPortfolioBalanceData($date=null){
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

    public function GetPortfolioData(){
        return $this->portfolio_data;
    }

    /**
     * Create the new entity in the crmentity table
     * @param $crmid
     * @param $owner
     * @param cFidelityPortfolios $data
     */
    protected function FillEntityTable($crmid, $owner, cFidelityPortfolioData $data){
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
     * @param cFidelityPortfolios $data
     */
    protected function FillPortfolioTable($crmid, cFidelityPortfolioData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = $data->account_number;
        $params[] = 'FIDELITY';
        $params[] = $data->net_worth;
        $params[] = $data->market_value;
        $params[] = $data->cash_available_to_withdraw;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination, total_value, market_value, cash_value)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    /**
     * Creates data in the vtiger_portfolioinformationcf table
     * @param $crmid
     * @param cFidelityPortfolios $data
     */
    protected function FillPortfolioCFTable($crmid, cFidelityPortfolioData $data){
        global $adb;
        $params = array();
        $params[] = $crmid;
        $params[] = $data->unsettled_cash;
        $params[] = $data->dividend_accrual;
        $params[] = $data->rep_code;

        $questions = generateQuestionMarks($params);
        $query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, unsettled_cash, dividend_accrual, production_number)
                  VALUES ({$questions})";
        $adb->pquery($query, $params, true);
    }

    protected function UpdatePortfolios(cFidelityPortfolioData $data){
#        print_r($data);exit;
        global $adb;
        $params[] = $data->registration;
        $params[] = $data->primary_account_owner;
        $params[] = $data->primary_owner_first_name;
        $params[] = ($data->primary_owner_last_name == '') ? $data->primary_account_owner : $data->primary_owner_last_name;
        $params[] = $data->address1_line1;
        $params[] = $data->address1_line2;
        $params[] = $data->address1_line3;
        $params[] = $data->address2_line1;
        $params[] = $data->address2_line2;
        $params[] = $data->address2_line3;
        $params[] = $data->city1;
        $params[] = $data->state1;
        $params[] = $data->zip_code1;
        $params[] = $data->establishment_date;
        $params[] = $data->rep_code;
        $params[] = $data->master_rep_code;
        $params[] = $data->rep_code_multiple;
        $params[] = $data->registration;
        $params[] = $data->primary_email;
        $params[] = $data->omni_code;
        $params[] = $data->filename;
        $params[] = $data->net_worth;
        $params[] = $data->as_of_date;
        $params[] = $data->net_worth - $data->cash_available_to_withdraw;
        $params[] = $data->cash_available_to_withdraw;
        $params[] = $data->cash_available_to_withdraw;
        $params[] = $data->unsettled_cash;
        $params[] = $data->short_market_value;
        $params[] = $data->short_balance;
        $params[] = $data->dividend_accrual;
        $params[] = $data->rep_code;
        $params[] = $data->cash_available_to_borrow;
        $params[] = $data->cash_available_to_withdraw;
        $params[] = $data->money_market_available;
        $params[] = $data->outstanding_calls;
        $params[] = $data->margin_balance;
        $params[] = $data->core_cash_market_value;
        $params[] = $data->margin_market_value;
        $params[] = $data->trade_date_legal_balance;
        $params[] = $data->face_amount;
        $params[] = $data->death_benefit_amount;
        $params[] = $data->policy_account_value;
        $params[] = $data->cash_surrender_value;
        $params[] = $data->loan_balance;
        $params[] = $data->regulatory_net_worth;
        $params[] = $data->registration;
        $params[] = $data->net_worth;
        $params[] = $data->filename;
        $params[] = $data->account_number;

        $query = "UPDATE vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                  LEFT JOIN  {$this->database}.portfolios_mapping_fidelity pmap ON pmap.fidelity_type = ?
                  SET cf.description = ?, p.first_name = ?, 
                    p.last_name = ?, cf.address1 = ?, cf.address2 = ?, 
                    cf.address3 = ?, cf.address4 = ?, cf.address5 = ?, 
                    cf.address6 = ?, cf.city = ?, cf.state = ?, cf.zip = ?, cf.custodian_inception = ?, 
                    cf.production_number = ?, cf.master_production_number = ?, cf.rep_code_multiple = ?, 
                    p.account_type = ?, 
                    cf.email_address = ?, cf.omniscient_control_number = ?, cf.custodian_source = ?, 
                    p.total_value=?, cf.stated_value_date = ?, cf.securities = ?, 
                    cf.cash = ?, p.cash_value = ?, cf.unsettled_cash = ?, 
                    cf.short_market_value = ?, cf.short_balance = ?, cf.dividend_accrual = ?, 
                    cf.production_number = ?, p.cash_available_to_borrow=?, 
                    p.cash_available_to_withdraw=?, p.money_market_funds=?, 
                    p.outstanding_calls=?, p.margin_balance=?, p.core_cash_market_value=?, 
                    p.margin_market_value=?, p.trade_date_legal_balance=?, p.face_amount=?, 
                    p.death_benefit_amount=?, p.policy_account_value=?, 
                    p.cash_surrender_value=?, p.loan_balance=?, p.regulatory_net_worth=?, 
                    cf.cf_2549 = CASE WHEN pmap.omniscient_type != '' THEN pmap.omniscient_type ELSE cf.cf_2549 END, cf.account_registration = ?, 
                    cf.stated_net_worth = ?, cf.custodian_source = ? 
                    WHERE account_number = ?";
        $adb->pquery($query, $params, true);
    }

    /**
     * Using the cFidelityPortfolios class, create the portfolios.  Used with a pre-filled in cFidelityPortfolios class (done manually)
     * @param cFidelityPortfolios $data
     * @throws Exception
     */
    public function CreateNewPortfolioUsingcFidelityPortfolios(cFidelityPortfolioData $data){
        if(!$this->DoesAccountNumberExistInCRM($data->account_number)) {//If the account number doesn't exist yet, create it
#            echo $data->account_number . ' does not exist!';exit;
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
                $data = $this->portfolio_data[$v];
                if (!empty($data)) {
                    $tmp = new cFidelityPortfolioData($data);
                    $this->CreateNewPortfolioUsingcFidelityPortfolios($tmp);
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
                $data = $this->portfolio_data[$v];
                if (!empty($data)) {
                    $tmp = new cFidelityPortfolioData($data);
                    $this->UpdatePortfolios($tmp);
                }
            }
        }
    }

    static public function CreateNewPortfoliosForRepCodes($rep_codes){
        global $adb;
        $custodian_accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromCustodianUsingRepCodes("Fidelity", $rep_codes);
        $crm_accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromRepCodeOpenAndClosed($rep_codes);

        $new = array_diff($custodian_accounts, $crm_accounts);

        if(!empty($new)){
            $questions = generateQuestionMarks($new);

            $query = "SELECT p.account_number, p.account_name, p.t_account, p.registration, p.disposal_method, p.s_corp_indicator, 
                             'Fidelity' AS custodian, p.production_number, f.as_of_date, f.net_worth, f.buying_power, 
                             f.cash_available_to_withdraw, (f.net_worth-f.cash_available_to_withdraw) AS market_value, f.cash_available_to_borrow, 
                             f.money_market_available, f.core_cash_market_value, f.unsettled_cash, f.dividend_accrual, NOW() AS generatedtime, 
                             p.rep_code, u.id AS userid
                      FROM custodian_omniscient.custodian_portfolios_fidelity p
                      JOIN custodian_omniscient.latestpositiondates lpd ON lpd.rep_code = p.rep_code 
                      LEFT JOIN custodian_omniscient.custodian_balances_fidelity f ON f.account_number = p.account_number 
                                                                                   AND f.as_of_date = lpd.last_position_date
                      JOIN vtiger_users u ON u.advisor_control_number LIKE CONCAT('%',p.rep_code,'%') 
                      WHERE p.account_number IN ({$questions})";
            $result = $adb->pquery($query, array($new));

            if($adb->num_rows($result) > 0){
                while($v = $adb->fetchByAssoc($result)){
                    $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");
                    $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], 1, $v['userid'], 1, 'PortfolioInformation', $v['generatedtime'], $v['generatedtime'], $v['account_number']));

                    $query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination, total_value, market_value, cash_value)
                              VALUES(?, ?, ?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], $v['account_number'], $v['custodian'], $v['net_worth'], $v['market_value'], $v['cash_available_to_withdraw']));

                    $query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, unsettled_cash, dividend_accrual, production_number)
                              VALUES (?, ?, ?, ?)";
                    $adb->pquery($query, array($v['crmid'], $v['unsettled_cash'], $v['dividend_accrual'], $v['production_number']));
                }
            }
        }
    }

    static public function UpdateAllPortfoliosForAccountsPersonalOnly(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);

        $query = "SELECT pf.primary_account_owner, pf.primary_owner_first_name,
                         CASE WHEN pf.primary_owner_last_name = '' THEN pf.primary_account_owner ELSE pf.primary_owner_last_name END AS last_name,
                         pf.address1_line1, pf.address1_line2, pf.address1_line3, pf.address2_line1, pf.address2_line2, pf.address2_line3, 
                         pf.city1, pf.state1, pf.zip_code1, pf.establishment_date, pf.rep_code, pf.master_rep_code, pf.rep_code_multiple,
                         u.id AS userid, p.portfolioinformationid
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid  
                  LEFT JOIN custodian_omniscient.custodian_portfolios_fidelity pf ON pf.account_number = p.account_number 
                  LEFT JOIN custodian_omniscient.portfolios_mapping_fidelity pmap ON pmap.fidelity_type = pf.registration
                  JOIN vtiger_users u ON u.advisor_control_number LIKE CONCAT('%',pf.rep_code,'%')
                  WHERE pf.account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_number), true);#21,826,709.36
        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_portfolioinformation p 
                      JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                      JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid 
                      SET cf.description = ?, p.first_name = ?,
                      p.last_name = ?, cf.address1 = ?, cf.address2 = ?, cf.address3 = ?, cf.address4 = ?, cf.address5 = ?, 
                      cf.address6 = ?, cf.city = ?, cf.state = ?, cf.zip = ?, cf.custodian_inception = ?, cf.production_number = ?,
                      cf.master_production_number = ?, cf.rep_code_multiple = ?, e.smownerid = ?
                    WHERE p.portfolioinformationid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, $v, true);
            }
        }
    }

    static public function UpdateAllPortfoliosForAccountsBalancesOnly(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);

        $query = "SELECT f.net_worth, f.as_of_date, (f.net_worth - f.cash_available_to_withdraw) AS securities, f.cash_available_to_withdraw,
                     f.cash_available_to_withdraw AS cash_available_to_withdraw2, f.unsettled_cash, f.short_market_value, f.short_balance, 
                     f.dividend_accrual, CASE WHEN pf.production_number IS NOT NULL AND pf.production_number != '' THEN pf.production_number ELSE cf.production_number END AS production_number, 
                     f.cash_available_to_borrow, f.cash_available_to_withdraw AS cash_available_to_withdraw3, f.money_market_available, f.outstanding_calls, 
                     f.margin_balance, f.core_cash_market_value, f.margin_market_value, f.trade_date_legal_balance, f.face_amount, f.death_benefit_amount,
                     f.policy_account_value, f.cash_surrender_value, f.loan_balance, f.regulatory_net_worth, CASE WHEN pmap.omniscient_type != '' THEN pmap.omniscient_type ELSE cf.cf_2549 END AS omniscient_type, 
                     pf.registration, f.net_worth AS net_worth2, f.filename, 0 AS accountclosed, pf.omni_code,
                     p.portfolioinformationid
                  FROM vtiger_portfolioinformation p 
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                  JOIN custodian_omniscient.custodian_balances_fidelity f ON f.account_number = p.account_number 
                  LEFT JOIN custodian_omniscient.custodian_portfolios_fidelity pf ON pf.account_number = f.account_number 
                  LEFT JOIN custodian_omniscient.portfolios_mapping_fidelity pmap ON pmap.fidelity_type = pf.registration
                  JOIN custodian_omniscient.latestpositiondates lpd ON lpd.rep_code = cf.production_number 
                  WHERE f.account_number IN ({$questions}) 
                  AND f.as_of_date = lpd.last_position_date";
        $result = $adb->pquery($query, array($account_number), true);#21,826,709.36

        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_portfolioinformation p 
                      JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid 
                      SET p.total_value = ?, cf.stated_value_date = ?, cf.securities = ?, cf.cash = ?, p.cash_value = ?, cf.unsettled_cash = ?, 
                      cf.short_market_value = ?, cf.short_balance = ?, cf.dividend_accrual = ?, cf.production_number = ?, 
                      p.cash_available_to_borrow = ?, p.cash_available_to_withdraw = ?, p.money_market_funds = ?, p.outstanding_calls = ?, p.margin_balance = ?, p.core_cash_market_value = ?, 
                      p.margin_market_value = ?, p.trade_date_legal_balance = ?, p.face_amount = ?, p.death_benefit_amount = ?, p.policy_account_value = ?, 
                      p.cash_surrender_value = ?, p.loan_balance = ?, p.regulatory_net_worth = ?, cf.cf_2549 = ?, cf.account_registration = ?, 
                      cf.stated_net_worth = ?, cf.custodian_source = ?, p.accountclosed = ?, cf.omniscient_control_number = ?
                    WHERE p.portfolioinformationid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, $v, true);
            }
        }
    }

    static public function UpdateAllPortfoliosForAccounts(array $account_number){
        self::UpdateAllPortfoliosForAccountsPersonalOnly($account_number);
        self::UpdateAllPortfoliosForAccountsBalancesOnly($account_number);
    }

    static public function UpdateAllPortfolios(){
        $rep_codes = PortfolioInformation_Module_Model::GetRepCodeListFromUsersTable();
        $accounts = PortfolioInformation_Module_Model::GetAccountNumbersFromRepCodeOpenAndClosed($rep_codes);
        self::UpdateAllPortfoliosForAccounts($accounts);
    }

    static public function GetLatestBalance($account_number){
        global $adb;
        $query = "SELECT * 
                  FROM custodian_omniscient.custodian_balances_fidelity 
                  WHERE account_number = ?
                  ORDER BY as_of_date 
                  DESC LIMIT 1";
        $result = $adb->pquery($query, array($account_number));

        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, 'net_worth');
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

        $query = "SELECT account_number, net_worth AS account_value, MIN(as_of_date) AS as_of_date
                  FROM custodian_omniscient.custodian_balances_fidelity 
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

    static public function BalanceBetweenDates(array $account_number, $sdate, $edate){
        global $adb;
        $questions = generateQuestionMarks($account_number);
        $params = array();
        $params[] = $account_number;
        $params[] = $sdate;
        $params[] = $edate;

        $query = "SELECT account_number, net_worth AS value, as_of_date AS date
                  FROM custodian_omniscient.custodian_balances_fidelity 
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

    static public function UpdatePortfoliosForAccounts(array $account_number){
        global $adb;
        $questions = generateQuestionMarks($account_number);

        $query = "SELECT * FROM custodian_omniscient.custodian_portfolios_fidelity f
                  WHERE account_number IN ({$questions})";

        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){

            }
        }
/*
JOIN vtiger_portfolioinformation p ON p.dashless = f.account_number
JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
SET cf.description = f.primary_account_owner, p.first_name = f.primary_owner_first_name,
p.last_name = CASE WHEN f.primary_owner_last_name = '' THEN f.primary_account_owner ELSE f.primary_owner_last_name END, cf.address1 = f.address1_line1, cf.address2 = f.address1_line2,
cf.address3 = f.address1_line3, cf.address4 = f.address2_line1, cf.address5 = f.address2_line2,
cf.address6 = f.address2_line3, cf.city = f.city1, cf.state = f.state1, cf.zip = f.zip_code1, cf.custodian_inception = f.establishment_date,
cf.production_number = f.rep_code, cf.master_production_number = f.master_rep_code, cf.rep_code_multiple = f.rep_code_multiple,
p.account_type = CASE WHEN f.registration = '' THEN p.account_type ELSE f.registration END,
cf.email_address = f.primary_email, cf.omniscient_control_number = f.omni_code, cf.custodian_source = f.filename
WHERE cf.freeze_personal = 0";
*/
    }

    static public function GetBeginningBalanceAsOfDate(array $account_numbers, $date){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;
        $params[] = $date;

        $query = "SELECT account_number, net_worth AS value, as_of_date AS date
                  FROM custodian_omniscient.custodian_balances_fidelity 
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

        $query = "SELECT account_number, net_worth AS value, as_of_date AS date
                  FROM custodian_omniscient.custodian_balances_fidelity 
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
}
