<?php
include_once "libraries/Reporting/ReportCommonFunctions.php";

class cIntervalData{public $accountNumber, $intervalBeginDate, $intervalEndDate, $intervalBeginValue, $intervalEndValue, $netFlowAmount,
                           $netReturnAmount, $expenseAmount, $incomeAmount, $journalAmount, $tradeAmount, $intervalType, $investmentReturn;}

class DayBalances{public $date, $value;}
class DayTransactions{public $transactionType, $transactionActivity, $amount;}

class cIntervals{
    protected $account_number, $transactions, $balances, $custodianAccess, $intervals;
    protected $lastDate, $lastBalance;//Tokens

    public function __construct($account_number){
        $this->account_number = $account_number;
        $this->ResetMemberVariables();
    }

    public function ResetMemberVariables(){
        $this->transactions = array();
        $this->balances = array();
        $this->intervals = array();
        $this->lastDate = null;
        $this->lastBalance = 0;
        $this->custodianAccess = new CustodianClassMapping($this->account_number);
    }

    public function ResetIntervals(array $account_number){
        global $adb;

        $query = "DELETE FROM intervals_daily WHERE AccountNumber = ?";
        $adb->pquery($query, array($account_number));
    }

    protected function SetGroupedTransactionsForDay($date){
        global $adb;

        if(!empty($this->transactions))
            $this->transactions = array();//Reset so we don't double up on transactions

        $query = "SELECT SUM(CONCAT(operation, ABS(net_amount))) AS amount, transaction_type, transaction_activity, trade_date, operation 
                  FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid) 
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  WHERE account_number = ? 
                  AND trade_date = ?
		          AND e.deleted = 0 
		          GROUP BY transaction_type, transaction_activity";
        $result = $adb->pquery($query, array($this->account_number, $date));
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $tmp = new DayTransactions();
                $tmp->amount = $x['amount'];
                $tmp->transactionActivity = $x['transaction_activity'];
                $tmp->transactionType = $x['transaction_type'];
#                $this->transactions[$x['trade_date']][] = $tmp;
#                $this->transactions[$x['trade_date']][$x['transaction_type']][$x['transaction_activity']] = $x;
                $this->transactions[$x['trade_date']][strtolower($x['transaction_type'])][strtolower($x['transaction_activity'])] = $tmp;
            }
        }
    }

    protected function SetAllGroupedTransactionsBetweenDates($sdate, $edate){
        global $adb;

        if(!empty($this->transactions))
            $this->transactions = array();//Reset so we don't double up on transactions

        $query = "SELECT SUM(CONCAT(operation, ABS(net_amount))) AS amount, transaction_type, transaction_activity, trade_date, operation 
                  FROM vtiger_transactions t 
                  JOIN vtiger_transactionscf cf USING (transactionsid) 
                  JOIN vtiger_crmentity e ON e.crmid = t.transactionsid
                  WHERE account_number = ? 
                  AND trade_date between ? AND ?
		          AND e.deleted = 0 
		          GROUP BY trade_date, transaction_type, transaction_activity";
        $result = $adb->pquery($query, array($this->account_number, $sdate, $edate));
        if($adb->num_rows($result) > 0){
            while($x = $adb->fetchByAssoc($result)){
                $tmp = new DayTransactions();
                $tmp->amount = $x['amount'];
                $tmp->transactionActivity = $x['transaction_activity'];
                $tmp->transactionType = $x['transaction_type'];

#                $this->transactions[$x['trade_date']][] = $tmp;
                $this->transactions[$x['trade_date']][strtolower($x['transaction_type'])][strtolower($x['transaction_activity'])] = $tmp;
            }
        }
    }

    protected function SetAllGroupedBalancesBetweenDates($sdate, $edate){
        if(!empty($this->balances))
            $this->balances = array();

        $balances = $this->custodianAccess->portfolios::BalanceBetweenDates(array($this->account_number), $sdate, $edate);
        foreach ($balances[$this->account_number] AS $k => $v) {
            $tmp = new DayBalances();
            $tmp->date = $v['date'];
            $tmp->value = $v['value'];
            $this->balances[] = $tmp;
        }
    }

    public function CalculateDayType($date, $type){
        $total = 0;
        foreach($this->transactions[$date][$type] AS $k => $v){
            $total += $v->amount;
        }

        return $total;
    }

    /**
     * This will take all transactions between sdate and edate adding their amounts
     * If a balance is missing but a transaction exists (IE:  transaction on sunday but no balance), this will catch that
     * @param $date
     * @param $type
     * @return int
     */
    public function CalculateDayTypeBetweenDates($sdate, $edate, $type){
        if($sdate == $edate || GetDatePlusOneDay($sdate) == $edate) {
            return $this->CalculateDaytype($edate, $type);//If the dates are the same, return the amount as of that balance date
        }
        else {//We get here when the last day was a Friday and it is now a monday or tuesday for example.. this will snag any transactions that don't have a balance date
            $sdate = GetDatePlusOneDay($sdate);
            $total = 0;

            $begin = new DateTime($sdate);
            $end = new DateTime($edate);
            $end->setTime(0,0,1);

            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($begin, $interval, $end);

            foreach ($period as $dt) {
                $date = $dt->format("Y-m-d");
                foreach ($this->transactions[$date][$type] AS $k => $v) {
                    $total += $v->amount;
                }
            }
        }
        return $total;
    }

    public function CalculateIntervals($sdate, $edate){
        global $adb;
        $this->ResetIntervals(array($this->account_number));
        $this->SetAllGroupedTransactionsBetweenDates($sdate, $edate);
        $this->SetAllGroupedBalancesBetweenDates($sdate, $edate);

        foreach($this->balances AS $k => $balanceDay){
            if(is_null($this->lastDate)){
                $this->lastDate = GetDateMinusOneDay($balanceDay->date);
            }
            $interval = new cIntervalData();
            $interval->accountNumber = $this->account_number;
            $interval->intervalBeginDate = $balanceDay->date;
            $interval->intervalBeginValue = $this->lastBalance;
            $interval->intervalEndDate = $balanceDay->date;
            $interval->intervalEndValue = $balanceDay->value;
            $interval->expenseAmount = $this->CalculateDayTypeBetweenDates($this->lastDate, $balanceDay->date, 'expense');
            $interval->netFlowAmount = $this->CalculateDayTypeBetweenDates($this->lastDate, $balanceDay->date, 'flow');
            $interval->tradeAmount = $this->CalculateDayTypeBetweenDates($this->lastDate, $balanceDay->date, 'trade');
            $interval->incomeAmount = $this->CalculateDayTypeBetweenDates($this->lastDate, $balanceDay->date, 'income');
            $interval->intervalType = 'Daily';

            if($interval->intervalBeginValue == 0)
                $beginValue = 1;
            else
                $beginValue = $interval->intervalBeginValue;

            $interval->netReturnAmount = $interval->intervalEndValue /
                                         ($interval->intervalBeginValue +
                                         ($interval->netFlowAmount + $interval->expenseAmount));
#echo $interval->intervalEndValue . ' / (' . $interval->intervalBeginValue . ' +(' . $interval->netFlowAmount . ' + '. $interval->expenseAmount . '))' . ' = ' . $interval->netReturnAmount . '<br />';
            /*
                (($interval->intervalEndValue - $interval->intervalBeginValue) -
                    ($interval->netFlowAmount + $interval->expenseAmount)) /
                $beginValue;*/

            if($interval->intervalBeginValue == 0 && ($interval->netFlowAmount + $interval->expenseAmount) == 0)
                $interval->netReturnAmount = 1;

            if($interval->intervalEndValue == 0 && ($interval->netFlowAmount + $interval->expenseAmount) == 0)
                $interval->netReturnAmount = 1;

            $interval->investmentReturn = $interval->intervalEndValue - ($interval->netFlowAmount + $interval->expenseAmount) - $interval->intervalBeginValue;

            if($interval->netReturnAmount == 0 || is_nan($interval->netReturnAmount) || !is_numeric($interval->netReturnAmount)
                                               || is_infinite($interval->netReturnAmount) || $interval->netReturnAmount > 1.5
                                               || $interval->netReturnAmount < -1.5)
                $interval->netReturnAmount = 1;

            if($interval->intervalBeginValue == 0 && $interval->investmentReturn > 0) {
                $interval->netFlowAmount += $interval->investmentReturn;
                $interval->investmentReturn = 0;
                $interval->netReturnAmount = 0;
            }/*
            if($v['begin_value'] == 0 && $v['end_value'] > 0 && $v['investmentreturn'] > 0) {
                $intervals[$k]['net_flow'] += $intervals[$k]['investmentreturn'];//begin_value'] = $v['end_value'] - $v['investmentreturn'];
                $intervals[$k]['investmentreturn'] = 0;
                $intervals[$k]['net_return_percent'] = 0;
                $intervals[$k]['twr'] = 0;
            }*/
/*            echo $interval->intervalEndDate . ' ---- ' .
                $interval->intervalEndValue . ' / (' .
                $interval->intervalBeginValue . ' + (' . $interval->netFlowAmount . ' + ' . $interval->expenseAmount . ')) = ' .
                $interval->netReturnAmount . ' ===== ' . $interval->netReturnAmount . '<br />';
*/
            $this->intervals[] = $interval;
            $this->lastDate = $balanceDay->date;
            $this->lastBalance = $balanceDay->value;
        }
        $writer = "";
        $counter = 0;
        $params = array();
        foreach($this->intervals AS $k => $v){
            if($v->netReturnAmount < 0.5 || $v->netReturnAmount > 1.5) {
                $v->netReturnAmount = 1;
            }
#print_r($v);echo '<br />';
            if($counter >= 100){
                $writer = rtrim($writer, ', ');
                $query = "INSERT INTO intervals_daily (AccountNumber, IntervalBeginDate, IntervalBeginValue, IntervalEndDate, IntervalEndValue, incomeamount, expenseamount, NetFlowAmount, tradeamount, investmentreturn, NetReturnAmount, intervalType, EntryDate)
                          VALUES {$writer}";
                $adb->pquery($query, $params, true);
                $counter = 0;
                $writer = "";
                $params = array();
            }
            $writer .= "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()), ";
            $params[] = array($v->accountNumber, $v->intervalBeginDate, $v->intervalBeginValue, $v->intervalEndDate, $v->intervalEndValue, $v->incomeAmount, $v->expenseAmount, $v->netFlowAmount, $v->tradeAmount ,$v->investmentReturn, $v->netReturnAmount, $v->intervalType);
            $counter++;
        }

        if(!empty($params)) {
            $writer = rtrim($writer, ', ');
            $query = "INSERT INTO intervals_daily (AccountNumber, IntervalBeginDate, IntervalBeginValue, IntervalEndDate, IntervalEndValue, incomeamount, expenseamount, NetFlowAmount, tradeamount, investmentreturn, NetReturnAmount, intervalType, EntryDate)
                      VALUES {$writer}";
            $adb->pquery($query, $params, true);
        }
#        $data = $map->portfolios::GetBeginningBalanceAsOfDate(array($account_number), $date);
    }
}

/*
class cIntervalData{public $accountNumber, $intervalBeginDate, $intervalEndDate, $intervalBeginValue, $intervalEndValue, $netFlowAmount,
                           $netReturnAmount, $expenseAmount, $journalAmount, $tradeAmount, $intervalType, $investmentReturn;}

 */