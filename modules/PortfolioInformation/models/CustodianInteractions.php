<?php
#use League\Csv\Reader;
#require 'libraries/csv/vendor/autoload.php';
require 'modules/Omniscient/models/cAuditTypes.php';

class PortfolioInformation_CustodianInteractions_Model extends PortfolioInformation_PCQuery_Model{

	static public function LatestFidelityFiles(&$position_files, &$portfolio_files){
		$directory = "/mnt/lanserver2n/Fidelity/CONCERT Wealth Central/";
		date_default_timezone_set('America/Los_Angeles');
		$file = "fi" . date('mdy', strtotime('last weekday today'));
		date_default_timezone_set('UTC');

		$position_files[] = $directory . $file . ".pos";
		$portfolio_files[] = $directory . $file . ".bal";
	}

	static public function EmptyPortfoliosTable(){
		global $adb;
		$query = "TRUNCATE TABLE vtiger_audit_portfolios";
		$adb->pquery($query, array());
	}

	static public function EmptyResultsTable(){
		global $adb;
		$query = "TRUNCATE TABLE vtiger_audit_results";
		$adb->pquery($query, array());
	}

	static public function LatestSchwabFiles(&$position_files, &$portfolio_files){
		date_default_timezone_set('America/Los_Angeles');
		$position_file = "DD.PC" . date('mdy', strtotime('today -1 Weekday')) . ".SLB.05.Position";
		$portfolio_file = "DD.PC" . date('mdy', strtotime('today -1 Weekday')) . ".SLB.06.Balance";
		date_default_timezone_set('UTC');

		$position_files[] = "/mnt/lanserver2n/Schwab/08472259/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08472259/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08311926/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08311926/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08143312/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08143312/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08052098/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08052098/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08021944/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08021944/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08432336/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08432336/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08103201/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08103201/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08068076/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08068076/" . $portfolio_file;
		$position_files[] = "/mnt/lanserver2n/Schwab/08299385/" . $position_file;
		$portfolio_files[] = "/mnt/lanserver2n/Schwab/08299385/" . $portfolio_file;
	}

	static public function LatestPershingFiles(&$position_files, &$portfolio_files){
		$files = array();
		date_default_timezone_set('America/Los_Angeles');
		$file = "JW" . date('mdy', strtotime('today -1 Weekday')) . ".GCP";
		$f2 = "JW" . date('mdy', strtotime('today -1 Weekday')) . ".GML";
		date_default_timezone_set('UTC');

		$position_files[] = "/mnt/lanserver2n/Pershing/All/" . $file;
		$portfolio_files[] = "/mnt/lanserver2n/Pershing/All/" . $f2;
	}

	static public function GetCSVAccountList(){
		global $adb;
		$query = "SELECT account_number FROM vtiger_audit_results GROUP BY account_number ORDER BY account_number ASC";
		$results = $adb->pquery($query, array());
		if($adb->num_rows($results) > 0){
			$accounts = array();
			foreach($results AS $k => $v){
				$accounts[] = $v['account_number'];
			}
			return $accounts;
		}
		return 0;
	}

	static public function GetBadCSVAccountList(){
		global $adb;
/*		$query = "SELECT *
				  FROM vtiger_audit_results
				  WHERE ( (SUM(vtiger_audit_results.csv_quantity) - vtiger_audit_results.omni_quantity > 1) OR (SUM(vtiger_audit_results.csv_quantity) - vtiger_audit_results.omni_quantity < -1))
				  AND security_type_id is null
				  GROUP BY account_number, security_symbol";*/
$query = "SELECT *
			FROM (SELECT account_number, security_symbol, SUM(csv_quantity) AS csv_quantity, SUM(csv_value) AS csv_value, omni_quantity, omni_value, last_audit,
					filename, security_type_id, custodian_type
				  FROM vtiger_audit_results
				  GROUP BY account_number, security_symbol) AS q1
			WHERE ( q1.csv_quantity - q1.omni_quantity > 1)
			   OR ( q1.csv_quantity - q1.omni_quantity < -1)
				  AND security_type_id is null
			GROUP BY account_number";
		$results = $adb->pquery($query, array());
		if($adb->num_rows($results) > 0){
			$accounts = array();
			foreach($results AS $k => $v){
				$accounts[] = $v['account_number'];
			}
			return $accounts;
		}
		return 0;
	}

