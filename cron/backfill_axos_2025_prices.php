<?php
/**
 * Backfill Axos 2025 Security Pricing from EOD Historical Data
 */

chdir(__DIR__ . "/..");
require_once "config.inc.php";
require_once "libraries/EODHistoricalData/EODGuzzle.php";

$mysqli = new mysqli($dbconfig["db_server"], $dbconfig["db_username"], $dbconfig["db_password"], $dbconfig["db_name"]);
if ($mysqli->connect_error) {
    die("Database Connection Error: " . $mysqli->connect_error . "\n");
}

echo "========================================================\n";
echo "Starting Axos 2025 Security Pricing Backfill via EOD API\n";
echo "========================================================\n";

// 1. Get list of all distinct 2025 trading dates
$trading_dates = [];
$res = $mysqli->query("SELECT DISTINCT date FROM vtiger_prices WHERE symbol IN ('SPY', 'AAPL') AND date BETWEEN '2025-01-01' AND '2025-12-31' ORDER BY date ASC");
while ($r = $res->fetch_assoc()) {
    $trading_dates[] = $r['date'];
}
echo "Found " . count($trading_dates) . " trading dates for 2025.\n";

// 2. Get all distinct Axos securities
$query = "SELECT DISTINCT s.symbol, s.cusip, s.asset_type, s.description 
          FROM custodian_omniscient.custodian_securities_axos s
          UNION
          SELECT DISTINCT p.symbol, p.cusip, '' as asset_type, p.description
          FROM custodian_omniscient.custodian_positions_axos p
          WHERE p.symbol IS NOT NULL AND p.symbol != ''";

$res = $mysqli->query($query);
$securities = [];
while ($r = $res->fetch_assoc()) {
    $symbol = trim($r['symbol']);
    $cusip = trim($r['cusip']);
    if (empty($symbol) && !empty($cusip)) {
        $symbol = $cusip;
    }
    if (!empty($symbol)) {
        $securities[$symbol] = [
            'symbol' => $symbol,
            'cusip' => $cusip,
            'asset_type' => $r['asset_type'],
            'description' => $r['description']
        ];
    }
}

echo "Total unique Axos securities to process: " . count($securities) . "\n\n";

$guz = new cEodGuzzle();

// Prepared statements for batch inserts
$stmt_vtiger = $mysqli->prepare("INSERT INTO vtiger_prices (symbol, date, open, high, low, close, adjusted_close, volume) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE open=VALUES(open), high=VALUES(high), low=VALUES(low), close=VALUES(close), adjusted_close=VALUES(adjusted_close), volume=VALUES(volume)");

