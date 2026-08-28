<?php
// Set up CLI environment
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '2048M');

chdir(dirname(__FILE__) . '/../');

// Bootstrap Vtiger
require_once 'config.inc.php';
require_once 'include/utils/utils.php';
require_once 'include/utils/CommonUtils.php';
require_once 'includes/Loader.php';
vimport('includes.runtime.EntryPoint');

global $current_user;
$current_user = Users::getActiveAdminUser();
$adb = PearDatabase::getInstance();

// Parse CLI arguments: --anchor=YYYY-MM-DD, --start=YYYY-MM-DD, --end=YYYY-MM-DD
$anchor_date = '2026-07-13';
$start_date = '2026-01-01';
$end_date = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--anchor=') === 0) {
        $anchor_date = substr($arg, 9);
    }
    if (strpos($arg, '--start=') === 0) {
        $start_date = substr($arg, 8);
    }
    if (strpos($arg, '--end=') === 0) {
        $end_date = substr($arg, 6);
    }
}

if (!$end_date) {
    $end_date = $anchor_date;
}

echo "======================================================\n";
echo "Starting Axos Historical Flow Reversal & Balance Backfill\n";
echo "Anchor Date: {$anchor_date}\n";
echo "Date Range : {$start_date} to {$end_date}\n";
echo "======================================================\n\n";

// 1. Get all active Axos accounts
$res = $adb->pquery("SELECT DISTINCT account_number FROM custodian_omniscient.custodian_portfolios_axos", array());
$all_accounts = array();
while($r = $adb->fetch_array($res)) {
    if(!empty($r[0])) {
        $all_accounts[] = $r[0];
    }
}
echo "Total Axos accounts to process: " . count($all_accounts) . "\n";

// 2. Load all historical prices from custodian_prices_axos
echo "Loading historical security prices...\n";
$res = $adb->pquery("SELECT symbol, price, price_date FROM custodian_omniscient.custodian_prices_axos ORDER BY price_date ASC", array());
$historical_prices = array(); // [symbol][date] => price
$prices = array();            // [symbol] => latest known price <= anchor_date
while($r = $adb->fetchByAssoc($res)) {
    $sym = $r['symbol'];
    $d = $r['price_date'];
    $p = (float)$r['price'];
    $historical_prices[$sym][$d] = $p;
    if($d <= $anchor_date) {
        $prices[$sym] = $p;
    }
}

// 3. Load positions as of anchor date
echo "Loading anchor positions as of {$anchor_date}...\n";

// Verify that anchor_date has positions in custodian_positions_axos
$check_res = $adb->pquery("SELECT COUNT(*) AS c FROM custodian_omniscient.custodian_positions_axos WHERE date = ?", array($anchor_date));
$pos_count = (int)$adb->query_result($check_res, 0, 'c');

if ($pos_count === 0) {
    // Attempt fallback to closest position date <= anchor_date
    $fb_res = $adb->pquery("SELECT MAX(date) AS d FROM custodian_omniscient.custodian_positions_axos WHERE date <= ?", array($anchor_date));
    $fb_date = $adb->query_result($fb_res, 0, 'd');
    if (!empty($fb_date)) {
        echo "No positions found directly on {$anchor_date}. Falling back to closest position snapshot: {$fb_date}\n";
        $anchor_date = $fb_date;
        if (!$end_date || $end_date > $anchor_date) {
            $end_date = $anchor_date;
        }
    } else {
        echo "Error: No position data found on or before {$anchor_date} in custodian_positions_axos.\n";
        exit(1);
    }
}