	static public function GetBadCSVPortfolioAccountList(){
		global $adb;
		$query = "SELECT * FROM
				   (select ap.account_number, ap.total_value AS csv_total_value, ap.market_value AS csv_market_value, ap.cash_value AS csv_cash_value, p.total_value, p.market_value, p.cash_value, e.deleted
				    FROM vtiger_audit_portfolios ap
				    JOIN vtiger_portfolioinformation p ON replace(ap.account_number, '-', '') = replace(p.account_number, '-', '')
				    JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid) AS t1
				  WHERE (t1.csv_total_value - t1.total_value > 1000 OR t1.csv_total_value - t1.total_value < -1000)
				  AND t1.deleted = 0";
		$results = $adb->pquery($query, array());
		if($adb->num_rows($results) > 0){
			$accounts = array();
			foreach($results AS $k => $v){
				$accounts[] = $v['account_number'];
			}
			return $accounts;
		}
		return 0;
	}

	static public function RemoveCustodianPortfolios($custodian){
		global $adb;
		$query = "DELETE FROM vtiger_audit_portfolios WHERE custodian = ?";
		$adb->pquery($query, array($custodian));
	}

	static public function UpdateSecurityTypeIDs($account_number = null){
		global $adb;
		$query = "UPDATE vtiger_audit_results r,
					  (select security_symbol, security_type_id
						FROM vtiger_securities s
						WHERE security_symbol IN (select security_symbol from vtiger_audit_results)
						AND security_type_id = 11
						GROUP BY security_symbol) AS bla
				  SET r.security_type_id = bla.security_type_id
				  WHERE r.security_symbol = bla.security_symbol";

		$adb->pquery($query, array());

		if(strlen($account_number) > 1)
			$and = " AND replace(p.account_number, '-', '') = replace(?, '-', '') ";

		$query = "UPDATE vtiger_audit_results r
				  JOIN vtiger_positioninformation p ON replace(r.account_number, '-', '') = replace(p.account_number, '-', '') AND r.security_symbol = p.security_symbol
				  JOIN vtiger_crmentity e ON e.crmid = p.positioninformationid
				  SET r.omni_quantity = p.quantity, r.omni_value = p.quantity
				  WHERE e.deleted = 0
				  {$and}
				  AND r.security_type_id is null";

		if(strlen($account_number) > 1)
			$adb->pquery($query, array($account_number));
		else
			$adb->pquery($query, array());
	}

	static public function AuditTDPositions($filename){
		$inputCsv = Reader::createFromPath($filename);

		foreach ($inputCsv as $index => $row) {
			$tmp = new stdClass();
			$tmp->account_number = $row[0];
			$tmp->sSymbol = $row[3];
			$tmp->quantity = $row[4];
			$tmp->value = $row[5];
			$tmp->filename = $filename;
			$accounts[$row[0]][] = $tmp;
		}
		foreach($accounts AS $k => $v){
			$accountList[$k] = new stdClass();
			$accountList[$k]->positionList = $v;
		}

		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToTable($accountList);
	}

	static public function AuditSchwabPositions($filename){
		$inputCsv = Reader::createFromPath($filename, "r");
		try {
			foreach ($inputCsv as $index => $row) {
				$tmp = new stdClass();
				$tmp->account_number = $row[0];
				$tmp->sSymbol = $row[2];
				$tmp->quantity = $row[6];
				$tmp->value = $row[6];
				$tmp->filename = $filename;
				$accounts[$row[0]][] = $tmp;
			}
			foreach ($accounts AS $k => $v) {
				$accountList[$k] = new stdClass();
				$accountList[$k]->positionList = $v;
			}
		}catch (Exception $e){

		}
		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToTable($accountList);
	}

