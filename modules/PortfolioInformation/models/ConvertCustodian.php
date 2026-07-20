<?php
/**
 * Created by PhpStorm.
 * User: rsandnes
 * Date: 2016-03-29
 * Time: 1:48 PM
 */
global $dbconfig;
include_once("include/utils/omniscientCustom.php");

class PortfolioInformation_ConvertCustodian_Model extends Vtiger_Module_Model{
	static $tenant = "custodian_omniscient";

	/**
	 *
	 * @param $custodian
	 * @param $date
	 * @param $comparitor
	 * @return array|int
	 */
	static public function GetNewPortfolios($custodian, $date, $comparitor){
		global $adb;
		$tenant = self::$tenant;
		$query = "SELECT * FROM {$tenant}.custodian_balances_{$custodian} f WHERE f.account_number NOT IN (SELECT account_number FROM vtiger_portfolioinformation p WHERE p.dashless = REPLACE(f.account_number, '-', '') ) AND f.as_of_date = ?";
		$result = $adb->pquery($query, array($date));
		if($adb->num_rows($result) > 0){
			foreach($result AS $k => $v){
				$tmp[] = $v;
			}
			return $tmp;
		}
		return 0;
	}

	static public function GetDateDifference($date){
		$datetime1 = new DateTime();
		$datetime2 = new DateTime($date);
		$interval = $datetime1->diff($datetime2);
		return $interval->format('%R%a');
	}

	static public function SetDashless(){
		global $adb;

		$query = "UPDATE IGNORE vtiger_portfolioinformation SET dashless = REPLACE(account_number, '-', '')";
		$adb->pquery($query, array());
	}

