<?php
class cAxosReconcile {

    /**
     * Perform daily position unit and cash reconciliation for Axos accounts across a date range.
     * Compares Position(t) against Position(t-1) + TRN(t).
     *
     * @param array $account_numbers Optional account array filter
     * @param string $start_date Start date for comparison (default '2026-07-14')
     * @param string|null $end_date End date for comparison (default current date)
     * @return array Status summary
     */
    static public function ReconcileAccountsForDateRange(array $account_numbers = array(), $start_date = null, $end_date = null) {
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');
        
        if(!$start_date || $start_date === 'earliest') {
            $min_res = $adb->pquery("SELECT MIN(date) AS min_date FROM custodian_omniscient.custodian_positions_axos WHERE date != '0000-00-00'", array());
            if($min_res && $adb->num_rows($min_res) > 0) {
                $start_date = $adb->query_result($min_res, 0, 'min_date');
            }
            if(!$start_date) {
                $start_date = '2026-07-14';
            }
        }

        if(!$end_date) {
            $end_date = date('Y-m-d');
        }

        // 1. Fetch available position dates in chronological order
        $params = array($start_date, $end_date, $instance_path . '%');
        $acct_filter = "";
        if(!empty($account_numbers)) {
            $questions = generateQuestionMarks($account_numbers);
            $acct_filter = " AND account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }

        $query = "SELECT DISTINCT date FROM custodian_omniscient.custodian_positions_axos 
                  WHERE date >= ? AND date <= ? AND filename LIKE ? {$acct_filter}
                  ORDER BY date ASC";
        $result = $adb->pquery($query, $params);
        $dates = array();
        if($result && $adb->num_rows($result) > 0) {
            while($r = $adb->fetchByAssoc($result)) {
                $dates[] = $r['date'];
            }
        }

        if(count($dates) < 2) {
            return array('status' => 'success', 'message' => 'Fewer than 2 position dates available for comparison.', 'processed' => 0);
        }

        $audit_entries = 0;

        // Iterate date pairs (t-1, t)
        for($i = 1; $i < count($dates); $i++) {
            $prev_date = $dates[$i - 1];
            $curr_date = $dates[$i];

            // Get positions map for t-1
            $prev_positions = self::GetPositionsMap($prev_date, $account_numbers);
            // Get positions map for t
            $curr_positions = self::GetPositionsMap($curr_date, $account_numbers);
            // Get transaction net units map for t
            $trn_units = self::GetTransactionUnitsMap($curr_date, $account_numbers);

            // Combine all account-symbol keys across t-1, t, and TRN
            $all_keys = array_unique(array_merge(
                array_keys($prev_positions),
                array_keys($curr_positions),
                array_keys($trn_units)
            ));

            foreach($all_keys as $key) {
                list($acct, $symbol) = explode('|', $key, 2);

                $p_units = isset($prev_positions[$key]) ? (float)$prev_positions[$key] : 0.0;
                $c_units = isset($curr_positions[$key]) ? (float)$curr_positions[$key] : 0.0;
                $t_units = isset($trn_units[$key]) ? (float)$trn_units[$key] : 0.0;

                $expected_units = $p_units + $t_units;
                $discrepancy = round($c_units - $expected_units, 4);

                $status = (abs($discrepancy) < 0.0001) ? 'RECONCILED' : 'OUT_OF_BALANCE';

                // Insert or update reconciliation audit record
                $ins_query = "INSERT INTO custodian_omniscient.axos_reconciliation_audit 
                              (account_number, symbol, as_of_date, prior_date, prior_units, trn_units, expected_units, actual_units, discrepancy, status, insert_date)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE 
                              prior_date = VALUES(prior_date),
                              prior_units = VALUES(prior_units),
                              trn_units = VALUES(trn_units),
                              expected_units = VALUES(expected_units),
                              actual_units = VALUES(actual_units),
                              discrepancy = VALUES(discrepancy),
                              status = VALUES(status),
                              insert_date = NOW()";
                $adb->pquery($ins_query, array(
                    $acct, $symbol, $curr_date, $prev_date,
                    $p_units, $t_units, $expected_units, $c_units,
                    $discrepancy, $status
                ));

                $audit_entries++;
            }
        }

        return array(
            'status' => 'success',
            'processed' => $audit_entries,
            'dates_processed' => count($dates),
            'start_date' => $dates[0],
            'end_date' => $dates[count($dates)-1]
        );
    }

    /**
     * Map positions by account_number|symbol -> units for a given date
     */
    static private function GetPositionsMap($date, array $account_numbers = array()) {
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');
        $params = array($date, $instance_path . '%');
        $acct_filter = "";
        if(!empty($account_numbers)) {
            $questions = generateQuestionMarks($account_numbers);
            $acct_filter = " AND account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }

        $query = "SELECT account_number, CASE WHEN symbol = '' THEN 'CASHTCA' ELSE symbol END AS symbol, SUM(units) AS units
                  FROM custodian_omniscient.custodian_positions_axos
                  WHERE date = ? AND filename LIKE ? {$acct_filter}
                  GROUP BY account_number, symbol";
        $result = $adb->pquery($query, $params);
        $map = array();
        if($result && $adb->num_rows($result) > 0) {
            while($r = $adb->fetchByAssoc($result)) {
                $key = $r['account_number'] . '|' . $r['symbol'];
                $map[$key] = (float)$r['units'];
            }
        }
        return $map;
    }

    /**
     * Map daily transaction activity by account_number|symbol -> net units for a given date
     */
    static private function GetTransactionUnitsMap($date, array $account_numbers = array()) {
        global $adb;
        $params = array($date, $date);
        $acct_filter = "";
        if(!empty($account_numbers)) {
            $questions = generateQuestionMarks($account_numbers);
            $acct_filter = " AND account_number IN ({$questions}) ";
            $params[] = $account_numbers;
        }

        $query = "SELECT account_number, 
                         CASE WHEN symbol = '' THEN 'CASHTCA' ELSE symbol END AS symbol,
                         SUM(CASE WHEN symbol = '' THEN amount ELSE units END) AS trn_units
                  FROM custodian_omniscient.custodian_transactions_axos
                  WHERE (trade_date = ? OR (trade_date IS NULL AND journal_date = ?)) {$acct_filter}
                  GROUP BY account_number, symbol";
        $result = $adb->pquery($query, $params);
        $map = array();
        if($result && $adb->num_rows($result) > 0) {
            while($r = $adb->fetchByAssoc($result)) {
                $key = $r['account_number'] . '|' . $r['symbol'];
                $map[$key] = (float)$r['trn_units'];
            }
        }
        return $map;
    }
}