	static public function AuditSchwabPortfolios($filename){
		$inputCsv = Reader::createFromPath($filename, "r");
		try {
			foreach ($inputCsv as $index => $row) {
				$tmp = new stdClass();
				$tmp->account_number = $row[0];
				$tmp->market_value = $row[2];
				$tmp->cash_value = $row[8];
				$tmp->total_value = $row[4];
				$tmp->custodian = "Schwab";
				$tmp->filename = $filename;
				$accounts[$row[0]][] = $tmp;
			}
			foreach ($accounts AS $k => $v) {
				$accountList[$k] = new stdClass();
				$accountList[$k]->portfolioList = $v;
			}
		}catch (Exception $e){

		}
		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToPortfolioTable($accountList);
	}
/*
GCA00000001J7T0010021USD999997    USD999997    4XN3CRCUSD2015121520151215000000000083144000+000000000083144000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+USD99999710     000000000000000000+00000000000000000000000000000000000000000000CONCERT   005   10750001U.S.DOLLARS CURRENCY                                                                                                       NUSD000000000000831440+0000000000+000000010000000000+000000000000831440+USD              X
GCA00000001J7T0012751713448108    713448108    NVF3CRSUSD2015091120150911000000000007500000+000000000007500000+000000000007500000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000367125+000000000000220275+000000000000183563+000000000000000000+PEP      10     000000000000000000+00000000000000000000000000000000000000000000JOHNSON   030   10000001PEPSICO INC COM                                                                                                         C CNUSD000000000007342500+0000000000+000000010000000000+000000000007342500+USDIUS7134481081 X
GCA00001431J7T7001401824348106    824348106    G7N3CRSUSD2015121800000000000000000000500000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000000000+000000000000065273+000000000000039164+000000000000032636+000000000000000000+SHW      10     000000000000000000+00000000000000000000000000000000000000000000TEMPLE    93093010000002SHERWIN WILLIAMS CO COM                                                                                                 C CNUSD000000000001305450+0000000000+000000010000000000+000000000000000000+USDIUS8243481061 X
 */
	/**
	 * Pershing has a nightmare setup
	 * @param $filename
	 */
	static public function AuditPershingPositions($filename){
		$handle = @fopen($filename, "r");
		$row = 1;
		try {
			while ($line = fgets($handle)) {
				if ($row == 1) {
					$row++;
					continue;
				}
				$tmp = new stdClass();
				if (trim(substr($line, 0, 3)) != "EOF") {
					$tmp->account_number = trim(substr($line, 11, 9));//Column 0
					$tmp->custodian_type = trim(substr($line, 20, 1));
					$tmp->quantity = trim(substr($line, 73, 17))/10000;//Column 4
					$tmp->value = trim(substr($line, 73, 17))/10000;//Column 5
					$tmp->sSymbol = trim(substr($line, 434, 8));//Column 3
					$tmp->position_value = trim(substr($line, 664, 17))/100;
					$tmp->filename = $filename;
					$accounts[substr($line, 11, 9)][] = $tmp;
					$row++;
				}
			}
		}catch(Exception $e){

		}

		foreach($accounts AS $k => $v){
			$accountList[$k] = new stdClass();
			$accountList[$k]->positionList = $v;
		}

		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToTable($accountList);
	}
/*
GMA00000001J7T001002 4XN 3CR000000000000000156+000000000000000156+000000000000000000+000000000000000000+000000000000000156-000000000000242350-000000000000000156-000000000000242350+000000000000242506+000000000000000000+000000000000000000+    20151221X
GMA00000037J7T201594 H2R 3CR000000000000205331+000000000000205331+000000000006124230+000000000000000000+000000000005918899+000000000000162330-000000000005918899+000000000006286560+000000000000367659+000000000000000002+000000000000000000+    20151221X
 */
	/**
	 * Pershing has a nightmare setup
	 * @param $filename
	 */
	static public function AuditPershingPortfolios($filename){
		$handle = @fopen($filename, "r");
		$row = 1;
		try {
			while ($line = fgets($handle)) {
				if ($row == 1) {
					$row++;
					continue;
				}
				$tmp = new stdClass();
				if (trim(substr($line, 0, 3)) != "EOF") {
					$indicator = trim(substr($line, 2, 1));//We want indicator A which has total, market and cash value
					if($indicator == "A") {
						$tmp->account_number = trim(substr($line, 11, 9));
						$tmp->market_value = trim(substr($line, 66, 18))/100;
						$tmp->cash_value = trim(substr($line, 123, 18))/100;
						$tmp->total_value = trim(substr($line, 161, 18))/100;
						$tmp->custodian = "Pershing";
						$tmp->filename = $filename;
						$accounts[substr($line, 11, 9)][] = $tmp;
					}
					$row++;
				}
			}
		}catch(Exception $e){

		}

		foreach($accounts AS $k => $v){
			$accountList[$k] = new stdClass();
			$accountList[$k]->portfolioList = $v;
		}

		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToPortfolioTable($accountList);
	}