	static private function PullNewPortfoliosFidelity($as_of_date){
		global $adb;
		$tenant = self::$tenant;

		$query = "DROP TABLE IF EXISTS new_portfolios";
		$adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE new_portfolios
                  SELECT p.account_number, p.account_name, p.t_account, p.registration, p.disposal_method, p.s_corp_indicator, 'Fidelity' AS custodian, 0 AS crmid, p.production_number, as_of_date, net_worth, buying_power, cash_available_to_withdraw, (net_worth-cash_available_to_withdraw) AS market_value, cash_available_to_borrow, money_market_available, core_cash_market_value, unsettled_cash, dividend_accrual
                  FROM {$tenant}.custodian_portfolios_fidelity p
                  LEFT JOIN {$tenant}.custodian_balances_fidelity f ON f.account_number = p.account_number AND f.as_of_date = ?
                  WHERE p.account_number NOT IN (SELECT dashless FROM vtiger_portfolioinformation WHERE dashless IS NOT NULL)
                  AND p.account_number NOT IN (SELECT account_number FROM vtiger_portfolioinformation)";
/*
 * This was the original query that seemed to be working for some time... If there are no balances however it breaks, as well as dashless being empty
		$query = "CREATE TEMPORARY TABLE new_portfolios
				  SELECT f.account_number, as_of_date, net_worth, buying_power, cash_available_to_withdraw, (net_worth-cash_available_to_withdraw) AS market_value, cash_available_to_borrow, money_market_available, core_cash_market_value, unsettled_cash, dividend_accrual, 'Fidelity' AS custodian, 0 AS crmid, p.production_number
				  FROM {$tenant}.custodian_balances_fidelity f
				  JOIN {$tenant}.custodian_portfolios_fidelity p ON f.account_number = p.account_number
				  WHERE as_of_date = ?
				  AND account_number NOT IN (SELECT dashless FROM vtiger_portfolioinformation) AND account_number NOT IN (SELECT REPLACE(account_number, '-', '') FROM vtiger_portfolioinformation)";
*/
		$adb->pquery($query, array($as_of_date));

        $crmid = $adb->getUniqueID("vtiger_crmentity");
		$query = "UPDATE new_portfolios SET crmid = ?";
		$adb->pquery($query, array($crmid));

#		$query = "SELECT * FROM new_portfolios";
#		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
		SELECT crmid, 1, 1, 1, 'PortfolioInformation', NOW(), NOW(), account_number FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination, total_value, market_value, cash_value)
		SELECT crmid, account_number, custodian, net_worth, market_value, cash_available_to_withdraw FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, unsettled_cash, dividend_accrual, production_number)
		SELECT crmid, unsettled_cash, dividend_accrual, production_number FROM new_portfolios";
		$adb->pquery($query, array());
	}

	static public function InsertAccountsIntoTDAmeritradeCloud($accounts){
		$tenant = self::$tenant;
		$query = "INSERT INTO {$tenant}.custodian_portfolios_td (account_number, first_name, last_name, advisor_id, street, address2, city, state, zip, phone_number, account_type)
				      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
				      ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name=VALUES(last_name), advisor_id=VALUES(advisor_id), street = VALUES(street), address2 = VALUES(address2)";

		foreach($accounts AS $k => $v) {
            $exists = PortfolioInformation_Module_Model::CheckCloudForAccountNumber("td", "custodian_omniscient", $v['accountNumber']);
            if($exists == 0) {
                global $adb;
                $adb->pquery($query, array($v['accountNumber'],
                    $v['firstName'],
                    $v['lastName'],
                    $v['repCode'],
                    $v['address1'],
                    $v['address2'],
                    $v['city'],
                    $v['state'],
                    $v['zip'],
                    $v['secondaryPhone'],
                    $v['accountType']));
            }
        }
	}

	static private function PullNewPortfoliosSchwab($as_of_date){
		global $adb;
		$tenant = self::$tenant;

		$query = "DROP TABLE IF EXISTS new_portfolios";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE new_portfolios
				  SELECT f.account_number, description, tax_id, inception, 'schwab' AS custodian, 0 AS crmid
				  FROM {$tenant}.custodian_portfolios_schwab f
				  WHERE account_number NOT IN (SELECT REPLACE(account_number, '-', '') FROM vtiger_portfolioinformation)";
		$adb->pquery($query, array());

		$crmid = $adb->getUniqueID("vtiger_crmentity");
		$query = "UPDATE new_portfolios SET crmid = ?";
		$adb->pquery($query, array($crmid));

		$query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
		SELECT crmid, 1, 1, 1, 'PortfolioInformation', NOW(), NOW(), account_number FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, last_name, origination, total_value, market_value, cash_value)
		SELECT crmid, account_number, description, custodian, 0, 0, 0 FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid, tax_id)
		SELECT crmid, tax_id FROM new_portfolios";
		$adb->pquery($query, array());
	}

	static private function PullNewPortfoliosTD(){
/*		$trade = new Trading_Ameritrade_Model();
		$accounts = $trade->GetAllAccounts("https://veoapi.advisorservices.com/InstitutionalAPIv2/api");
		$toInsert = $accounts['model']['getAccountsJson']['account'];
		self::InsertAccountsIntoTDAmeritradeCloud($toInsert);
*/
		global $adb;
		$tenant = self::$tenant;

		$query = "DROP TABLE IF EXISTS new_portfolios";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE new_portfolios
				  SELECT CONCAT(first_name, ' ', last_name) AS account_description, account_number, advisor_id, account_type, 'td' AS custodian, 0 AS crmid
				  FROM {$tenant}.custodian_portfolios_td f
				  WHERE account_number NOT IN (SELECT account_number FROM vtiger_portfolioinformation)";

		$adb->pquery($query, array());

		$crmid = $adb->getUniqueID("vtiger_crmentity");
		$query = "UPDATE new_portfolios SET crmid = ?()";
		$adb->pquery($query, array($crmid));

		$query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
		SELECT crmid, 1, 1, 1, 'PortfolioInformation', NOW(), NOW(), account_number FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, last_name, account_number, origination, total_value, market_value, cash_value)
		SELECT crmid, account_description, account_number, custodian, 0, 0, 0 FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid)
		SELECT crmid FROM new_portfolios";
		$adb->pquery($query, array());

	}

	static private function PullNewPortfoliosPershing($as_of_date){
		global $adb;
		$tenant = self::$tenant;

		$query = "DROP TABLE IF EXISTS new_portfolios";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE new_portfolios
				  SELECT account_number, date AS as_of_date, CONCAT(net_worth_sign, 1)*net_worth/100 AS net_worth, CONCAT(money_market_principal_sign, 1)*money_market_principal/100 AS cash_value, CONCAT(total_equity_sign, 1)*total_equity/100 AS market_value, 'Pershing' AS custodian, 0 AS crmid
				  FROM {$tenant}.custodian_balances_pershing f
				  WHERE date = ?
				  AND account_number NOT IN (SELECT dashless FROM vtiger_portfolioinformation)";

		$adb->pquery($query, array($as_of_date));

        $crmid = $adb->getUniqueID("vtiger_crmentity");
		$query = "UPDATE new_portfolios SET crmid = ?";
		$adb->pquery($query, array($crmid));

#		$query = "SELECT * FROM new_portfolios";
#		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
		SELECT crmid, 1, 1, 1, 'PortfolioInformation', NOW(), NOW(), account_number FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformation (portfolioinformationid, account_number, origination, total_value, market_value, cash_value)
		SELECT crmid, account_number, custodian, net_worth, market_value, cash_value FROM new_portfolios";
		$adb->pquery($query, array());

		$query = "INSERT INTO vtiger_portfolioinformationcf (portfolioinformationid)
		SELECT crmid FROM new_portfolios";
		$adb->pquery($query, array());
	}	

	static public function ConnectPortfolioInformationSSNFidelity(){
		global $adb;
		$tenant = self::$tenant;

		$query = "DROP TABLE IF EXISTS portfolio_update";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE portfolio_update
				  SELECT * FROM {$tenant}.custodian_ssn_fidelity GROUP BY account_number";
		$adb->pquery($query, array());

		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN portfolio_update up ON p.dashless = REPLACE(up.account_number, '-', '')
				  SET cf.tax_id = up.ssn, p.first_name = up.first_name, p.last_name = up.last_name
				  WHERE cf.tax_id = '' OR cf.tax_id is null";
		$adb->pquery($query, array());
	}

	static public function UpdatePortfolioValuesFidelity($date = null, $accounts = null){
		global $adb;
		$tenant = self::$tenant;
		if(!$date)
			$date = date("Y-m-d", strtotime("today -1 Weekday"));

		$params[] = $date;
		$and = "";
		if($accounts){
			$accounts = RemoveDashes($accounts);
			$questions = generateQuestionMarks($accounts);
			$params[] = $accounts;
			$and .= " AND p.dashless IN ({$questions})";
		}
//stated net worth
		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN {$tenant}.custodian_balances_fidelity f ON f.account_number = p.dashless
				  LEFT JOIN {$tenant}.custodian_portfolios_fidelity pf ON pf.account_number = f.account_number
				  SET p.total_value=f.net_worth, 
				  cf.securities = f.net_worth - f.cash_available_to_withdraw,
				  cf.cash = f.cash_available_to_withdraw,
				  p.cash_value = f.cash_available_to_withdraw,
				  cf.unsettled_cash = f.unsettled_cash, 
				  cf.short_market_value = f.short_market_value, 
				  cf.short_balance = f.short_balance, 
				  cf.dividend_accrual = f.dividend_accrual, 
				  cf.production_number = IF(pf.production_number IS NOT NULL AND pf.production_number != '', pf.production_number, cf.production_number),
				  p.cash_available_to_borrow=f.cash_available_to_borrow,
				  p.cash_available_to_withdraw=f.cash_available_to_withdraw,
				  p.money_market_funds=f.money_market_available,
				  p.outstanding_calls=f.outstanding_calls,
				  p.margin_balance=f.margin_balance,
				  p.core_cash_market_value=f.core_cash_market_value,
				  p.margin_market_value=f.margin_market_value,
				  p.trade_date_legal_balance=f.trade_date_legal_balance,
				  p.face_amount=f.face_amount,
				  p.death_benefit_amount=f.death_benefit_amount,
				  p.policy_account_value=f.policy_account_value,
				  p.cash_surrender_value=f.cash_surrender_value,
				  p.loan_balance=f.loan_balance,
				  p.regulatory_net_worth=f.regulatory_net_worth,
				  cf.stated_net_worth = f.net_worth
				  WHERE f.as_of_date = ?
				  {$and}";
#		echo $query; print_r($params);exit;
		$adb->pquery($query, $params);
	}

	static public function UpdatePortfolioValuesPershing($date, $accounts = null){
		global $adb;
		$tenant = self::$tenant;

        $params[] = $date;
        $and = "";

        if($accounts){
            $questions = generateQuestionMarks($accounts);
            $params[] = $accounts;
            $and .= " AND p.account_number IN ({$questions})";
        }

		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN {$tenant}.custodian_balances_pershing f ON f.account_number = p.dashless
				  SET p.total_value=CONCAT(net_worth_sign, 1)*f.net_worth/100, p.market_value = CONCAT(f.total_equity_sign, 1)*f.total_equity/100, p.cash_value = CONCAT(f.usde_net_sign, 1)*-1*f.usde_net/100, p.money_market_funds = CONCAT(f.total_usde_sign, 1)*-1*f.total_usde/100
				  WHERE f.date = ? {$and}";
		$adb->pquery($query, $params);
	}

	static public function UpdatePortfolioValuesTD($date = null, $accounts = null){
		global $adb;
		$tenant = self::$tenant;

		$params[] = $date;
		$and = "";

		if($accounts){
			$questions = generateQuestionMarks($accounts);
			$params[] = $accounts;
			$and .= " AND p.account_number IN ({$questions})";
		}
		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN {$tenant}.custodian_balances_td f ON f.account_number = p.account_number
				  SET p.total_value = f.account_value, p.money_market_funds = f.money_market
				  WHERE f.as_of_date = ? {$and}";
		$adb->pquery($query, $params);
	}

	static public function UpdatePortfolioValuesSchwab($date = null, $accounts = null){
		global $adb;
		$tenant = self::$tenant;

		$params[] = $date;
		$and = "";

		if($accounts){
			$questions = generateQuestionMarks($accounts);
			$params[] = $accounts;
			$and .= " AND p.account_number IN ({$questions})";
		}
		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN {$tenant}.custodian_balances_schwab f ON f.account_number = p.dashless
				  SET p.total_value = f.account_value, cf.cash = f.net_cash, cf.securities = f.market_value_long + f.market_long_minus_cash
				  WHERE f.as_of_date = ? {$and}";
		$adb->pquery($query, $params);
	}

	static public function UpdatePortfolioValuesAxos($date = null, $accounts = null){
		global $adb;
		$tenant = self::$tenant;

		$params = array();
		$and = "";

		if($date) {
			$params[] = $date;
			$and_date = " AND f.as_of_date = ? ";
		} else {
			$and_date = " AND f.as_of_date = (SELECT MAX(as_of_date) FROM {$tenant}.custodian_balances_axos WHERE account_number = p.dashless) ";
		}

		if($accounts){
			$questions = generateQuestionMarks($accounts);
			$params[] = $accounts;
			$and .= " AND p.account_number IN ({$questions})";
		}
		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN {$tenant}.custodian_balances_axos f ON f.account_number = p.dashless
				  SET p.total_value = f.account_value, cf.cash = f.net_cash, cf.securities = f.securities_value, p.cash_value = f.net_cash
				  WHERE 1=1 {$and_date} {$and}";
		$adb->pquery($query, $params);
	}

	static public function GetBalancesAxosAndWrite($accounts, $date = null){
		global $adb, $root_directory;
		$instance_path = rtrim($root_directory, '/');
		$tenant = self::$tenant;
		$account_numbers = RemoveDashes($accounts);
		$params = array();
		$and = "";
		
		if($date) {
			$params[] = $date;
			$and_date = " AND date = ? ";
		} else {
			$and_date = " AND date = (SELECT MAX(date) FROM {$tenant}.custodian_positions_axos WHERE account_number = pos.account_number AND filename LIKE ?) ";
			$params[] = $instance_path . '%';
		}

		if($account_numbers){
			$questions = generateQuestionMarks($accounts);
			$and = " AND account_number IN ({$questions}) ";
			$params[] = $account_numbers;
		}

		$and .= " AND filename LIKE ? ";
		$params[] = $instance_path . '%';

		$query = "DROP TABLE IF EXISTS BalanceTotals";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE BalanceTotals
				  SELECT account_number, date AS as_of_date,
				  SUM(market_value) AS account_value,
				  SUM(CASE WHEN symbol = 'CASHTCA' THEN market_value ELSE 0 END) AS net_cash,
				  SUM(CASE WHEN symbol != 'CASHTCA' THEN market_value ELSE 0 END) AS securities_value
				  FROM {$tenant}.custodian_positions_axos pos
				  WHERE 1=1 {$and_date} {$and}
				  GROUP BY account_number";
		$adb->pquery($query, $params);

		$query = "INSERT INTO {$tenant}.custodian_balances_axos (account_number, account_value, net_cash, securities_value, as_of_date, filename, insert_date)
				  SELECT account_number, account_value, net_cash, securities_value, as_of_date, 'derived', NOW() FROM BalanceTotals
				  ON DUPLICATE KEY UPDATE account_value = VALUES(account_value), net_cash = VALUES(net_cash), securities_value = VALUES(securities_value)";
		$adb->pquery($query, array());
	}

	static private function GetPortfolioInformation($custodian, $account_number){
		global $adb;
		$tenant = self::$tenant;

		$account_number = RemoveDashes($account_number);
		$query = "SELECT * FROM {$tenant}.custodian_portfolios_{$custodian} WHERE REPLACE(account_number, '-', '') = ?";
		$r = $adb->pquery($query, array($account_number));
		if($adb->num_rows($r) > 0){
			while ($v = $adb->fetchByAssoc($r)){
				return $v;
			}
		}else
			return 0;
	}

	static private function GetBalanceInformation($custodian, $account_number){
		global $adb;
		$tenant = self::$tenant;
		$account_number = RemoveDashes($account_number);
		$query = "SELECT * FROM {$tenant}.custodian_balances_{$custodian} WHERE REPLACE(account_number, '-', '') = ?";
		$r = $adb->pquery($query, array($account_number));
		if($adb->num_rows($r) > 0){
			while ($v = $adb->fetchByAssoc($r)){
				return $v;
			}
		}else
			return 0;
	}

	static public function DetermineCustodian($account_number){
		$r = self::GetPortfolioInformation("fidelity", $account_number);
		if($r)
			return "fidelity";
		$r = self::GetBalanceInformation("fidelity", $account_number);
		if($r)
			return "fidelity";
		$r = self::GetBalanceInformation("td", $account_number);
		if($r)
			return "td";
		$r = self::GetPortfolioInformation("schwab", $account_number);
		if($r)
			return "schwab";
		$r = self::GetBalanceInformation("pershing", $account_number);
		if($r)
			return "pershing";
		$r = self::GetPortfolioInformation("millenium", $account_number);
		if($r)
			return "millenium";
	}

	static public function GetBalancesTD($accounts, $date = null, $startIndex=null, $endIndex=null){
		$trade = new Trading_Ameritrade_Model();
        $tmp = $trade->GetBalances("https://veoapi.advisorservices.com/InstitutionalAPIv2/api", $accounts, $date, $startIndex, $endIndex);
		$balances = array();
		foreach($tmp['model']['getBalancesJson']['balance'] AS $k => $v){
			$balances[] = $v;
		}
		return $balances;
	}

	/**
	 * Unlike TD, we don't use an API to get an unknown balance.  We take the balances given to us from schwab in the form of Positions and write those directly to the table
	 * @param $accounts
	 * @param null $date
	 * @return mixed
	 */
	static public function GetBalancesSchwabAndWrite($accounts, $date = null){
		global $adb;
		$tenant = self::$tenant;
		$account_numbers = RemoveDashes($accounts);
		$params = array();
		$params[] = $date;
		if($account_numbers){
			$questions = generateQuestionMarks($accounts);
			$and = " AND account_number IN ({$questions}) ";
			$params[] = $account_numbers;
		}

		$query = "DROP TABLE IF EXISTS BalanceValues";
		$adb->pquery($query, array());

		$query = "DROP TABLE IF EXISTS BalanceTotals";
		$adb->pquery($query, array());

		$query = "DROP TABLE IF EXISTS Balances";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE BalanceValues
		SELECT *
		FROM {$tenant}.custodian_positions_schwab 
		WHERE SYMBOL IN ('CASH01', 'CASH02', 'CASH03', 'CASH04', 'CASH05', 'CASH06', 'CASH07', 'CASH08', 'CASH09', 'CASH10', 
												   'CASH11', 'CASH12', 'CASH13', 'CASH14', 'CASH15', 'CASH16', 'CASH17', 'CASH18', 'CASH19')
		AND date = ?";
		$adb->pquery($query, array($date));

		$query = 'CREATE TEMPORARY TABLE BalanceTotals
					SELECT account_number, date AS as_of_date, 
					CASE WHEN symbol = "CASH01" THEN quantity END AS c1, 
					CASE WHEN symbol = "CASH02" THEN quantity END AS c2, 
					CASE WHEN symbol = "CASH03" THEN quantity END AS c3, 
					CASE WHEN symbol = "CASH04" THEN quantity END AS c4, 
					CASE WHEN symbol = "CASH05" THEN quantity END AS c5, 
					CASE WHEN symbol = "CASH06" THEN quantity END AS c6, 
					CASE WHEN symbol = "CASH07" THEN quantity END AS c7, 
					CASE WHEN symbol = "CASH08" THEN quantity END AS c8, 
					CASE WHEN symbol = "CASH09" THEN quantity END AS c9, 
					CASE WHEN symbol = "CASH10" THEN quantity END AS c10, 
					CASE WHEN symbol = "CASH11" THEN quantity END AS c11, 
					CASE WHEN symbol = "CASH12" THEN quantity END AS c12, 
					CASE WHEN symbol = "CASH13" THEN quantity END AS c13, 
					CASE WHEN symbol = "CASH14" THEN quantity END AS c14, 
					CASE WHEN symbol = "CASH15" THEN quantity END AS c15, 
					CASE WHEN symbol = "CASH16" THEN quantity END AS c16, 
					CASE WHEN symbol = "CASH17" THEN quantity END AS c17, 
					CASE WHEN symbol = "CASH18" THEN quantity END AS c18, 
					CASE WHEN symbol = "CASH19" THEN quantity END AS c19
					FROM BalanceValues';
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE Balances
					SELECT account_number, as_of_date, SUM(c1) AS c1, SUM(c2) AS c2, SUM(c3) AS c3, SUM(c4) AS c4, SUM(c5) AS c5, SUM(c6) AS c6, SUM(c7) AS c7, SUM(c8) AS c8, SUM(c9) AS c9, SUM(c10) AS c10, SUM(c11) AS c11, SUM(c12) AS c12, SUM(c13) AS c13, SUM(c14) AS c14, 
								   SUM(c15) AS c15, SUM(c16) AS c16, SUM(c17) AS c17, SUM(c18) AS c18, SUM(c19) AS c19, SUM(c1) + SUM(c7) + SUM(c15) + SUM(c19) AS account_balance
					FROM BalanceTotals
					GROUP BY account_number";
		$adb->pquery($query, array());

		$query = "INSERT INTO {$tenant}.custodian_balances_schwab (account_number, account_value, net_cash, margin_balance, available_to_pay, margin_buying_power, money_market_funds, 
							    mtd_margin_interest, daily_interest, market_value_long, market_value_short, month_end_div_payout, market_long_minus_cash, as_of_date)
					SELECT account_number, account_balance, c1, c3, c4, c5, c7, c9, c10, c13, c14, c17, c19, as_of_date FROM Balances
					ON DUPLICATE KEY UPDATE net_cash = VALUES(net_cash), account_value = VALUES(account_value)";
		$adb->pquery($query, array());
	}

	static public function WriteBalancesTD($balances){
		global $adb;
		$tenant = self::$tenant;
		$query = "INSERT INTO {$tenant}.custodian_balances_td (account_number, account_type, account_description, repcode, account_value, money_market, cash_equivalent, 
							  available_funds, todays_net_change, buying_power, net_balance, option_buying_power, as_of_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
							  ON DUPLICATE KEY UPDATE repcode = VALUES(repcode), account_value = VALUES(account_value), net_balance = VALUES(net_balance)";
		foreach($balances AS $k => $v){
			$adb->pquery($query, array($v['accountNumber'],
									   $v['accountType'],
									   $v['accountDescription'],
				 					   $v['repCode'],
									   $v['accountValue'],
									   $v['moneyMarket'],
									   $v['cashEquivalent'],
									   $v['availableFunds'],
									   $v['todaysNetChange'],
									   $v['buyingPower'],
									   $v['netBalance'],
									   $v['optionBuyingPower'],
									   $v['asOfDate']));
		}
	}

    /**
     * Start and End index is used for TD due to having too many accounts.  It sets the limit so the data can be returned properly
     * @param $custodian
     * @param $accounts
     * @param null $date
     * @param null $startIndex
     * @param null $endIndex
     */
	static public function WriteBalancesToCloud($custodian, $accounts, $date = null, $startIndex=null, $endIndex=null){
#	    $attempt = 0;
		switch($custodian){
			case "td":
				$balances = self::GetBalancesTD($accounts, $date, $startIndex, $endIndex);
#				while(sizeof($balances) == 0 && $attempt < 5){
#                    $balances = self::GetBalancesTD($accounts, $date, $startIndex, $endIndex);
#                    $attempt++;
#                }
				echo "size -- " . sizeof($balances);
				echo "<br />";
				self::WriteBalancesTD($balances);
				break;
			case "schwab":
				self::GetBalancesSchwabAndWrite($accounts, $date);
				break;
			case "axos":
				self::GetBalancesAxosAndWrite($accounts, $date);
				break;
		}
	}

	/**
	 * This simply returns the query result
	 * @param $tenant
	 * @param $custodian
	 * @param $accounts
	 * @param $date
	 * @return mixed
	 */
	static private function GetCloudBalanceResult($tenant, $custodian, $accounts, $date){
		global $adb;
		$params = array();
		if(!$date)
			$date = date("Y-m-d", strtotime("today -1 Weekday"));
		$params[] = $date;
		$accounts = RemoveDashes($accounts);
		$and = "";
		if($accounts) {
			$questions = generateQuestionMarks($accounts);
			$and = " AND p.dashless IN ({$questions})";
			$params[] = $accounts;
		}

		switch($custodian){
			case "pershing":
				$query = "SELECT p.account_number, p.total_value, CONCAT(net_worth_sign, 1)*net_worth/100 AS net_worth FROM {$tenant}.custodian_balances_{$custodian} b
				  JOIN vtiger_portfolioinformation p ON p.dashless = REPLACE(b.account_number, '-', '')
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  WHERE e.deleted = 0
				  AND as_of_date = ?
				  {$and}
				  ORDER BY as_of_date DESC";
				break;
			case "fidelity":
				$query = "SELECT p.account_number, total_value, net_worth, margin_balance, b.unsettled_cash, b.dividend_accrual, b.short_balance FROM {$tenant}.custodian_balances_{$custodian} b
				  JOIN vtiger_portfolioinformation p ON p.dashless = REPLACE(b.account_number, '-', '')
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  WHERE e.deleted = 0
				  AND as_of_date = ?
				  {$and}
				  ORDER BY as_of_date DESC";
				break;
			default:
				$query = "SELECT * FROM {$tenant}.custodian_balances_{$custodian} b
				  JOIN vtiger_portfolioinformation p ON p.dashless = REPLACE(b.account_number, '-', '')
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  WHERE e.deleted = 0
				  AND as_of_date = ?
				  {$and}
				  ORDER BY as_of_date DESC";
				break;
		}

		return $adb->pquery($query, $params);
	}

	/**
	 * Gets the integrity values for Fidelity
	 * @param $date
	 * @param $accounts
	 * @return array
	 */
	static public function GetIntegrityValuesFidelity($date, $accounts){
		global $adb;
		$tenant = self::$tenant;

		$values = array();
		$good = 0;
		$bad = 0;

		$r = self::GetCloudBalanceResult($tenant, "fidelity", $accounts, $date);
		if($adb->num_rows($r) > 0) {
			while ($v = $adb->fetchByAssoc($r)) {
				$difference = $v['total_value'] - $v['net_worth'];
				if ($difference > 1 || $difference < -1) {
					$difference = $v['total_value'] - ($v['net_worth'] - $v['margin_balance'] - $v['unsettled_cash'] + $v['dividend_accrual'] + $v['short_balance']);
					#				echo $v['total_value'] . " - " . $v['net_worth'] . " - " . $v['margin_balance'] . " - " . $v['unsettled_cash'] . " + " . $v['dividend_accrual'];
				}

				if ($difference > 1 || $difference < -1) {
					$color = "red";
					$bad += 1;
				} else {
					$color = "green";
					$good += 1;
				}
				$tmp = array("account_number" => $v['account_number'],
					"crm_value" => $v['total_value'],
					"custodian_value" => $v['net_worth'],
					"difference" => $difference,
					"color" => $color);
				$values['info'][] = $tmp;
			}
			$values['good'] = $good;
			$values['bad'] = $bad;
		}else{
			$values['bad'] = 1;
		}
		return $values;
	}

	/**
	 * Gets the integrity values for Fidelity
	 * @param $date
	 * @param $accounts
	 * @return array
	 */
	static public function GetIntegrityValuesSchwab($date, $accounts){
		global $adb;
		$tenant = self::$tenant;

		$values = array();
		$good = 0;
		$bad = 0;

		$r = self::GetCloudBalanceResult($tenant, "schwab", $accounts, $date);
		if($adb->num_rows($r) > 0) {
			while ($v = $adb->fetchByAssoc($r)) {
				$difference = $v['total_value'] - $v['account_value'];
				if ($difference > 1 || $difference < -1) {
					$difference = $v['total_value']  - ($v['account_value'] + $v['margin_balance'] + $v['money_market_funds']);// - $v['margin_balance'] - $v['unsettled_cash'] + $v['dividend_accrual'] + $v['short_balance']);
#					#				echo $v['total_value'] . " - " . $v['net_worth'] . " - " . $v['margin_balance'] . " - " . $v['unsettled_cash'] . " + " . $v['dividend_accrual'];
				}

				if ($difference > 1 || $difference < -1) {
					$color = "red";
					$bad += 1;
				} else {
					$color = "green";
					$good += 1;
				}
				$notes = array("margin_balance" => $v['margin_balance'],
						 	   "money_market_funds" => $v['money_market_funds']);
				$tmp = array("account_number" => $v['account_number'],
					"crm_value" => $v['total_value'],
					"custodian_value" => $v['account_value'],
					"difference" => $difference,
					"special_notes" => 1,
					"notes" => $notes,
					"color" => $color);
				$values['info'][] = $tmp;
			}
			$values['good'] = $good;
			$values['bad'] = $bad;
		}else{
			$values['bad'] = 1;
		}
		return $values;
	}

	/**
	 * Gets the integrity values for Pershing
	 * @param $date
	 * @param $accounts
	 * @return array
	 */
	static public function GetIntegrityValuesPershing($date, $accounts){
		global $adb;
		$tenant = self::$tenant;

		$values = array();
		$good = 0;
		$bad = 0;

		$r = self::GetCloudBalanceResult($tenant, "pershing", $accounts, $date);
		if($adb->num_rows($r) > 0) {
			while ($v = $adb->fetchByAssoc($r)) {
				$difference = $v['total_value'] - $v['net_worth'];
				if ($difference > 1 || $difference < -1) {
					$difference = $v['total_value']  - ($v['net_worth'] + $v['margin_balance'] + $v['money_market_funds']);// - $v['margin_balance'] - $v['unsettled_cash'] + $v['dividend_accrual'] + $v['short_balance']);
#					#				echo $v['total_value'] . " - " . $v['net_worth'] . " - " . $v['margin_balance'] . " - " . $v['unsettled_cash'] . " + " . $v['dividend_accrual'];
				}

				if ($difference > 1 || $difference < -1) {
					$color = "red";
					$bad += 1;
				} else {
					$color = "green";
					$good += 1;
				}
				$notes = array("margin_balance" => $v['margin_balance'],
					"money_market_funds" => $v['money_market_funds']);
				$tmp = array("account_number" => $v['account_number'],
					"crm_value" => $v['total_value'],
					"custodian_value" => $v['net_worth'],
					"difference" => $difference,
					"special_notes" => 1,
					"notes" => $notes,
					"color" => $color);
				$values['info'][] = $tmp;
			}
			$values['good'] = $good;
			$values['bad'] = $bad;
		}else{
			$values['bad'] = 1;
		}
		return $values;
	}

	/**
	 * Gets the integrity values for TD
	 * @param $date
	 * @param $accounts
	 */
	static public function GetIntegrityValuesTD($date, $accounts){
		global $adb;
		$tenant = self::$tenant;

		$values = array();
		$good = 0;
		$bad = 0;

		$r = self::GetCloudBalanceResult($tenant, "td", $accounts, $date);
		if($adb->num_rows($r) > 0) {
			while ($v = $adb->fetchByAssoc($r)) {
				$difference = $v['total_value'] - $v['account_value'];
				if ($difference > 1 || $difference < -1) {
					$difference = $v['total_value'] - ($v['account_value']);
					$color = "red";
					$bad += 1;
				} else {
					$color = "green";
					$good += 1;
				}
				$tmp = array("account_number" => $v['account_number'],
					"crm_value" => $v['total_value'],
					"custodian_value" => $v['account_value'],
					"difference" => $difference,
					"color" => $color);
				$values['info'][] = $tmp;
			}
			$values['good'] = $good;
			$values['bad'] = $bad;
		}else{
			$values['bad'] = 1;
			$values['note'] = "There is no balance result from the Custodian";
		}

		return $values;
	}

	/**
	 * Determines the last position for the accounts provided and returns the date result
	 * @param $custodian
	 * @param $accounts
	 * @return int|mixed|string
	 */
	static public function GetLastPositionDateForAccounts($custodian, $accounts){
		global $adb;
		$tenant = self::$tenant;

		switch($custodian){
			case "fidelity":
				$date_field = "as_of_date";
				break;
			case "pershing":
				$date_field = "position_date";
				break;
			default:
				$date_field = "date";
				break;
		}

		$account_number = RemoveDashes($accounts);
		$questions = generateQuestionMarks($account_number);

		$query = "SELECT p.{$date_field} AS date_field FROM {$tenant}.custodian_positions_{$custodian} p
				  WHERE account_number IN ({$questions}) 
				  ORDER BY {$date_field} DESC LIMIT 1";
		$result = $adb->pquery($query, array($account_number));
		if($adb->num_rows($result) > 0){
			return $adb->query_result($result, 0, "date_field");
		}
		return 0;
	}

	static public function IntegrityCheck($custodian, $date=null, $accounts=null){
		$custodian = trim($custodian);
		if(!$date)
			$date = date("Y-m-d", strtotime("today -1 Weekday"));

		switch($custodian){
			case "fidelity":
				return self::GetIntegrityValuesFidelity($date, $accounts);
				break;
			case "td":
				return self::GetIntegrityValuesTD($date, $accounts);
				break;
			case "schwab":
				return self::GetIntegrityValuesSchwab($date, $accounts);
				break;
			case "pershing":
				return self::GetIntegrityValuesPershing($date, $accounts);
			break;
		}
	}

	/**
	 * Check if the security symbol already exists
	 * @param $original_id
	 * @param $custodian
	 */
	static public function DoesPortfolioAlreadyExist($account_number){
		global $adb;
		$query = "SELECT portfolioinformationid FROM vtiger_portfolioinformation p JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid WHERE REPLACE(account_number, '-', '') = REPLACE(?, '-', '')";
		$result = $adb->pquery($query, array($account_number));
		if($adb->num_rows($result) > 0){
			return $adb->query_result($result, 0, 'positioninformationid');
		}
		return 0;
	}


	static public function ConvertCustodian($custodian, $date, $comparitor, $account_number){
	    //NO LONGER SAFE
	    return;/*
		switch($custodian){
			case "fidelity":
				self::PullNewPortfoliosFidelity($date);
				self::SetDashless();
				self::ConnectPortfolioInformationSSNFidelity();
				break;
			case "pershing":
				self::PullNewPortfoliosPershing($date);
				self::SetDashless();
				break;
			case "td":
				self::PullNewPortfoliosTD($date);
				self::SetDashless();
				break;
			case "schwab":
				self::PullNewPortfoliosSchwab($date);
				self::SetDashless();
				break;
		}
		echo "New Portfolios Pulled for {$custodian}";*/
	}

	/**
	 * Links PortfolioInformation module to contacts when it is currently empty
	 */
	static public function LinkContactsToPortfolios($account_number = null){
		global $adb;
		$params = array();
		if($account_number){
			$and = " AND p.account_number = ? ";
			$params[] = $account_number;
		}
/*		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN vtiger_contactscf ccf ON cf.tax_id = ccf.ssn
				  JOIN vtiger_crmentity e ON e.crmid = ccf.contactid
				  SET p.contact_link = ccf.contactid
				  WHERE REPLACE(cf.tax_id, '-', '') != '' AND e.deleted = 0 {$and} ";
		$adb->pquery($query, $params);*/

        $query = "DROP TABLE IF EXISTS ContactSSN";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS PortfolioSSN";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS Combined";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE ContactSSN
                    SELECT contactid, REPLACE(ssn, '-', '') AS ssn 
                    FROM vtiger_contactscf 
                    JOIN vtiger_crmentity ON crmid = contactid
                    WHERE TRIM(ssn) <> '' AND deleted = 0";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE PortfolioSSN
                    SELECT cf.portfolioinformationid, REPLACE(tax_id, '-', '') AS ssn 
                    FROM vtiger_portfolioinformationcf cf
                    JOIN vtiger_portfolioinformation p ON p.portfolioinformationid = cf.portfolioinformationid
                    JOIN vtiger_crmentity ON crmid = cf.portfolioinformationid
                    WHERE TRIM(tax_id) <> '' AND deleted = 0 {$and}";
        $adb->pquery($query, $params);

        $query = "CREATE TEMPORARY TABLE Combined
                    SELECT contactid, portfolioinformationid, c.ssn AS cSSN, p.ssn AS pSSN
                    FROM ContactSSN c
                    JOIN PortfolioSSN p ON c.ssn = p.ssn
                    JOIN vtiger_crmentity e ON e.crmid = c.contactid
                    WHERE TRIM(c.ssn) <> '' AND TRIM(p.ssn) <> ''
                            AND e.deleted = 0
                    GROUP BY portfolioinformationid";
        $adb->pquery($query, array());

        $query = "UPDATE vtiger_portfolioinformation p
                    JOIN Combined c ON p.portfolioinformationid = c.portfolioinformationid
                    SET p.contact_link = c.contactid";
        $adb->pquery($query, array());
	}

	static public function LinkHouseholdsToPortfolios($account_number = null){
		global $adb;
		$params = array();
		if($account_number){
			$and = " AND p.account_number = ? ";
			$params[] = $account_number;
		}
		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_contactdetails cd ON p.contact_link = cd.contactid
				  SET p.household_account = cd.accountid
				  WHERE p.contact_link != 0 {$and} ";//" AND p.household_account != 0 AND household_account = 0 OR household_account = ''";
		$adb->pquery($query, $params);
	}

	/**
	 * Assigns the portfolio to the appropriate user based on contact_link.  Contact owner cannot be set to admin
	 * @param null $account_number
	 * @param int $admin_only - If true, only assign portfolios that are set to admin.  True by default
	 */
	static public function AssignPortfoliosBasedOnContactLink($account_number=null, $admin_only=1){
		global $adb;
		$and = "";
		$params = array();
		if($account_number) {
			$and .= " AND p.account_number = ?";
			$params[] = $account_number;
		}

		if($admin_only){
			$and .= " AND e.smownerid = ?";
			$params[] = 1;
		}

		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  JOIN vtiger_crmentity contact_owner ON contact_owner.crmid = p.contact_link
				  SET e.smownerid = contact_owner.smownerid
				  WHERE contact_owner.smownerid != 1
				  {$and} ";
		$adb->pquery($query, $params);
	}

	static private function CreateAccountsToUpdate($custodian, $accounts=null){
        $tenant = self::$tenant;
		global $adb;
		$and = "";
		$params = array();
		if(is_array($accounts))
			$account_number = $accounts;
		else
			if(strlen($accounts) > 2)
				$account_number[] = $accounts;

		$account_number = RemoveDashes($account_number);
		if($account_number){
			$questions = generateQuestionMarks($account_number);
			$and = " AND p.dashless IN ({$questions})";
			$params[] = $account_number;
		}

		$query = "DROP TABLE IF EXISTS AccountsToUpdate";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE AccountsToUpdate
				  SELECT p.dashless AS account_number FROM vtiger_portfolioinformation p 
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  WHERE p.dashless IN (SELECT REPLACE(account_number, '-', '') FROM {$tenant}.custodian_portfolios_{$custodian})
				  AND account_number != '' {$and}";
		$adb->pquery($query, $params);
	}

	static private function CreatePositionsWithValues($custodian = null, $accounts = null){
		global $adb;

		if(!$custodian && !$accounts)//We require at least a custodian or an account number for now.  Don't want to be updating Portfolios we don't have positions for/broken positions from other custodians we haven't covered yet
			return;

		$query = "DROP TABLE IF EXISTS PositionsWithValues";
		$adb->pquery($query, array());

		if($accounts)
			$account_number = RemoveDashes($accounts);

		$and = "";
		$params = array();
#		$account_number = RemoveDashes($account_number);
		if(strlen($custodian) > 3){
//			$and .= " AND p.dashless IN (SELECT account_number FROM {$tenant}.custodian_portfolios_{$custodian}) ";
		}
		if($account_number){
			$questions = generateQuestionMarks($account_number);
			$and .= " AND p.dashless IN ({$questions})";
			$params[] = $account_number;
		}

		$query = "CREATE TEMPORARY TABLE PositionsWithValues
				  SELECT (p.current_value * (us_stock + intl_stock)/100) AS equity_value,
				  (p.current_value * (us_bond + intl_bond + preferred_net)/100) AS fixed_value, 
				  (p.current_value * (cash_net)/100) AS cash_value, 
				  (p.current_value * (other_net)/100) AS other_value, 
				  (p.current_value * (unclassified_net)/100) AS unclassified_value, 
				  CASE WHEN ((cash_net=100 AND p.last_price=1) OR mcf.cash_instrument = 1) THEN p.current_value END AS cash_balance,
				  CASE WHEN (cash_net!=100 AND mcf.cash_instrument != 1 OR (cash_net=100 AND p.last_price!=1)) THEN p.current_value END AS market_balance,
				  p.*, mcf.* FROM vtiger_positioninformation p 
				  JOIN vtiger_positioninformationcf cf ON p.positioninformationid = cf.positioninformationid
				  JOIN vtiger_modsecurities m ON m.security_symbol = p.security_symbol
				  JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
				  WHERE p.quantity != 0	
				  {$and}";
		$adb->pquery($query, $params);
	}

	static private function CreatePortfolioValues(){
		global $adb;
		$query = "DROP TABLE IF EXISTS PortfolioValues";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE PortfolioValues
				  SELECT account_number, SUM(equity_value) AS equity_value, SUM(fixed_value) AS fixed_value, SUM(cash_value) AS cash_value, SUM(other_value) AS other_value, SUM(unclassified_value) AS unclassified_value,
				  SUM(current_value) AS current_value, SUM(cash_balance) AS cash_balance, SUM(market_balance) AS market_balance, REPLACE(account_number, '-', '') AS dashless
				  FROM PositionsWithvalues
				  GROUP BY REPLACE(account_number, '-', '')";
		$adb->pquery($query, array());
	}

	/**
	 * Uses print_r for each portfolio value...Used for debugging
	 */
	static private function PrintPortfolioValues(){
		global $adb;
		$query = "SELECT * FROM PortfolioValues";
		$result = $adb->pquery($query, array());
		if($adb->num_rows($result) > 0){
			while($v = $adb->fetchByAssoc($result)){
				print_r($v);
				echo "<br /><br />>";
			}
		}
	}

	/**
	 * Uses print_r for each Positions with value...Used for debugging
	 */
	static private function PrintPositionsWithValues(){
		global $adb;
		$query = "SELECT * FROM PositionsWithValues WHERE dashless = '676838918'";
		$r = $adb->pquery($query, array());
		echo $adb->num_rows($r);
		exit;
		$tmp = $adb->fetchByAssoc($r);
		print_r($tmp);
		exit;
	}

	static public function UpdatePortfolioValuesFromPositions($custodian=null, $accounts=null){
		global $adb;
#		self::CreateAccountsToUpdate($custodian, $accounts);
		self::CreatePositionsWithValues($custodian, $accounts);
		self::CreatePortfolioValues();
#		self::PrintPortfolioValues();exit;
/*
# * DEBUG FOR WHEN WE HAVE ISSUES OF PORTFOLIOS NOT TOTALLING

		$query = "SELECT * FROM PositionsWithValues WHERE dashless = 'J7T200059'";
		$r = $adb->pquery($query, array());
		echo $adb->num_rows($r);
		exit;
		$tmp = $adb->fetchByAssoc($r);
		print_r($tmp);
		exit;
*/
/*
		$query = "SELECT * FROM PortfolioValues WHERE dashless = '939387495'";
		$r = $adb->pquery($query, array());
		echo $adb->num_rows($r);
		$tmp = $adb->fetchByAssoc($r);
		print_r($tmp);
		exit;
*/
		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf USING (portfolioinformationid)
				  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
				  JOIN PortfolioValues pv ON pv.dashless = p.dashless
				  SET cf.equities = pv.equity_value, cf.fixed_income = pv.fixed_value, p.cash_value = pv.cash_value, cf.other_value = pv.other_value, 
				  cf.unclassified_value = pv.unclassified_value, p.total_value = pv.current_value, cf.cash=pv.cash_balance, cf.securities=pv.market_balance, e.modifiedtime = NOW()";
		$adb->pquery($query, array());
	}

	static public function UpdateIntegrityHistory($total, $good, $bad, $custodian, $date){
		global $adb;
		$query = "INSERT INTO vtiger_integrity_history (total, good, bad, custodian, date) 
				  VALUES (?, ?, ?, ?, ?) 
				  ON DUPLICATE KEY UPDATE total = VALUES(total), good = VALUES(good), bad = VALUES(bad)";
		$adb->pquery($query, array($total, $good, $bad, $custodian, $date));
	}

	static public function UpdatePCCustodian($custodian, $account_number)
	{
		$pc = new PortfolioInformation_PCQuery_Model();
		$id = 0;
		switch ($custodian) {
			case "fidelity":
				$id = 5;
				break;
			case "schwab":
				$id = 3;
				break;
			case "pershing":
				$id = 10;
				break;
			case "td":
				$id = 25;
				break;
			case "millenium":
				$id = 22;
				break;
		}
		if ($id != 0) {
			$query = "UPDATE [PortfolioCenter].[dbo].[Portfolios] SET OriginationID = {$id} WHERE REPLACE(AccountNumber, '-', '') = '{$account_number}'";
			$pc->CustomQuery($query);
		}
	}

	static public function ResetAllValuesByCustodian($custodian){
		global $adb;
		$query = "UPDATE vtiger_portfolioinformation JOIN vtiger_portfolioinformationcf USING (portfolioinformationid)
			      SET total_value = 0, market_value = 0, cash_value = 0, cash = 0, equities = 0, fixed_income = 0, unsettled_cash = 0, 
			          other_value = 0, unclassified_value = 0, dividend_accrual = 0, short_market_value = 0, short_balance = 0, securities = 0
			      WHERE origination = ?";
		$adb->pquery($query, array($custodian));
	}

	static public function UpdateAllFidelityPortfoliosWithLatestInfoForAccount($account_number = null){
        $tenant = self::$tenant;
		global $adb;
		$query = "DROP TABLE IF EXISTS AccountWithDate";
		$adb->pquery($query, array());

		$query = "DROP TABLE IF EXISTS Balances";
		$adb->pquery($query, array());

		$params = array();
		if($account_number) {
			$params[] = $account_number;
			$where = " WHERE account_number = ? ";
		}
		$query = "CREATE TEMPORARY TABLE AccountWithDate 
			 	  SELECT account_number, MAX(as_of_date) as_of_date 
			 	  FROM {$tenant}.custodian_balances_fidelity {$where}
			 	  GROUP BY account_number 
			 	  ORDER BY as_of_date DESC";
		$adb->pquery($query, $params);

		$query = "CREATE TEMPORARY TABLE Balances
				  SELECT f.* FROM {$tenant}.custodian_balances_fidelity f
				  JOIN AccountWithDate awd ON awd.account_number = f.account_number
				  WHERE awd.as_of_date = f.as_of_date";
		$adb->pquery($query, array());

		$query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN Balances f ON f.account_number = p.dashless
				  LEFT JOIN {$tenant}.custodian_portfolios_fidelity pf ON pf.account_number = f.account_number
				  SET p.total_value=f.net_worth,
				  cf.securities = f.net_worth - f.cash_available_to_withdraw,
				  cf.cash = f.cash_available_to_withdraw,
				  p.cash_value = f.cash_available_to_withdraw,
				  cf.unsettled_cash = f.unsettled_cash,
				  cf.short_market_value = f.short_market_value,
				  cf.short_balance = f.short_balance,
				  cf.dividend_accrual = f.dividend_accrual,
				  cf.production_number = IF(pf.production_number IS NOT NULL AND pf.production_number != '', pf.production_number, cf.production_number),
				  p.cash_available_to_borrow=f.cash_available_to_borrow,
				  p.cash_available_to_withdraw=f.cash_available_to_withdraw,
				  p.money_market_funds=f.money_market_available,
				  p.outstanding_calls=f.outstanding_calls,
				  p.margin_balance=f.margin_balance,
				  p.core_cash_market_value=f.core_cash_market_value,
				  p.margin_market_value=f.margin_market_value,
				  p.trade_date_legal_balance=f.trade_date_legal_balance,
				  p.face_amount=f.face_amount,
				  p.death_benefit_amount=f.death_benefit_amount,
				  p.policy_account_value=f.policy_account_value,
				  p.cash_surrender_value=f.cash_surrender_value,
				  p.loan_balance=f.loan_balance,
				  p.regulatory_net_worth=f.regulatory_net_worth,
				  cf.stated_net_worth = f.net_worth";
		$adb->pquery($query, array());
	}

	static public function UpdateAllSchwabPortfoliosWithLatestInfoForAccount($account_number = null){
        $tenant = self::$tenant;
		global $adb;

		$query = "DROP TABLE IF EXISTS AccountWithDate";
		$adb->pquery($query, array());

		$query = "DROP TABLE IF EXISTS Balances";
		$adb->pquery($query, array());

		$params = array();
		if($account_number) {
			$params[] = $account_number;
			$where = " WHERE account_number = ? ";
		}
		$query = "CREATE TEMPORARY TABLE AccountWithDate
				  SELECT account_number, MAX(as_of_date) as_of_date
				  FROM {$tenant}.custodian_balances_schwab
				  {$where}
				  GROUP BY account_number ORDER BY as_of_date DESC";
		$adb->pquery($query, $params);

		$query = "CREATE TEMPORARY TABLE Balances
				  SELECT f.* FROM {$tenant}.custodian_balances_schwab f
				  JOIN AccountWithDate awd ON awd.account_number = f.account_number
				  WHERE awd.as_of_date = f.as_of_date";
		$adb->pquery($query, array());

        $query = "UPDATE vtiger_portfolioinformation p
				  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
				  JOIN balances f ON f.account_number = p.dashless
				  SET p.total_value = f.account_value, 
				  p.cash_available_to_withdraw = f.net_cash + f.money_market_funds, 
				  cf.cash = f.net_cash + f.money_market_funds, 
				  cf.securities = f.market_long_minus_cash + f.market_value_long,
				  p.available_to_pay = f.available_to_pay,
				  p.margin_buying_power = f.margin_buying_power,
				  p.money_market_funds = f.money_market_funds,
				  p.mtd_margin_interest = f.mtd_margin_interest,
				  p.daily_interest = f.daily_interest,
				  p.market_value_long = f.market_value_long,
				  p.market_value_short = f.market_value_short,
				  p.month_end_dividend_payout = f.month_end_div_payout,
				  p.market_long_minus_cash = f.market_long_minus_cash,
				  p.market_short_minus_cash = f.market_short_minus_cash";
		$adb->pquery($query, array());

	}

	static private function GenerateLiveAccounts($custodian, $date){
        $tenant = self::$tenant;
	    global $adb;
	    DropTable("LiveAccounts");
        $query = "CREATE TEMPORARY TABLE LiveAccounts
                  SELECT account_number 
                  FROM {$tenant}.custodian_balances_{$custodian} 
                  WHERE as_of_date >= ? GROUP BY account_number";
        $adb->pquery($query, array($date));
    }

    static private function GenerateClosedAccounts($custodian, $date){
        $tenant = self::$tenant;
	    global $adb;
	    self::GenerateLiveAccounts($custodian, $date);
        DropTable("ClosedAccounts");
        $query = "CREATE TEMPORARY TABLE ClosedAccounts
                  SELECT account_number
                  FROM {$tenant}.custodian_balances_{$custodian}
                  WHERE as_of_date < ? AND account_number 
                  NOT IN (SELECT account_number FROM LiveAccounts)
                  GROUP BY account_number";
        $adb->pquery($query, array($date));
    }

    /**
     * Determine which accounts are in the CRM but do not exist in the cloud for the given custodian
     * @param $custodian
     * @return array|int
     */
    static public function GetAccountsNotInCloud($custodian){
        $tenant = self::$tenant;
        global $adb;
        DropTable('TempCloudList');

        $query = "CREATE TEMPORARY TABLE TempCloudList
                  SELECT account_number FROM {$tenant}.custodian_balances_{$custodian} GROUP BY account_number";
        $adb->pquery($query, array());

        $query = "SELECT account_number FROM vtiger_portfolioinformation p
                  WHERE p.origination = ?
                  AND p.dashless NOT IN (SELECT account_number FROM TempCloudList)";
        $result = $adb->pquery($query, array($custodian));
        if($adb->num_rows($result) > 0){
            $rows = array();
            while($v = $adb->fetchByAssoc($result)){
                $rows[] = $v;
            }
            return $rows;
        }
        return 0;
    }

    /**
     * Generates the temporary missing account table
     */
    static private function GenerateMissingTemporaryTables(){
        global $adb;
        $query = "DROP TABLE IF EXISTS ActiveAccounts";
        $adb->pquery($query, array());

        $query = "DROP TABLE IF EXISTS ExistingAccounts";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE ActiveAccounts
                        (account_number VARCHAR (50) PRIMARY KEY,
                        date DATE, custodian VARCHAR(50));";
        $adb->pquery($query, array());
    }

    /**
     * Determines the missing accounts and returns them. 0 if none
     * @return array|int
     */
    static private function ReturnMissingAccounts(){
        global $adb;
        $query = "CREATE TEMPORARY TABLE ExistingAccounts
                  SELECT a.account_number, a.custodian FROM vtiger_portfolioinformation p
                  JOIN ActiveAccounts a ON a.account_number = REPLACE(p.account_number, '-', '')";
        $adb->pquery($query, array());

        $query = "SELECT account_number FROM ActiveAccounts WHERE account_number NOT IN (SELECT account_number FROM ExistingAccounts)";
        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            while ($v = $adb->fetchByAssoc($result)){
                $portfolios[] = $v['account_number'];
            }
            return $portfolios;
        }
        return 0;
    }

    /**
     * Determine which accounts aren't in the CRM, but are in the TD Balances table
     */
    static public function GetMissingTDAccountsFromBalances(){
        $tenant = self::$tenant;
        global $adb;
        self::GenerateMissingTemporaryTables();
        $query = "INSERT INTO ActiveAccounts
                  SELECT account_number, MAX(as_of_date) AS date, 'TD Ameritrade' AS custodian
                  FROM {$tenant}.custodian_balances_td
                  WHERE as_of_date >= NOW() - INTERVAL 2 WEEK
                  GROUP BY account_number";
        $adb->pquery($query, array());
        return self::ReturnMissingAccounts();
    }

    static public function GetMissingFidelityAccountsFromBalances(){
        $tenant = self::$tenant;
        global $adb;
        self::GenerateMissingTemporaryTables();
        $query = "INSERT INTO ActiveAccounts
                  SELECT account_number, MAX(as_of_date) AS date, 'Fidelity' AS custodian 
                  FROM {$tenant}.custodian_balances_fidelity
                  WHERE as_of_date >= NOW() - INTERVAL 2 WEEK
                  GROUP BY account_number;";
        $adb->pquery($query, array());
        return self::ReturnMissingAccounts();
    }

    static public function CreateAndUpdatePortfoliosFromFidelity(array $account_numbers){
        $tenant = self::$tenant;
        global $adb;
        if(!is_array($account_numbers) OR sizeof($account_numbers) == 0)
            return 0;

        $questions = generateQuestionMarks($account_numbers);
        $query = "SELECT * FROM {$tenant}.custodian_portfolios_fidelity WHERE account_number IN ({$questions})";
        $result = $adb->pquery($query, array($account_numbers));
        if($adb->num_rows($result) > 0){
            $rows = array();
            while($v = $adb->fetchByAssoc($result)){
                $recordModel = PortfolioInformation_Record_Model::getCleanInstance("PortfolioInformation");
                $data = $recordModel->getData();
                $data['account_number'] = $v['account_number'];
                $data['description'] = $v['account_name'];
                $data['origination'] = 'fidelity';
                if(PortfolioInformation_Module_Model::GetCrmidFromAccountNumber($v['account_number']) == 0) {
                    $recordModel->setData($data);
                    $recordModel->set('mode', 'create');
                    $recordModel->save();
                    $d = date ( 'Y-m-d' , strtotime ( '-1 weekdays' ));
                    echo "<p>WROTE: {$data['account_number']} -- {$d}</p>";
                    PortfolioInformation_ConvertCustodian_Model::UpdatePortfolioValuesFidelity($d, $v['account_number']);
                }else{
                    echo "<p>{$data['account_number']} EXISTS, currently doing nothing!</p>";
                }
            }
            return $rows;
        }
        return 0;
    }