$res = $adb->pquery("
    SELECT account_number, symbol, description, cusip, rep_id, 
           SUM(units) AS units, SUM(market_value) AS market_value,
           CASE WHEN SUM(units) != 0 THEN SUM(market_value) / SUM(units) ELSE 0 END AS price
    FROM custodian_omniscient.custodian_positions_axos
    WHERE date = ?
    GROUP BY account_number, symbol
", array($anchor_date));

$holdings = array();       // [account][symbol] => units
$sec_info = array();       // [symbol] => array(description, cusip, rep_id, price)
$cash_balances = array();  // [account] => cash amount

while($r = $adb->fetchByAssoc($res)) {
    $acc = $r['account_number'];
    $sym = $r['symbol'];
    $units = (float)$r['units'];
    $mv = (float)$r['market_value'];
    $price = (float)$r['price'];

    if($sym === 'CASHTCA' || empty($sym)) {
        $cash_balances[$acc] = (isset($cash_balances[$acc]) ? $cash_balances[$acc] : 0.0) + $mv;
    } else {
        if($units > 0.0001) {
            if(!isset($holdings[$acc])) {
                $holdings[$acc] = array();
            }
            $holdings[$acc][$sym] = $units;
        }

        if(!isset($sec_info[$sym])) {
            $sec_info[$sym] = array(
                'description' => $r['description'],
                'cusip' => $r['cusip'],
                'rep_id' => $r['rep_id'],
                'price' => $price
            );
        }
        if(!isset($prices[$sym]) && $price > 0.0001) {
            $prices[$sym] = $price;
        }
    }
}

// Ensure every account has a cash entry
foreach($all_accounts as $acc) {
    if(!isset($cash_balances[$acc])) {
        $cash_balances[$acc] = 0.0;
    }
    if(!isset($holdings[$acc])) {
        $holdings[$acc] = array();
    }
}

echo "Anchor state loaded for " . count($holdings) . " funded accounts with positions.\n";

// 4. Load all transactions from $start_date to $anchor_date
echo "Loading transaction ledger from {$start_date} to {$anchor_date}...\n";
$res = $adb->pquery("
    SELECT account_number, symbol, units, amount, unit_cost, activity_code, trade_date, journal_date
    FROM custodian_omniscient.custodian_transactions_axos
    WHERE (trade_date BETWEEN ? AND ? OR (trade_date IS NULL AND journal_date BETWEEN ? AND ?))
    ORDER BY COALESCE(trade_date, journal_date) DESC
", array($start_date, $anchor_date, $start_date, $anchor_date));

$transactions_by_date = array(); // [date][account][] => trn
$trn_count = 0;
while($r = $adb->fetchByAssoc($res)) {
    $d = !empty($r['trade_date']) ? $r['trade_date'] : $r['journal_date'];
    $acc = $r['account_number'];
    $transactions_by_date[$d][$acc][] = $r;
    $trn_count++;
}
echo "Loaded {$trn_count} transactions across " . count($transactions_by_date) . " distinct trade dates.\n";

// 5. Generate reverse date sequence from $anchor_date down to $start_date
$start_ts = strtotime($start_date);
$anchor_ts = strtotime($anchor_date);

$daily_balances_to_insert = array(); // [account, value, cash, sec_val, date]
$baseline_positions_to_insert = array();

echo "Running reverse flow calculations from {$anchor_date} down to {$start_date}...\n";

// First, record the anchor date balance for funded accounts
foreach($all_accounts as $acc) {
    $c = isset($cash_balances[$acc]) ? $cash_balances[$acc] : 0.0;
    $sec_val = 0.0;
    if(isset($holdings[$acc])) {
        foreach($holdings[$acc] as $sym => $units) {
            if($units > 0.0001) {
                $p = isset($prices[$sym]) ? $prices[$sym] : 1.0;
                $sec_val += $units * $p;
            }
        }
    }
    $tot = $c + $sec_val;
    if(abs($tot) > 0.01 || abs($c) > 0.01) {
        $daily_balances_to_insert[] = array(
            'account_number' => $acc,
            'account_value' => round($tot, 2),
            'net_cash' => round($c, 2),
            'securities_value' => round($sec_val, 2),
            'as_of_date' => $anchor_date
        );
    }
}

for($current_ts = $anchor_ts; $current_ts > $start_ts; $current_ts -= 86400) {
    $current_date = date('Y-m-d', $current_ts);
    $prior_date = date('Y-m-d', $current_ts - 86400);

    // If there were transactions on $current_date, apply flow reversal
    if(isset($transactions_by_date[$current_date])) {
        foreach($transactions_by_date[$current_date] as $acc => $trns) {
            foreach($trns as $t) {
                $sym = $t['symbol'];
                $u = (float)$t['units'];
                $amt = (float)$t['amount'];

                // Reverse security share holding
                if(!empty($sym) && $sym !== 'CASHTCA') {
                    if(!isset($holdings[$acc][$sym])) {
                        $holdings[$acc][$sym] = 0.0;
                    }
                    // Subtract units on buy/reinvest (u > 0), add back on sell (u < 0)
                    $holdings[$acc][$sym] -= $u;
                    if(abs($holdings[$acc][$sym]) < 0.0001) {
                        $holdings[$acc][$sym] = 0.0;
                    }
                }

                // Reverse cash balance
                // Buying an asset has amount < 0 (debit), so subtracting a negative adds cash back.
                // Selling an asset has amount > 0 (credit), so subtracting positive decreases cash.
                if(!isset($cash_balances[$acc])) {
                    $cash_balances[$acc] = 0.0;
                }
                $cash_balances[$acc] -= $amt;
            }
        }
    }

    // Now calculate prior_date portfolio valuation
    foreach($all_accounts as $acc) {
        $c = isset($cash_balances[$acc]) ? $cash_balances[$acc] : 0.0;
        $sec_val = 0.0;

        if(isset($holdings[$acc])) {
            foreach($holdings[$acc] as $sym => $units) {
                if($units > 0.0001) {
                    // Look up price
                    $p = 1.0;
                    if(isset($historical_prices[$sym][$prior_date])) {
                        $p = $historical_prices[$sym][$prior_date];
                        $prices[$sym] = $p; // Update price state so non-trading days (weekends/holidays) carry nearest market price
                    } elseif(isset($prices[$sym])) {
                        $p = $prices[$sym];
                    }
                    $sec_val += $units * $p;

                    // If we reached start date, record baseline positions
                    if($prior_date === $start_date) {
                        $info = isset($sec_info[$sym]) ? $sec_info[$sym] : array('description' => $sym, 'cusip' => '', 'rep_id' => '');
                        $baseline_positions_to_insert[] = array(
                            'account_number' => $acc,
                            'symbol' => $sym,
                            'description' => $info['description'],
                            'cusip' => $info['cusip'],
                            'rep_id' => $info['rep_id'],
                            'units' => round($units, 4),
                            'market_value' => round($units * $p, 2),
                            'date' => $start_date,
                            'filename' => '/var/www/sites/opt/storage/custodian/axos/derived_backfill_' . str_replace('-', '', $start_date) . '.POS'
                        );
                    }
                }
            }
        }

        // Also record baseline cash position on start date
        if($prior_date === $start_date && abs($c) > 0.01) {
            $baseline_positions_to_insert[] = array(
                'account_number' => $acc,
                'symbol' => 'CASHTCA',
                'description' => 'CASH RESERVES',
                'cusip' => '000000000',
                'rep_id' => '',
                'units' => round($c, 4),
                'market_value' => round($c, 2),
                'date' => $start_date,
                'filename' => '/var/www/sites/opt/storage/custodian/axos/derived_backfill_' . str_replace('-', '', $start_date) . '.POS'
            );
        }

        $tot = $c + $sec_val;
        // Only record daily balance if account has value or had activity
        if(abs($tot) > 0.01 || abs($c) > 0.01) {
            $daily_balances_to_insert[] = array(
                'account_number' => $acc,
                'account_value' => round($tot, 2),
                'net_cash' => round($c, 2),
                'securities_value' => round($sec_val, 2),
                'as_of_date' => $prior_date
            );
        }
    }
}

echo "Reverse flow calculation completed!\n";
echo "Total daily balance records generated: " . count($daily_balances_to_insert) . "\n";
echo "Total baseline {$start_date} position records: " . count($baseline_positions_to_insert) . "\n\n";

// 6. Batch Insert Daily Balances into custodian_balances_axos
echo "Inserting daily balances into custodian_omniscient.custodian_balances_axos...\n";
$adb->pquery("DELETE FROM custodian_omniscient.custodian_balances_axos WHERE as_of_date BETWEEN ? AND ? AND filename LIKE '%derived%'", array($start_date, $anchor_date));
$chunk_size = 500;
$chunks = array_chunk($daily_balances_to_insert, $chunk_size);
foreach($chunks as $chunk) {
    $values_sql = array();
    $params = array();
    foreach($chunk as $row) {
        $values_sql[] = "(?, ?, ?, ?, ?, 'derived_backfill', NOW())";
        $params[] = $row['account_number'];
        $params[] = $row['account_value'];
        $params[] = $row['net_cash'];
        $params[] = $row['securities_value'];
        $params[] = $row['as_of_date'];
    }
    $query = "INSERT INTO custodian_omniscient.custodian_balances_axos 
              (account_number, account_value, net_cash, securities_value, as_of_date, filename, insert_date)
              VALUES " . implode(', ', $values_sql) . "
              ON DUPLICATE KEY UPDATE account_value = VALUES(account_value), net_cash = VALUES(net_cash), securities_value = VALUES(securities_value)";
    $adb->pquery($query, $params);
}
echo "Daily balances successfully written to custodian_balances_axos!\n";

// 7. Insert Baseline Positions into custodian_positions_axos
if(!empty($baseline_positions_to_insert)) {
    echo "Inserting baseline {$start_date} positions into custodian_positions_axos...\n";
    $adb->pquery("DELETE FROM custodian_omniscient.custodian_positions_axos WHERE date = ? AND filename LIKE '%derived_backfill%'", array($start_date));
    $chunks = array_chunk($baseline_positions_to_insert, $chunk_size);
    foreach($chunks as $chunk) {
        $values_sql = array();
        $params = array();
        foreach($chunk as $row) {
            $values_sql[] = "(?, ?, ?, ?, ?, ?, ?, ?, '', 'derived_backfill', NOW())";
            $params[] = $row['account_number'];
            $params[] = $row['symbol'];
            $params[] = $row['description'];
            $params[] = $row['cusip'];
            $params[] = $row['rep_id'];
            $params[] = $row['units'];
            $params[] = $row['market_value'];
            $params[] = $row['date'];
        }
        $query = "INSERT INTO custodian_omniscient.custodian_positions_axos
                  (account_number, symbol, description, cusip, rep_id, units, market_value, date, model, filename, insert_date)
                  VALUES " . implode(', ', $values_sql) . "
                  ON DUPLICATE KEY UPDATE units = VALUES(units), market_value = VALUES(market_value)";
        $adb->pquery($query, $params);
    }
    echo "Baseline positions written for {$start_date}!\n";
}

// 8. Consolidate Balances into consolidated_balances from $start_date to $end_date
echo "\nConsolidating balances across all Axos accounts from {$start_date} to {$end_date}...\n";
PortfolioInformation_Module_Model::ConsolidatedBalances($all_accounts, $start_date, $end_date);
echo "Balance consolidation completed!\n";

// 9. Recompute Daily Intervals (intervals_daily) from $start_date to $end_date
echo "\nRecalculating daily performance intervals for all funded Axos accounts...\n";
$funded_accounts = array_keys($holdings);
$interval_count = 0;
foreach($funded_accounts as $acc) {
    $intervals = new cIntervals($acc);
    $intervals->CalculateIntervals($start_date, $end_date);
    $interval_count++;
    if($interval_count % 20 === 0) {
        echo "  Processed intervals for {$interval_count}/" . count($funded_accounts) . " accounts...\n";
    }
}
echo "Daily intervals recomputed for all {$interval_count} funded accounts!\n";

// 10. Run Flow Reconciliation Audit
echo "\nRunning Axos Historical Flow Reconciliation Audit from {$start_date} to {$end_date}...\n";
require_once 'libraries/custodians/Axos/cAxosReconcile.php';
$recon_res = cAxosReconcile::ReconcileAccountsForDateRange($all_accounts, $start_date, $end_date);
print_r($recon_res);

echo "\n======================================================\n";
echo "Axos Historical Flow Reversal & Balance Backfill COMPLETE\n";
echo "======================================================\n";