	static public function AuditTDPortfolios($account_info){
		try {
			foreach ($account_info['model']['getBalancesJson']['balance'] AS $k => $v) {
				$tmp = new stdClass();
				$tmp->account_number = $v['accountNumber'];
				$tmp->total_value = $v['accountValue'];
				$tmp->market_value = $v['netBalance'];
				$tmp->cash_value = $v['cashEquivalent'];
				$tmp->custodian = "TD";
				$tmp->filename = "VEO";
				$accounts[$tmp->account_number][] = $tmp;
			}
		}catch(Exception $e){

		}
		foreach($accounts AS $k => $v){
			$accountList[$k] = new stdClass();
			$accountList[$k]->portfolioList = $v;
		}

		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToPortfolioTable($accountList);
	}

	static public function AuditFidelityPortfolios($filename){
		$handle = @fopen($filename, "r");
		$row = 1;
		try {
			while ($line = fgets($handle)) {
				if ($row == 1) {
					$row++;
					continue;
				}
				$tmp = new stdClass();
				$tmp->account_number = trim(substr($line,0, 9));
				$tmp->cash_value = trim(substr($line, 74, 14));
				$tmp->total_value = trim(substr($line, 18, 14));
				$tmp->market_value = $tmp->total_value - $tmp->cash_value;
				$tmp->custodian = "Fidelity";
				$tmp->filename = $filename;
				$accounts[$tmp->account_number][] = $tmp;
				$row++;
			}
		}catch(Exception $e){

		}

		foreach($accounts AS $k => $v){
			$accountList[$k] = new stdClass();
			$accountList[$k]->portfolioList = $v;
		}

		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToPortfolioTable($accountList);
	}

	/** Fidelity has a different setup than TD
	 * @param $filename
	 */
	static public function AuditFidelityPositions($filename){
		$handle = @fopen($filename, "r");
		while($line = fgets($handle)){
			$tmp = new stdClass();
			$tmp->account_number = trim(substr($line, 0, 14));//Column 0
			$tmp->sSymbol = trim(substr($line, 26, 10));//Column 3
			$tmp->quantity = trim(substr($line, 36, 16));//Column 4
			$tmp->value = trim(substr($line, 52, 16));//Column 5
			$tmp->filename = $filename;
			$accounts[substr($line, 0, 14)][] = $tmp;

/*			$column0 = substr($line, 0, 14);
			$column1 = substr($line, 14, 16);
			$column2 = substr($line, 16, 26);
			$column3 = substr($line, 26, 36);
			$column4 = substr($line, 36, 52);
			$column5 = substr($line, 52, 68);
			$column6 = substr($line, 84, 125);
			$column7 = substr($line, 125, 134);
			$column8 = substr($line, 134, 152);
			$column9 = substr($line, 152, 171);
			$column10 = substr($line, 171, 190);
			$column11 = substr($line, 190, 191);*/
		}

		foreach($accounts AS $k => $v){
			$accountList[$k] = new stdClass();
			$accountList[$k]->positionList = $v;
		}

		$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToTable($accountList);
	}