/*	static public function CloseAccountsLessThanSpecifiedDate($custodian, $date){
	    global $adb;

    }
*/
	static private function CloudToModuleConversion($custodian, $date, $comparitor, $account_number){
/*		$security_type_map = self::GetSecurityTypeMapping();
		$positions = self::GetNewPositions($custodian, $date, $comparitor);
		$count = 0;
		if($positions){
			set_time_limit ( 0 );
			foreach($positions AS $k => $v){
#				echo "START OF LOOP: " . memory_get_peak_usage() . " - Count: " . $count . PHP_EOL;
				$record = self::DoesPositionAlreadyExist($v['account_number'], $v['symbol']);
				if($record){//If the record exists, use it instead
					$tmp = Vtiger_Record_Model::getInstanceById($record, "PositionInformation");
					$tmp->set('mode', 'edit');
#					echo "EDIT<br />";
				}else{
					$tmp = Vtiger_Record_Model::getCleanInstance("PositionInformation");
					$tmp->set('mode', 'create');
#					echo "NEW<br />";
					$count++;
				}

				$data = $tmp->getData();
				self::MapCloudToModuleData($custodian, $security_type_map, $v, $data);
				$tmp->setData($data);
				$tmp->save();
			}
		}

		self::ClearPositionInfoByCustodian($custodian, $date, $account_number);
//		self::UpdatePositionInfoFromCloud($custodian, $date, '638-221089');
		self::UpdatePriceAdjustment();
		self::UpdateAllPrices();
		if(strlen($account_number) > 5)
			self::UpdatePositionInfoByCustodianIndividualAccount($custodian, $date, $account_number);
		else
			self::UpdatePositionInfoByCustodianAllAccounts($custodian, $date);

		echo "{$count} Positions Added.  All positions updated to specified date of {$date}";*/
	}

	static public function LinkContactsToPortfoliosUsingEmail($account_numbers = null){
	    global $adb;

	    $params = array();
	    $questions = generateQuestionMarks($account_numbers);
	    if($account_numbers != null){
	        $and = " AND p.account_number IN ({$questions}) ";
	        $params[] = $account_numbers;
        }

	    $query = "DROP TABLE IF EXISTS UnlinkedWithEmail";
	    $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE UnlinkedWithEmail
                  SELECT p.*, cf.email_address, cf.tax_id FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  WHERE (contact_link IS NULL OR contact_link < 1)
                  AND cf.email_address != ''
                  AND accountclosed = 0 {$and} ";
        $adb->pquery($query, $params);

        $query = "UPDATE vtiger_portfolioinformation p
                  JOIN UnlinkedWithEmail u ON p.account_number = u.account_number
                  JOIN vtiger_contactdetails cd ON u.email_address = cd.email
                  JOIN vtiger_crmentity e ON e.crmid = cd.contactid
                  SET p.contact_link = cd.contactid
                  WHERE e.deleted = 0";
        $adb->pquery($query, array());
    }

    static public function LinkContactsToPortfoliosUsingFirstLast($account_numbers = null){
        global $adb;

        $params = array();
        $questions = generateQuestionMarks($account_numbers);
        if($account_numbers != null){
            $and = " AND p.account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }

        $query = "DROP TABLE IF EXISTS UnlinkedWithEmail";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE UnlinkedWithName
                  SELECT p.* FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                  WHERE (contact_link IS NULL OR contact_link < 1)
                  AND first_name IS NOT NULL AND last_name IS NOT NULL AND first_name != '' AND last_name != '' AND first_name != '--'
                  AND accountclosed = 0 {$and} ";
        $adb->pquery($query, $params);

        $query = "UPDATE vtiger_portfolioinformation p
                  JOIN UnlinkedWithName u ON p.account_number = u.account_number
                  JOIN vtiger_contactdetails cd ON u.first_name = cd.firstname AND u.last_name = cd.lastname
                  JOIN vtiger_crmentity e ON e.crmid = cd.contactid
                  SET p.contact_link = cd.contactid
                  WHERE e.deleted = 0";
        $adb->pquery($query, array());
    }

    static public function CreateContactFromPortfolio($account_numbers = null){
        global $adb;

        $params = array();
        $questions = generateQuestionMarks($account_numbers);
        if($account_numbers != null){
            $and = " AND p.account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }

        $query = "CALL LINK_PORTFOLIOS_TO_CONTACTS_USING_ADDRESS_AND_NAME()";
        $adb->pquery($query, array());

        $query = "DROP TABLE IF EXISTS ContactsToCreate";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE ContactsToCreate
                    SELECT 0 AS crmid, 'UNKNOWNYET' AS conNumber, p.first_name, p.last_name, cf.address1, cf.address2, cf.address3, cf.address4, 
                    cf.address5, cf.address6, cf.city, cf.state, cf.zip, cf.email_address, cf.tax_id, e.smownerid, p.portfolioinformationid, p.account_number
                    FROM live_omniscient.vtiger_portfolioinformation p
                    JOIN live_omniscient.vtiger_portfolioinformationcf cf USING (portfolioinformationid)
                    JOIN live_omniscient.vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                    WHERE (p.contact_link IS NULL OR p.contact_link < 1)
                    AND p.accountclosed = 0 AND e.deleted = 0
                    AND p.first_name <> '' AND p.last_name <> '' AND p.first_name IS NOT NULL AND p.last_name IS NOT NULL AND first_name != '--' {$and}
                    GROUP BY first_name, last_name ORDER BY last_name";
        $adb->pquery($query, $params);

        $crmid = $adb->getUniqueID("vtiger_crmentity");
        $query = "UPDATE ContactsToCreate SET crmid = ?";
        $adb->pquery($query, array($crmid));
        $query = "UPDATE ContactsToCreate SET conNumber = live_omniscient.IncreaseAndReturnContactNumber()";
        $adb->pquery($query, array());
        $query = "UPDATE ContactsToCreate SET tax_id = CASE WHEN tax_id = '' THEN conNumber WHEN tax_id is null THEN conNumber ELSE tax_id END";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_crmentity (crmid, smcreatorid, smownerid, setype, createdtime, modifiedtime, presence, label)
                    SELECT crmid, 34306 AS smcreatorid, smownerid, 'Contacts' AS setype, NOW() AS createdtime, NOW() AS modifiedtime, 1 AS presence, CONCAT(first_name, last_name) AS label
                    FROM ContactsToCreate";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_contactdetails (contactid, contact_no, firstname, lastname, email)
                  SELECT crmid, conNumber, first_name, last_name, email_address FROM ContactsToCreate";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_contactaddress (contactaddressid, mailingcity, mailingstreet, mailingstate, mailingzip)
                  SELECT crmid, city, address1, state, zip FROM ContactsToCreate";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_contactscf (contactid, ssn, auto_generated)
                  SELECT crmid, tax_id, 1 FROM ContactsToCreate";
        $adb->pquery($query, array());

        $query = "INSERT INTO live_omniscient.vtiger_contactsubdetails (contactsubscriptionid)
                  SELECT crmid FROM ContactsToCreate";
        $adb->pquery($query, array());

        $query = "UPDATE live_omniscient.vtiger_portfolioinformation p
                    JOIN live_omniscient.vtiger_portfolioinformationcf cf ON p.portfolioinformationid = cf.portfolioinformationid
                    JOIN ContactsToCreate c ON p.portfolioinformationid = c.portfolioinformationid
                    SET contact_link = crmid, cf.tax_id = c.tax_id";
        $adb->pquery($query, array());
    }

    static public function UpdatePortfolioInception(){
        global $adb;
        $query = "UPDATE vtiger_portfolioinformation p
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  SET p.inceptiondate = e.createdtime
                  WHERE p.inceptiondate IS NULL OR p.inceptiondate = ''";
        $adb->pquery($query, array());
    }
}