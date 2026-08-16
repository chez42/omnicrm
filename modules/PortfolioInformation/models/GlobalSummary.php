<?php
require_once("libraries/Reporting/ReportCommonFunctions.php");

class PortfolioInformation_GlobalSummary_Model extends Vtiger_Module {

    public function __construct() {
        setlocale(LC_MONETARY, 'en_US');
    }

    static public function UpdatePortfolioDailyIndividualValues(){
        global $adb;
        $query = "INSERT INTO vtiger_portfolio_daily_individual
                  SELECT account_number, NOW(), cash, fixed_income, equities, securities, total_value
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = p.portfolioinformationid
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0
                  ON DUPLICATE KEY UPDATE cash=VALUES(cash)";
        $adb->pquery($query, array());
    }

    static public function UpdatePortfolioDailyValues(){
        global $adb;
        $query = "INSERT INTO vtiger_portfolio_daily_totals
                  SELECT NOW(), SUM(total_value) as total_value, SUM(securities) AS market_value, SUM(cash) as cash_value, SUM(annual_management_fee) as annual_management_fee
                  FROM vtiger_portfolioinformation p
                  JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = p.portfolioinformationid
                  JOIN vtiger_crmentity e ON e.crmid = p.portfolioinformationid
                  WHERE e.deleted = 0;";
        $adb->pquery($query, array());
    }