	static public function AuditAxosPositions($filename){
		$handle = @fopen($filename, "r");
		$accounts = array();
		if($handle) {
			while($line = fgets($handle)){
				$acct = trim(substr($line, 0, 9));
				if(empty($acct) || strpos($acct, 'AAS') === 0) continue;
				$tmp = new stdClass();
				$tmp->account_number = $acct;
				$tmp->sSymbol = trim(substr($line, 24, 10));
				$tmp->quantity = floatval(trim(substr($line, 50, 15)));
				$tmp->value = floatval(trim(substr($line, 34, 15)));
				$tmp->filename = $filename;
				$accounts[$acct][] = $tmp;
			}
			fclose($handle);
		}

		if(!empty($accounts)) {
			foreach($accounts AS $k => $v){
				$accountList[$k] = new stdClass();
				$accountList[$k]->positionList = $v;
			}
			$finishedCompareData = Omniscient_BridgingFunctions_Model::WriteCSVToTable($accountList);
		}
	}

	static public function CompareCSVPortfolios($account_number){
		global $adb;
		$account_number = str_replace('-', '', $account_number);
		$query = "SELECT ap.account_number, ap.total_value AS csv_total_value, ap.market_value AS csv_market_value, ap.cash_value AS csv_cash_value,
	   					 p.total_value AS omni_total_value, p.market_value AS omni_market_value, p.cash_value AS omni_cash_value
				  FROM vtiger_audit_portfolios ap
				  JOIN vtiger_portfolioinformation p ON replace(p.account_number, '-', '') = replace(ap.account_number, '-', '')
				  WHERE ap.account_number = ?";
		$result = $adb->pquery($query, array($account_number));
		if($adb->num_rows($result) > 0 )
			foreach($result AS $k => $v){
				return $v;
			}
		return 0;
	}

	static public function CompareToCSV($account_number){
		global $adb;
		$account_number = str_replace('-', '', $account_number);
		$account = array();
		$query = "SELECT account_number, security_symbol, SUM(csv_quantity) AS csv_quantity, SUM(csv_value) AS csv_value, omni_quantity, omni_value,
 				  last_audit, filename, security_type_id, custodian_type
 				  FROM vtiger_audit_results
 				  WHERE account_number = ?
 				  AND account_number != '0000000'
 				  GROUP BY security_symbol";
		$result = $adb->pquery($query, array($account_number));
		if($adb->num_rows($result) > 0){
			foreach($result AS $k => $v){
				$account[$v['security_symbol']] = $v;
			}
		}

		$query = "SELECT * FROM vtiger_positioninformation p
				  JOIN vtiger_positioninformationcf cf ON p.positioninformationid = cf.positioninformationid
				  JOIN vtiger_crmentity e ON e.crmid = p.positioninformationid
				  WHERE replace(account_number, '-', '') = ?
				  AND e.deleted = 0";
		$result = $adb->pquery($query, array($account_number));
		if($adb->num_rows($result) > 0){
			foreach($result AS $k => $v){
				$account[$v['security_symbol']]['omni_quantity'] = $v['quantity'];
				$account[$v['security_symbol']]['omni_value'] = $v['current_value'];
				$account[$v['security_symbol']]['security_type'] = $v['security_type'];
				$account[$v['security_symbol']]['security_type_id'] = $v['security_type_id'];
			}
		}
		$portfolio_info = self::CompareCSVPortfolios($account_number);
		$result = array("positions" => $account, "portfolios" => $portfolio_info);
		return $result;
	}

	static public function CreateTransactionsFromPositions($account_number, $date){
        $tmp = new CustodianClassMapping(array($account_number));
        $positions = $tmp->positions::GetPositionDataAsOfDate(array($account_number), $date);

        foreach($positions AS $account_number => $positions){
            foreach($positions AS $k => $v){
                if(strtolower($v['aclass']) == 'cash')
                    $type = 1;
                else
                    $type = 0;
                $tmp->transactions::CreateTransactionsInCustodian($account_number, $v['symbol'], $date,
                                                                  $type, $v['market_value'], $v['quantity'], $v['price']);
            }
        }
    }
}