$stmt_custodian = $mysqli->prepare("INSERT INTO custodian_omniscient.custodian_prices_axos (symbol, cusip, price_date, price, filename, insert_date) 
                                   VALUES (?, ?, ?, ?, 'EOD_BACKFILL_2025', NOW()) 
                                   ON DUPLICATE KEY UPDATE price=VALUES(price), insert_date=NOW()");

$count_success = 0;
$count_failed = 0;
$count_cash = 0;
$total_vtiger_rows = 0;
$total_custodian_rows = 0;
$failed_symbols = [];

$index = 0;
$total = count($securities);

foreach ($securities as $sym => $sec) {
    $index++;
    $symbol = $sec['symbol'];
    $cusip = $sec['cusip'];
    $desc = strtoupper($sec['description']);

    // Check if Cash / Money Market fixed at 1.00
    if ($symbol === 'CASH' || $symbol === 'CASHPROD8' || strpos($symbol, '$CASH') !== false || $symbol === 'SCASH' || $symbol === 'USDOLLAR' || $desc === 'CASH') {
        $count_cash++;
        $mysqli->begin_transaction();
        foreach ($trading_dates as $t_date) {
            $one = 1.0000;
            $zero = 0;
            $stmt_vtiger->bind_param("ssdddddi", $symbol, $t_date, $one, $one, $one, $one, $one, $zero);
            $stmt_vtiger->execute();
            $total_vtiger_rows++;

            $stmt_custodian->bind_param("sssd", $symbol, $cusip, $t_date, $one);
            $stmt_custodian->execute();
            $total_custodian_rows++;
        }
        $mysqli->commit();
        echo "[$index/$total] $symbol: Fixed cash asset ($count_cash) - generated 250 price points.\n";
        continue;
    }

    // Determine list of symbol candidates to try with EOD API
    $candidates = [$symbol];
    if (strpos($symbol, '/') !== false) {
        $candidates[] = str_replace('/', '-', $symbol);
        $candidates[] = str_replace('/', '.', $symbol);
        $candidates[] = str_replace('/', '', $symbol);
    }
    if (strpos($symbol, '.') !== false) {
        $candidates[] = str_replace('.', '-', $symbol);
    }
    if (strpos($symbol, '-') !== false) {
        $candidates[] = str_replace('-', '.', $symbol);
    }

    $prices = null;
    $used_candidate = null;

    foreach ($candidates as $cand) {
        try {
            $raw_json = $guz->getSymbolPricing($cand, "2025-01-01", "2025-12-31");
            if (!empty($raw_json)) {
                $decoded = json_decode($raw_json);
                if (is_array($decoded) && !empty($decoded)) {
                    $prices = $decoded;
                    $used_candidate = $cand;
                    break;
                }
            }
        } catch (Exception $e) {
            // ignore and try next
        }
    }

    if (is_array($prices) && !empty($prices)) {
        $count_success++;
        $rows_for_symbol = count($prices);
        $mysqli->begin_transaction();
        foreach ($prices as $p) {
            if (!isset($p->date) || !isset($p->close)) continue;
            
            $p_date = $p->date;
            $p_open = isset($p->open) ? floatval($p->open) : floatval($p->close);
            $p_high = isset($p->high) ? floatval($p->high) : floatval($p->close);
            $p_low = isset($p->low) ? floatval($p->low) : floatval($p->close);
            $p_close = floatval($p->close);
            $p_adj = isset($p->adjusted_close) ? floatval($p->adjusted_close) : $p_close;
            $p_vol = isset($p->volume) ? floatval($p->volume) : 0;

            $stmt_vtiger->bind_param("ssdddddi", $symbol, $p_date, $p_open, $p_high, $p_low, $p_close, $p_adj, $p_vol);
            $stmt_vtiger->execute();
            $total_vtiger_rows++;

            $stmt_custodian->bind_param("sssd", $symbol, $cusip, $p_date, $p_close);
            $stmt_custodian->execute();
            $total_custodian_rows++;
        }
        $mysqli->commit();
        $cand_note = ($used_candidate !== $symbol) ? " (via $used_candidate)" : "";
        echo "[$index/$total] $symbol$cand_note: OK ($rows_for_symbol days inserted)\n";
    } else {
        $count_failed++;
        $failed_symbols[] = $symbol . " (" . $sec['description'] . ")";
        echo "[$index/$total] $symbol: NO DATA found in EOD\n";
    }

    // Small delay
    usleep(30000); // 30ms
}

echo "\n========================================================\n";
echo "BACKFILL SUMMARY\n";
echo "========================================================\n";
echo "Total Securities Processed: $total\n";
echo "Successfully Pulled & Updated from EOD: $count_success\n";
echo "Cash / Fixed Value Assets Handled: $count_cash\n";
echo "Total Missing / No Data: $count_failed\n";
echo "Total Rows Inserted into vtiger_prices: $total_vtiger_rows\n";
echo "Total Rows Inserted into custodian_prices_axos: $total_custodian_rows\n";

if (!empty($failed_symbols)) {
    echo "\nSymbols without EOD data (" . count($failed_symbols) . "):\n";
    foreach (array_slice($failed_symbols, 0, 30) as $f) {
        echo " - $f\n";
    }
    if (count($failed_symbols) > 30) {
        echo " ... and " . (count($failed_symbols) - 30) . " more.\n";
    }
}
echo "========================================================\n";