    static public function GetTotalsFromListViewID($id, &$query = null){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query = strstr($query, 'FROM');
        /*
        $query = " SELECT (SUM(securities) + SUM(cash)) as total_value, SUM(securities) AS market_value, SUM(annual_management_fee) as annual_management_fee, SUM(equities) as equities,
                          SUM(fixed_income) as fixed_income, SUM(cash) as cash_value " . $query;
        */
        $query = " SELECT SUM(total_value) as total_value " . $query;

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values['total_value'] = money_format('%.0n',$v['total_value']);
//                $values['market_value'] = money_format('%.0n',$v['market_value']);
//                $values['cash_value'] = money_format('%.0n',$v['cash_value']);
//                $values['annual_management_fee'] = money_format('%.0n',abs($v['annual_management_fee']));
            }
            return $values;
        }
        return 0;
    }

    static public function GetTrailingFilterPieTotalsFromListViewID($id, &$query = null, $activeonly=0){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query =
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query = strstr($query, 'FROM');
        $query = " SELECT DATE_FORMAT(date, '%b (%Y)') AS date, SUM(vpih.market_value) AS market_value,
                         SUM(vpih.cash_value) AS cash_value, SUM(vpih.fixed_income) AS fixed_income, SUM(vpih.equities) AS equities,
                         SUM(vpih.total_value) AS total_value ";
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        $query .= " AND (vtiger_portfolioinformation.closingdate >= FIRST_DAY(date) OR vtiger_portfolioinformation.closingdate is null) ";
        $query .= " AND vpih.date = LAST_DAY(NOW()-interval 1 month)";
        $query .= " GROUP BY date ";

        if(!$activeonly)
            $query = str_replace("WHERE vtiger_crmentity.deleted=0", "WHERE vtiger_crmentity.deleted IN (0,1)", $query);

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            $values['Equities'] = $adb->query_result($result, 0, 'equities');
            $values['Cash'] = $adb->query_result($result, 0, 'cash_value');
            $values['Fixed Income'] = $adb->query_result($result, 0, 'fixed_income');
        }
        else{
            $values['Equities'] = 0;
            $values['Cash'] = 0;
            $values['Fixed Income'] = 0;
        }

        return $values;
    }

    /**
     * Get admin global summary values
     * @param Vtiger_Request $request
     */
    public function getAdminSummaryValues(Vtiger_Request $request){
        $db = PearDatabase::getInstance();
        $query = "SELECT SUM(total_value) AS total_value, SUM(market_value) AS market_value, SUM(cash_value) AS cash_value, SUM(annual_management_fee) AS annual_management_fee
                  FROM vtiger_portfolioinformation
                  INNER JOIN vtiger_crmentity e ON e.crmid = vtiger_portfolioinformation.portfolioinformationid
                  WHERE e.deleted = 0 AND accountclosed = 0";

        $result = $db->pquery($query, array());
        if (is_object($result))
            foreach($result AS $k => $v){
                $values['total_value'] = money_format('%.0n',$v['total_value']);
                $values['market_value'] = money_format('%.0n',$v['market_value']);
                $values['cash_value'] = money_format('%.0n',$v['cash_value']);
                $values['annual_management_fee'] = money_format('%.0n',abs($v['annual_management_fee']));
            }

        return $values;
    }

    /**
     * Get the total value, market value, cash value, annual management fee
     * @param Vtiger_Request $request
     * @param QueryGenerator $generator
     * @return type
     */
    public function getResultValues(Vtiger_Request $request, QueryGenerator $generator){
        return $this->GetTotalsFromListViewID($request->get('viewname'), $query);
        /*        $db = PearDatabase::getInstance();

				$query = "SELECT SUM(total_value) AS total_value, SUM(market_value) AS market_value, SUM(cash_value) AS cash_value, SUM(annual_management_fee) AS annual_management_fee ";
				$generator->getQuery();
				$query .= $generator->getFromClause();
				$query .= $generator->getWhereClause();

				$result = $db->pquery($query, array());
				if (is_object($result))
					foreach($result AS $k => $v){
						$values['total_value'] = money_format('%.0n',$v['total_value']);
						$values['market_value'] = money_format('%.0n',$v['market_value']);
						$values['cash_value'] = money_format('%.0n',$v['cash_value']);
						$values['annual_management_fee'] = money_format('%.0n',abs($v['annual_management_fee']));
					}

				return $values;*/
    }

    /**
     * Get non admin summary values
     * @global type $current_user
     * @param Vtiger_Request $request
     * @return type
     */
    public function getNonAdminSummaryValues(Vtiger_Request $request){
        global $current_user;
        $moduleName = $request->getModule();
        $db = PearDatabase::getInstance();

        require('user_privileges/sharing_privileges_'.$current_user->id.'.php');

        foreach($PortfolioInformation_share_read_permission['GROUP'] AS $groups => $users){
            foreach($users AS $k => $v)
                $related_ids[] = $v;
            $related_ids[] = $groups;
        }
        $related_ids[] = $current_user->id;//Always at least give the current user ID
        $questions = generateQuestionMarks($related_ids);
        $query = "SELECT SUM(total_value) AS total_value, SUM(market_value) AS market_value, SUM(cash_value) AS cash_value, SUM(annual_management_fee) AS annual_management_fee
                  FROM vtiger_portfolioinformation vpi
                  JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = vpi.portfolioinformationid
                  LEFT JOIN vtiger_crmentity e ON e.crmid = vpi.portfolioinformationid
                  WHERE e.smownerid IN ({$questions})
                  AND e.deleted = 0 AND accountclosed = 0";

        $result = $db->pquery($query, array($related_ids));
        if (is_object($result))
            foreach($result AS $k => $v){
                $values['total_value'] = money_format('%.0n',$v['total_value']);
                $values['market_value'] = money_format('%.0n',$v['market_value']);
                $values['cash_value'] = money_format('%.0n',$v['cash_value']);
                $values['annual_management_fee'] = money_format('%.0n',abs($v['annual_management_fee']));
            }

        return $values;
    }

    public function getCurrentFilterPie(Vtiger_Request $request, QueryGenerator $generator){
        global $current_user;
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        $moduleName = $request->getModule();
        $db = PearDatabase::getInstance();
        $query = "SELECT SUM(cf.equities) AS equities, SUM(cf.fixed_income) AS fixed_income,
                         SUM(cash_value) AS cash ";
        $generator->getQuery();
        $query .= $generator->getFromClause();
        $query .= "JOIN vtiger_portfolioinformationcf cf ON cf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid";
        $query .= $generator->getWhereClause();

        /*
				if(!$currentUserModel->isAdminUser()){
					require('user_privileges/sharing_privileges_'.$current_user->id.'.php');

					foreach($PortfolioInformation_share_read_permission['GROUP'] AS $groups => $users){
						foreach($users AS $k => $v)
							$related_ids[] = $v;
						$related_ids[] = $groups;
					}
					$related_ids[] = $current_user->id;//Always at least give the current user ID
					$questions = generateQuestionMarks($related_ids);
					$where = "AND e.smownerid IN ({$questions})";
				}
				else
					$related_ids = array();
				$query = "SELECT SUM(equities) AS equities, SUM(fixed_income) AS fixed_income,
								 SUM(cash_value) AS cash
						  FROM vtiger_portfolioinformation vpi
						  JOIN vtiger_portfolioinformationcf cf ON vpi.portfolioinformationid = cf.portfolioinformationid
						  JOIN vtiger_crmentity e ON e.crmid = vpi.portfolioinformationid
						  WHERE e.deleted = 0
						  {$where}";*/
        $result = $db->pquery($query, array());
        if($db->num_rows($result) > 0){
            $values['Equities'] = $db->query_result($result, 0, 'equities');
            $values['Cash'] = $db->query_result($result, 0, 'cash');
            $values['Fixed Income'] = $db->query_result($result, 0, 'fixed_income');
        }
        else{
            $values['Equities'] = 0;
            $values['Cash'] = 0;
            $values['Fixed Income'] = 0;
        }
        /*                foreach($result AS $k => $v){
						$values['total_value'] = money_format('%(#10n',$v['total_value']);
						$values['market_value'] = money_format('%(#10n',$v['market_value']);
						$values['cash_value'] = money_format('%(#10n',$v['cash_value']);
						$values['annual_management_fee'] = money_format('%(#10n',$v['annual_management_fee']);
					}
		*/
        return $values;
    }

    static public function GetTrailingAUMFromListViewID($id, &$query = null, $activeonly=0){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query =
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query = strstr($query, 'FROM');
        $query = " SELECT DATE_FORMAT(date, '%b (%Y)') AS date, SUM(vpih.market_value) AS market_value,
                             SUM(vpih.cash_value) AS cash_value, SUM(vpih.fixed_income) AS fixed_income, SUM(vpih.equities) AS equities,
                             SUM(vpih.total_value) AS total_value ";
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        
        
        /* ===== START : Felipe Project Run Changes ===== */
            
        //$query .= " AND (vtiger_portfolioinformation.closingdate >= FIRST_DAY(date) OR vtiger_portfolioinformation.closingdate is null) ";
        
        /* ===== END : Felipe Project Run Changes ===== */
            
        $query .= " AND vpih.date between NOW()-interval 1 year AND LAST_DAY(NOW()-interval 1 month)";
        $query .= " GROUP BY date ";

        if(!$activeonly)
            $query = str_replace("WHERE vtiger_crmentity.deleted=0", "WHERE vtiger_crmentity.deleted IN (0,1)", $query);

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['date'],
                    'market_value' => $v['market_value'],
                    'cash_value' => $v['cash_value'],
                    'fixed_income' => $v['fixed_income'],
                    'equities' => $v['equities'],
                    'value'=>$v['total_value']);
            }
        }
        else{
            $values[] = array('date'=>0,
                              'market_value' => $v['market_value'],
                              'cash_value' => 0,
                              'fixed_income' => 0,
                              'equities' => 0,
                              'value'=>0);
        }

        return $values;
    }

    static public function GetTrailingNewAcccountsFromListViewID($id, &$query = null, $activeonly=0){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query = strstr($query, 'FROM');
        $query = " SELECT COUNT(*) AS new_accounts, SUM(total_value) AS total_value, DATE_FORMAT(inceptiondate, '%b (%Y)') AS date, DATE_FORMAT(inceptiondate, '%Y-%m') AS true_date ";
        $query .= $generator->getFromClause();
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        $query .= " AND inceptiondate BETWEEN NOW()-interval 1 year AND LAST_DAY(NOW()-interval 1 month) ";
        $query .= " GROUP BY true_date ASC ";

        if(!$activeonly)
            $query = str_replace("WHERE vtiger_crmentity.deleted=0", "WHERE vtiger_crmentity.deleted IN (0,1)", $query);

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['date'],
                    'new_accounts'=>$v['new_accounts'],
                    'total_value' => $v['total_value']);
            }
        }
        else{
            $values[] = array('date'=>0,
                'new_accounts'=>0,
                'total_value' => 0);
        }

        return $values;
    }

    static public function GetTrailingClosedAcccountsFromListViewID($id, &$query = null, $activeonly=0){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query = strstr($query, 'FROM');
        $query = " SELECT COUNT(*) AS closed_accounts, SUM(total_value) AS total_value, DATE_FORMAT(closingdate, '%b (%Y)') AS date, DATE_FORMAT(closingdate, '%Y-%m') AS true_date ";
        $query .= $generator->getFromClause();
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        $query .= " AND closingdate BETWEEN NOW()-interval 1 year AND LAST_DAY(NOW()-interval 1 month) ";
        $query .= " GROUP BY true_date ASC ";

        if(!$activeonly)
            $query = str_replace("WHERE vtiger_crmentity.deleted=0", "WHERE vtiger_crmentity.deleted IN (0,1)", $query);

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['date'],
                    'closed_accounts'=>$v['closed_accounts'],
                    'total_value' => $v['total_value']);
            }
        }
        else{
            $values[] = array('date'=>0,
                'closed_accounts'=>0,
                'total_value' => 0);
        }

        return $values;
    }

    static public function GetTrailingAccountsCountFromListViewID($id, &$query = null, $activeonly=0){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query = strstr($query, 'FROM');
        $query = " SELECT COUNT(*) AS total_accounts, DATE_FORMAT(date, '%b (%Y)') AS date, DATE_FORMAT(date, '%Y-%m') AS formatted_date ";
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        $query .= " AND vpih.date between NOW()-interval 1 year AND LAST_DAY(NOW()-interval 1 month)";
        $query .= " GROUP BY date ";

        if(!$activeonly)
            $query = str_replace("WHERE vtiger_crmentity.deleted=0", "WHERE vtiger_crmentity.deleted IN (0,1)", $query);

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                /*                if($v['date'] == 'Jan (2016)') {
									$values[] = array('date' => $v['date'],
										'value' => 11009);
								}else {*/
                $values[] = array('date' => $v['date'],
                    'value' => $v['total_accounts']);
//                }
            }
        }
        else{
            $values[] = array('date'=>0,
                'total_accounts'=>0);
        }

        return $values;
    }

    static public function GetTrailingRevenueFromListViewID($id, &$query = null){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query = " SELECT *, DATE_FORMAT(combined_date,'%b') AS month_name FROM (SELECT month, year, SUM(expense_amount) AS expense_amount,
                  DATE(CONCAT(year, '-', month, '-', '01')) AS combined_date ";
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_fees vpif ON vpif.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        $query .= " GROUP BY month, year) AS t1 ";
        $query .= " WHERE t1.combined_date between CAST(DATE_FORMAT(NOW()-interval 1 year,'%Y-%m-01') as DATE) AND NOW()-interval 1 month ORDER BY year, month ASC";

        #       $query = str_replace("WHERE vtiger_crmentity.deleted=0 AND ", "WHERE ", $query);
#echo $query;exit;
        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['month_name'] . ' (' . $v['year'] . ')',
                    'value'=>abs($v['expense_amount']));
            }
        }
        else{
            $values[] = array('date'=>0,
                'value'=>0);
        }

        return $values;
    }

    static public function GetTrailing12RevenueFromListViewID($id, &$query = null, $start_date = null, $end_date = null){
        global $adb;
        $currentUser = Users_Record_Model::getCurrentUserModel();
        $adb = PearDatabase::getInstance();
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();

        $query = "DROP TABLE IF EXISTS tmp_account_numbers";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tmp_account_numbers 
                  SELECT account_number FROM vtiger_portfolioinformation
                  INNER JOIN vtiger_portfolioinformationcf USING (portfolioinformationid) 
				  INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid ";

        if(!$currentUser->isAdminUser()){
            $query .= Users_Privileges_Model::getNonAdminAccessControlQuery('PortfolioInformation');
        }
        $query .= " WHERE corporate_account = 0";

        $adb->pquery($query, array());

        $query = "DROP TABLE IF EXISTS management_fee_transactions";
        $adb->pquery($query, array());

        if($start_date == null){
            $start_date = GetDatePreviousYearBeginningOfMonth();
        }

        if($end_date == null){
            $end_date = GetDateEndOfLastMonth();
        }

        $query = "CREATE TEMPORARY TABLE management_fee_transactions
                  SELECT * FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  WHERE account_number IN (SELECT account_number FROM tmp_account_numbers) AND transaction_activity = 'Management fee'
                  AND trade_date between ? AND ?";
        $adb->pquery($query, array($start_date, $end_date));

//        $query = "UPDATE management_fee_transactions SET net_amount = CONCAT(operation, net_amount)";
//        $adb->pquery($query, array());

        $query = "SELECT SUM(net_amount) AS value, CONCAT(YEAR(trade_date), '-', MONTH(trade_date)) AS date, YEAR(trade_date) AS year, MONTH(trade_date) AS month 
                  FROM management_fee_transactions
                  GROUP BY YEAR(trade_date), MONTH(trade_date)";
        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $info[] = $x;
            }
        }

        return $info;
    }

    /**
     * Get the asset allocation data for passed in accounts.  This uses the current PositionInformation in Omniscient
     * @param array $account_numbers
     */
    static public function GetAssetAllocationDataForAccounts(array $account_numbers){
        global $adb;
        $and = "";
        $params = array();

        $currentUser = Users_Record_Model::getCurrentUserModel();
        if(!$currentUser->isAdminUser() || sizeof($account_numbers) > 0){
            $questions = generateQuestionMarks($account_numbers);
            $and .= " AND p.account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }

        $query = "SELECT SUM(p.current_value) AS value, cf.base_asset_class AS title, color 
                  FROM vtiger_positioninformation p
                  JOIN vtiger_positioninformationcf cf USING (positioninformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.positioninformationid
                  LEFT JOIN vtiger_chart_colors cc ON cc.title = cf.base_asset_class
                  WHERE base_asset_class IS NOT NULL AND base_asset_class != ''
                  {$and} AND e.deleted = 0
                  GROUP BY base_asset_class";

        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $info[] = $x;
            }
        }

        return $info;
    }

    /**
     * Get the asset allocation data for passed in accounts.  This uses the calculated asset allocation table
     * @param array $account_numbers
     * @return array
     */
    static public function GetAssetAllocationDataForAccountsFromCalculatedTable(array $account_numbers, $as_of_date){
        global $adb;
        $and = "";
        $params = array();

        if(strlen($as_of_date) == 0){
            $as_of_date = date("Y-m-d");
        }
        $currentUser = Users_Record_Model::getCurrentUserModel();
        if(!$currentUser->isAdminUser() || sizeof($account_numbers) > 0){
            $questions = generateQuestionMarks($account_numbers);
            $and .= " AND ach.account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }
        $params[] = $as_of_date;

        $query = "SELECT SUM(value) AS value, ach.base_asset_class AS title, color 
                  FROM vtiger_asset_class_history ach
                  LEFT JOIN vtiger_chart_colors cc ON cc.title = ach.base_asset_class
                  WHERE base_asset_class IS NOT NULL AND base_asset_class != ''
                  {$and} AND as_of_date = ? AND value != 0
                  GROUP BY base_asset_class";

        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $info[] = $x;
            }
        }

        return $info;
    }

    /**
     * Get the asset allocation data for the logged in user.  This uses the calculated asset allocation user table
     * @param array $account_numbers
     * @return array
     */
    static public function GetAssetAllocationDataForAccountsFromCalculatedUserTable(){
        global $adb, $site_URL;
//        https://dev.omnisrv.com/ver4ryan/vt71/index.php?module=PositionInformation&parent=&page=1&view=List&viewname=887&orderby=&sortorder=&app=INVENTORY&search_params=%5B%5B%5B%22base_asset_class%22%2C%22e%22%2C%22Funds%22%5D%5D%5D&tag_params=%5B%5D&nolistcache=0&list_headers=%5B%22description%22%2C%22security_symbol%22%2C%22account_number%22%2C%22current_value%22%2C%22quantity%22%2C%22security_type%22%2C%22base_asset_class%22%2C%22last_update%22%2C%22custodian%22%2C%22custodian_control_number%22%2C%22assigned_user_id%22%5D&tag=
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        $params = array();
        #$params[] = date("Y-m-d");
        $url = $site_URL . "/index.php?module=PositionInformation&view=List";
#        $params[] = $currentUserModel->getId();//THIS IS NEEDED TWICE FOR THE QUERY BELOW
        $params[] = $currentUserModel->getId();

        $query = "SELECT ceiling(value) AS value, base_asset_class, color 
                  FROM vtiger_asset_class_totals_users ach
                  LEFT JOIN vtiger_chart_colors cc ON cc.title = ach.base_asset_class
                  WHERE base_asset_class IS NOT NULL AND base_asset_class != ''
                  AND value != 0 AND user_id = ?
                  GROUP BY base_asset_class ORDER BY value DESC";

        $result = $adb->pquery($query, $params);
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $x['url'] = $url . "&" . "search_params=%5B%5B%5B%22base_asset_class%22%2C%22e%22%2C%22" . $x['base_asset_class'] . "%22%5D%5D%5D";
                $x['urlTarget'] = "_blank";
                $info[] = $x;
            }
        }

        return $info;
    }

    /**
     * Calculate the asset allocation for all accounts and insert them into the vtiger_asset_class_history table
     */
    static public function CalculateAllAccountAssetAllocationValues(){
        global $adb;
        $adb->pquery("TRUNCATE TABLE vtiger_asset_class_totals", array());
        $query = "SELECT p.account_number, SUM(p.current_value) AS value, CASE WHEN base_asset_class IS NULL OR base_asset_class ='' THEN 'Other' ELSE base_asset_class END
                  FROM vtiger_positioninformation p
                  JOIN vtiger_positioninformationcf cf USING (positioninformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.positioninformationid
                  JOIN vtiger_portfolioinformation port ON port.account_number = p.account_number
                  JOIN vtiger_crmentity pe ON pe.crmid = port.portfolioinformationid
                  LEFT JOIN vtiger_chart_colors cc ON cc.title = cf.base_asset_class
                  WHERE e.deleted = 0 AND pe.deleted = 0 AND port.accountclosed = '0' AND cf.last_update >= '2026-01-01'
                  GROUP BY base_asset_class, p.account_number";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            $query = "INSERT INTO vtiger_asset_class_totals VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, array($v));
            }
        }
    }

    /**
     * Calculate the asset allocation for all accounts and insert them into the vtiger_asset_class_history table
     */
    static public function CalculateAllAccountAssetAllocationValuesForAccount(array $account_number){
        if(empty($account_number))
            return null;

        global $adb;
        $questions = generateQuestionMarks($account_number);
        $query = "SELECT account_number, SUM(p.current_value), CASE WHEN base_asset_class IS NULL OR base_asset_class ='' THEN 'Other' ELSE base_asset_class END
                  FROM vtiger_positioninformation p
                  JOIN vtiger_positioninformationcf cf USING (positioninformationid)
                  JOIN vtiger_crmentity e ON e.crmid = p.positioninformationid
                  LEFT JOIN vtiger_chart_colors cc ON cc.title = cf.base_asset_class
                  WHERE e.deleted = 0
                  AND p.account_number IN ({$questions})
                  GROUP BY base_asset_class, account_number";
        $result = $adb->pquery($query, array($account_number));

        if($adb->num_rows($result) > 0){
            $query = "INSERT INTO vtiger_asset_class_totals VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)";
            while($v = $adb->fetchByAssoc($result)){
                $adb->pquery($query, array($v));
            }
        }

    }

    /**
     * Get the monthly balances for passed in accounts.  Also returns the number of accounts used to calculate for each month
     * @param array $account_numbers
     * @return array
     */
    static public function GetTrailingBalancesForAccounts(array $account_numbers){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);

        $query = "SELECT COUNT(account_number) AS num_accounts, SUM(account_value) AS total_value, CONCAT(as_of_date, 'T10:00:01') AS date, YEAR(as_of_date) AS year, MONTH(as_of_date) AS month
                  FROM consolidated_balances 
                  WHERE account_number IN ({$questions})
                      AND as_of_date IN (
                      SELECT MAX(as_of_date) 
                      FROM consolidated_balances 
                      WHERE as_of_date > '1900-01-01'
                      GROUP BY MONTH(as_of_date), YEAR(as_of_date))
                  GROUP BY as_of_date";
        $result = $adb->pquery($query, array($account_numbers));
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $info[] = $x;
            }
        }

        return $info;
    }

    /**
     * Get the monthly balances for passed in accounts.  Also returns the number of accounts used to calculate for each month
     * @param array $account_numbers
     * @return array
     */
    static public function GetTrailingBalancesForAccountsUsingTotalsTable(){
        global $adb;
        $current_user = Users_Record_Model::getCurrentUserModel();

        $query = "SELECT num_accounts, total_balance AS total_value, CONCAT(balance_date, 'T10:00:01') AS date, YEAR(balance_date) AS year, MONTH(balance_date) AS month, DAY(balance_date) AS day
                  FROM daily_user_total_balances 
                  WHERE user_id = ?
                      AND balance_date IN (
                      SELECT MAX(balance_date) 
                      FROM daily_user_total_balances 
                      WHERE balance_date > '1900-01-01'
                      GROUP BY MONTH(balance_date), YEAR(balance_date), DAY(balance_date))
                  GROUP BY balance_date";
        $result = $adb->pquery($query, array($current_user->getId()));
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $info[] = $x;
            }
        }

        return $info;
    }

    /**
     * Get the zoom chart chart data
     * @param $id
     * @param null $query
     * @param null $start_date
     * @param null $end_date
     * @return array
     */
    static public function GetTrailing12ZoomRevenueFromListViewID($id, &$query = null, $start_date = null, $end_date = null){
        global $adb;
        $currentUser = Users_Record_Model::getCurrentUserModel();
        $adb = PearDatabase::getInstance();
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();

        $query = "DROP TABLE IF EXISTS tmp_account_numbers";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tmp_account_numbers 
                  SELECT account_number FROM vtiger_portfolioinformation
                  INNER JOIN vtiger_portfolioinformationcf USING (portfolioinformationid) 
				  INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_portfolioinformation.portfolioinformationid ";

        if(!$currentUser->isAdminUser()){
            $query .= Users_Privileges_Model::getNonAdminAccessControlQuery('PortfolioInformation');
        }
        $query .= " WHERE corporate_account = 0";

        $adb->pquery($query, array());
/*
        $query = "DROP TABLE IF EXISTS management_fee_transactions";
        $adb->pquery($query, array());

        if($start_date == null){
            $start_date = GetDatePreviousYearBeginningOfMonth();
        }

        if($end_date == null){
            $end_date = GetDateEndOfLastMonth();
        }

        $query = "CREATE TEMPORARY TABLE management_fee_transactions
                  SELECT * FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid)
                  WHERE account_number IN (SELECT account_number FROM tmp_account_numbers) AND transaction_activity = 'Management fee'
                  AND trade_date between ? AND ?";
        $adb->pquery($query, array($start_date, $end_date));

//        $query = "UPDATE management_fee_transactions SET net_amount = CONCAT(operation, net_amount)";
//        $adb->pquery($query, array());

        $query = "SELECT SUM(net_amount) AS value, CONCAT(trade_date, 'T10:00:01') AS date, YEAR(trade_date) AS year, MONTH(trade_date) AS month
                  FROM management_fee_transactions
                  GROUP BY YEAR(trade_date), MONTH(trade_date)";
        $result = $adb->pquery($query, array());
*/

        $query = "SELECT SUM(amount) AS value, CONCAT(revenue_date, 'T10:00:01') AS date, YEAR(revenue_date) AS year, MONTH(revenue_date) AS month
                  FROM monthly_management_fees m
                  JOIN tmp_account_numbers t ON t.account_number = m.account_number
                  GROUP BY YEAR(revenue_date), MONTH(revenue_date)";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $info[] = $x;
            }
        }

        return $info;
    }

    public function getFilterRevenue(Vtiger_Request $request, QueryGenerator $generator){
        global $current_user;
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        $moduleName = $request->getModule();
        $db = PearDatabase::getInstance();
        $query = "SELECT *, DATE_FORMAT(combined_date,'%b') AS month_name FROM (SELECT month, year, SUM(expense_amount) AS expense_amount,
                  DATE(CONCAT(year, '-', month, '-', '01')) AS combined_date ";
        $generator->getQuery();
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_fees vpif ON vpif.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= " GROUP BY month, year) AS t1 ";
        $query .= " WHERE t1.combined_date between CAST(DATE_FORMAT(NOW()-interval 1 year,'%Y-%m-01') as DATE) AND NOW()-interval 1 month ORDER BY year, month ASC";
        $result = $db->pquery($query, array());

        if($db->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['month_name'] . ' (' . $v['year'] . ')',
                    'value'=>abs($v['expense_amount']));
            }
        }
        else{
            $values[] = array('date'=>0,
                'value'=>0);
        }

        return $values;
    }

    /**
     * Get the trailing filter pie using the historical data chart rather than current data
     * @global type $current_user
     * @param Vtiger_Request $request
     * @param QueryGenerator $generator
     * @return int
     */
    public function getTrailingFilterPie(Vtiger_Request $request, QueryGenerator $generator){
        global $current_user;
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        $moduleName = $request->getModule();
        $db = PearDatabase::getInstance();

        $query = "SELECT DATE_FORMAT(date, '%b (%Y)') AS date, SUM(vpih.market_value) AS market_value,
                         SUM(vpih.cash_value) AS cash_value, SUM(vpih.fixed_income) AS fixed_income, SUM(vpih.equities) AS equities,
                         SUM(vpih.total_value) AS total_value";
        $generator->getQuery();
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= " AND vpih.date = LAST_DAY(NOW()-interval 1 month)";
        $query .= " GROUP BY date ";

        $result = $db->pquery($query, array());

        if($db->num_rows($result) > 0){
            $values['Equities'] = $db->query_result($result, 0, 'equities');
            $values['Cash'] = $db->query_result($result, 0, 'cash_value');
            $values['Fixed Income'] = $db->query_result($result, 0, 'fixed_income');
        }
        else{
            $values['Equities'] = 0;
            $values['Cash'] = 0;
            $values['Fixed Income'] = 0;
        }

        return $values;
    }

    static public function GetTrailingFilterAssetsFromListViewID($id, &$query = null){
        global $adb;
        $listViewModel = Vtiger_ListView_Model::getInstance("PortfolioInformation", $id);
        $generator = $listViewModel->get('query_generator');
        $query = $generator->getQuery();
        $query =
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query = strstr($query, 'FROM');
        $query = " SELECT DATE_FORMAT(date, '%b (%Y)') AS date, SUM(vpih.market_value) AS market_value,
                             SUM(vpih.cash_value) AS cash_value, SUM(vpih.fixed_income) AS fixed_income, SUM(vpih.equities) AS equities,
                             SUM(vpih.total_value) AS total_value ";
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= Omniscient_Restrictions_Model::GetUserRestrictions('PortfolioInformation');
        $query .= " AND vpih.date between NOW()-interval 1 year AND LAST_DAY(NOW()-interval 1 month)";
        $query .= " GROUP BY date ";

        if(!strpos($query, "vtiger_portfolioinformationcf")){
            $query = str_replace("INNER JOIN vtiger_crmentity", "JOIN vtiger_portfolioinformationcf ON vtiger_portfolioinformationcf.portfolioinformationid = vtiger_portfolioinformation.portfolioinformationid INNER JOIN vtiger_crmentity", $query);
        }

        $result = $adb->pquery($query, array());

        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['date'],
                    'market_value' => $v['market_value'],
                    'cash_value' => $v['cash_value'],
                    'fixed_income' => $v['fixed_income'],
                    'equities' => $v['equities'],
                    'value'=>$v['total_value']);
            }
        }
        else{
            $values[] = array('date'=>0,
                'market_value' => 0,
                'cash_value' => 0,
                'fixed_income' => 0,
                'equities' => 0,
                'value'=>0);
        }

        return $values;
    }

    public function getFilterAssets(Vtiger_Request $request, QueryGenerator $generator){
        global $current_user;
        $currentUserModel = Users_Record_Model::getCurrentUserModel();
        $moduleName = $request->getModule();
        $db = PearDatabase::getInstance();
        $query = "SELECT DATE_FORMAT(date, '%b (%Y)') AS date, SUM(vpih.market_value) AS market_value,
                             SUM(vpih.cash_value) AS cash_value, SUM(vpih.fixed_income) AS fixed_income, SUM(vpih.equities) AS equities,
                             SUM(vpih.total_value) AS total_value";
        $generator->getQuery();
        $query .= $generator->getFromClause();
        $query .= " JOIN vtiger_portfolioinformation_historical vpih ON vpih.account_number = vtiger_portfolioinformation.account_number ";
        $query .= $generator->getWhereClause();
        $query .= " AND vpih.date between NOW()-interval 1 year AND LAST_DAY(NOW()-interval 1 month)";
        $query .= " GROUP BY date ";

        $result = $db->pquery($query, array());

        if($db->num_rows($result) > 0){
            foreach($result AS $k => $v){
                $values[] = array('date'=>$v['date'],
                    'market_value' => $v['market_value'],
                    'cash_value' => $v['cash_value'],
                    'fixed_income' => $v['fixed_income'],
                    'equities' => $v['equities'],
                    'value'=>$v['total_value']);
            }
        }
        else{
            $values[] = array('date'=>0,
                                  'market_value' => $v['market_value'],
                'cash_value' => 0,
                'fixed_income' => 0,
                'equities' => 0,
                'value'=>0);
        }

        return $values;
    }
}
?>
