<?php
chdir(dirname(__FILE__));

$parse_query['schwab']['balances'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                         FIELDS TERMINATED BY '|'
                                         LINES STARTING BY 'D2' TERMINATED BY '\\r\\n'";
$parse_query['schwab']['balances'][1] = "UPDATE tmp SET record_type='D2', filename = ?, insert_date = NOW(),
                                         account_number = TRIM(LEADING '0' FROM account_number), account_value = net_mv_plus_cash + margin_balance";


$parse_query['schwab']['positions'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                             FIELDS TERMINATED BY '|' OPTIONALLY ENCLOSED BY '\"'
                             LINES TERMINATED BY '\\r\\n' IGNORE 3 LINES;";
$parse_query['schwab']['positions'][1] = "";


$parse_query['axos']['pos'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    account_number = TRIM(SUBSTRING(@row, 1, 9)),
                                    model          = TRIM(SUBSTRING(@row, 10, 5)),
                                    cusip          = TRIM(SUBSTRING(@row, 15, 10)),
                                    symbol         = TRIM(SUBSTRING(@row, 25, 10)),
                                    market_value   = CAST(TRIM(SUBSTRING(@row, 35, 15)) AS DECIMAL(15,2)),
                                    units          = CAST(TRIM(SUBSTRING(@row, 51, 15)) AS DECIMAL(15,4)),
                                    book_value     = CAST(TRIM(SUBSTRING(@row, 67, 15)) AS DECIMAL(15,2)),
                                    rep_id         = TRIM(SUBSTRING(@row, 83, 5)),
                                    description    = TRIM(SUBSTRING(@row, 88, 41)),
                                    contract_no    = TRIM(SUBSTRING(@row, 129, 26)),
                                    price_date     = STR_TO_DATE(NULLIF(TRIM(SUBSTRING(@row, 155, 8)), ''), '%m/%d/%y'),
                                    composite_model_id = TRIM(SUBSTRING(@row, 163, 4))";
$parse_query['axos']['pos'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW(), date = COALESCE(price_date, STR_TO_DATE(SUBSTRING(filename, -13, 8), '%m%d%Y'))";
$parse_query['axos']['pos'][2] = "DELETE FROM tmp WHERE account_number LIKE 'AAS%' OR account_number = ''";

$parse_query['axos']['trn'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    account_number = TRIM(SUBSTRING(@row, 1, 9)),
                                    model          = TRIM(SUBSTRING(@row, 10, 5)),
                                    cusip          = TRIM(SUBSTRING(@row, 15, 9)),
                                    symbol         = TRIM(SUBSTRING(@row, 25, 10)),
                                    units          = CAST(TRIM(SUBSTRING(@row, 35, 15)) AS DECIMAL(15,4)),
                                    journal_date   = STR_TO_DATE(NULLIF(TRIM(SUBSTRING(@row, 51, 8)), ''), '%m/%d/%y'),
                                    trade_date     = STR_TO_DATE(NULLIF(TRIM(SUBSTRING(@row, 60, 8)), ''), '%m/%d/%y'),
                                    activity_code  = TRIM(SUBSTRING(@row, 69, 5)),
                                    amount         = CAST(TRIM(SUBSTRING(@row, 74, 15)) AS DECIMAL(15,2)),
                                    book_value     = CAST(TRIM(SUBSTRING(@row, 90, 15)) AS DECIMAL(15,2)),
                                    unit_cost      = CAST(TRIM(SUBSTRING(@row, 106, 15)) AS DECIMAL(15,2)),
                                    fee_assess     = CAST(TRIM(SUBSTRING(@row, 122, 15)) AS DECIMAL(15,2)),
                                    journal_id     = CAST(TRIM(SUBSTRING(@row, 138, 10)) AS UNSIGNED),
                                    offset_journal_id = CAST(TRIM(SUBSTRING(@row, 148, 10)) AS UNSIGNED),
                                    trade_fee      = CAST(TRIM(SUBSTRING(@row, 158, 9)) AS DECIMAL(15,2)),
                                    fed_withholding = CAST(TRIM(SUBSTRING(@row, 168, 9)) AS DECIMAL(15,2)),
                                    security_fees  = CAST(TRIM(SUBSTRING(@row, 178, 9)) AS DECIMAL(15,2)),
                                    rep_id         = TRIM(SUBSTRING(@row, 188, 5)),
                                    model_from     = TRIM(SUBSTRING(@row, 193, 4)),
                                    dist_id        = TRIM(SUBSTRING(@row, 198, 6)),
                                    composite_model_id = TRIM(SUBSTRING(@row, 204, 4))";
$parse_query['axos']['trn'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['trn'][2] = "DELETE FROM tmp WHERE account_number LIKE 'AAS%' OR account_number = ''";

$parse_query['axos']['sec'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    symbol       = TRIM(SUBSTRING(@row, 1, 10)),
                                    cusip        = TRIM(SUBSTRING(@row, 11, 10)),
                                    description  = TRIM(SUBSTRING(@row, 21, 41)),
                                    description2 = TRIM(SUBSTRING(@row, 62, 41)),
                                    asset_type   = TRIM(SUBSTRING(@row, 103, 5)),
                                    asset_class  = TRIM(SUBSTRING(@row, 108, 2)),
                                    frequency    = TRIM(SUBSTRING(@row, 110, 2)),
                                    payment_day  = TRIM(SUBSTRING(@row, 112, 3)),
                                    issue_date   = STR_TO_DATE(NULLIF(TRIM(SUBSTRING(@row, 115, 11)), ''), '%m/%d/%Y'),
                                    maturity_date = STR_TO_DATE(NULLIF(TRIM(SUBSTRING(@row, 126, 11)), ''), '%m/%d/%Y'),
                                    annual_rate  = CAST(TRIM(SUBSTRING(@row, 137, 7)) AS DECIMAL(10,5))";
$parse_query['axos']['sec'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['sec'][2] = "DELETE FROM tmp WHERE symbol LIKE 'AAS%' OR symbol = ''";

$parse_query['axos']['pri'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    symbol     = TRIM(SUBSTRING(@row, 1, 10)),
                                    cusip      = TRIM(SUBSTRING(@row, 11, 10)),
                                    price_date = STR_TO_DATE(NULLIF(TRIM(SUBSTRING(@row, 21, 9)), ''), '%m/%d/%y'),
                                    price      = CAST(TRIM(SUBSTRING(@row, 30, 13)) AS DECIMAL(15,4))";
$parse_query['axos']['pri'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['pri'][2] = "DELETE FROM tmp WHERE symbol LIKE 'AAS%' OR symbol = '';";

$parse_query['axos']['reg'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    account_number = TRIM(SUBSTRING(@row, 1, 8)),
                                    name1          = TRIM(SUBSTRING(@row, 9, 40)),
                                    name2          = TRIM(SUBSTRING(@row, 49, 40)),
                                    address1       = TRIM(SUBSTRING(@row, 89, 40)),
                                    address2       = TRIM(SUBSTRING(@row, 129, 40)),
                                    city           = TRIM(SUBSTRING(@row, 169, 20)),
                                    state          = TRIM(SUBSTRING(@row, 189, 2)),
                                    zip            = TRIM(SUBSTRING(@row, 191, 10)),
                                    account_type   = TRIM(SUBSTRING(@row, 201, 5)),
                                    tax_id         = TRIM(SUBSTRING(@row, 206, 11)),
                                    rep_id         = TRIM(SUBSTRING(@row, 217, 6)),
                                    alpha_sort     = TRIM(SUBSTRING(@row, 223, 40)),
                                    alternate_id   = TRIM(SUBSTRING(@row, 263, 20))";
$parse_query['axos']['reg'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['reg'][2] = "DELETE FROM tmp WHERE account_number LIKE 'AAS%' OR account_number = ''";

$parse_query['axos']['cbu'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  FIELDS TERMINATED BY '\\t'
                                  IGNORE 1 LINES
                                  (@account_number, @ticker, @cusip, @option_symbol, @underlying_ticker, @underlying_cusip, @security_type, @file_date, @open_date, @units, @cost_basis, @unit_cost, @amortization_adjustment, @long_tax_lot, @opening_activity_type, @premium_for_option, @covered, @wash_sale, @gift_type, @gift_date, @date_of_death, @average_cost_lot)
                                  SET 
                                    account_number = TRIM(@account_number),
                                    ticker = TRIM(@ticker),
                                    cusip = TRIM(@cusip),
                                    option_symbol = TRIM(@option_symbol),
                                    underlying_ticker = TRIM(@underlying_ticker),
                                    underlying_cusip = TRIM(@underlying_cusip),
                                    security_type = TRIM(@security_type),
                                    file_date = STR_TO_DATE(NULLIF(TRIM(@file_date), ''), '%m/%d/%Y'),
                                    open_date = STR_TO_DATE(NULLIF(TRIM(@open_date), ''), '%m/%d/%Y'),
                                    units = CAST(NULLIF(TRIM(@units), '') AS DECIMAL(15,4)),
                                    cost_basis = CAST(NULLIF(TRIM(@cost_basis), '') AS DECIMAL(15,2)),
                                    unit_cost = CAST(NULLIF(TRIM(@unit_cost), '') AS DECIMAL(15,4)),
                                    amortization_adjustment = CAST(NULLIF(TRIM(@amortization_adjustment), '') AS DECIMAL(15,2)),
                                    long_tax_lot = TRIM(@long_tax_lot),
                                    opening_activity_type = TRIM(@opening_activity_type),
                                    premium_for_option = CAST(NULLIF(TRIM(@premium_for_option), '') AS DECIMAL(15,2)),
                                    covered = TRIM(@covered),
                                    wash_sale = TRIM(@wash_sale),
                                    gift_type = TRIM(@gift_type),
                                    gift_date = STR_TO_DATE(NULLIF(TRIM(@gift_date), ''), '%m/%d/%Y'),
                                    date_of_death = STR_TO_DATE(NULLIF(TRIM(@date_of_death), ''), '%m/%d/%Y'),
                                    average_cost_lot = TRIM(@average_cost_lot)";
$parse_query['axos']['cbu'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['cbu'][2] = "DELETE FROM tmp WHERE account_number LIKE 'AAS%' OR account_number = ''";

$parse_query['axos']['cbl'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  FIELDS TERMINATED BY '\\t'
                                  IGNORE 1 LINES
                                  (@account_number, @ticker, @cusip, @option_symbol, @underlying_ticker, @underlying_cusip, @security_type, @file_date, @open_date, @closed_date, @units, @cost_basis, @unit_cost, @amortization, @long_tax_lot, @closing_activity_type, @proceeds, @premium_for_options, @short_term_realized_gain_loss, @long_term_realized_gain_loss, @covered, @wash_sale, @disallowed_loss, @gift_type, @gift_date, @date_of_death, @average_cost_lot, @tax_lot_disposition_methodology)
                                  SET 
                                    account_number = TRIM(@account_number),
                                    ticker = TRIM(@ticker),
                                    cusip = TRIM(@cusip),
                                    option_symbol = TRIM(@option_symbol),
                                    underlying_ticker = TRIM(@underlying_ticker),
                                    underlying_cusip = TRIM(@underlying_cusip),
                                    security_type = TRIM(@security_type),
                                    file_date = STR_TO_DATE(NULLIF(TRIM(@file_date), ''), '%m/%d/%Y'),
                                    open_date = STR_TO_DATE(NULLIF(TRIM(@open_date), ''), '%m/%d/%Y'),
                                    closed_date = STR_TO_DATE(NULLIF(TRIM(@closed_date), ''), '%m/%d/%Y'),
                                    units = CAST(NULLIF(TRIM(@units), '') AS DECIMAL(15,4)),
                                    cost_basis = CAST(NULLIF(TRIM(@cost_basis), '') AS DECIMAL(15,2)),
                                    unit_cost = CAST(NULLIF(TRIM(@unit_cost), '') AS DECIMAL(15,4)),
                                    amortization = CAST(NULLIF(TRIM(@amortization), '') AS DECIMAL(15,2)),
                                    long_tax_lot = TRIM(@long_tax_lot),
                                    closing_activity_type = TRIM(@closing_activity_type),
                                    proceeds = CAST(NULLIF(TRIM(@proceeds), '') AS DECIMAL(15,2)),
                                    premium_for_options = CAST(NULLIF(TRIM(@premium_for_options), '') AS DECIMAL(15,2)),
                                    short_term_realized_gain_loss = CAST(NULLIF(TRIM(@short_term_realized_gain_loss), '') AS DECIMAL(15,2)),
                                    long_term_realized_gain_loss = CAST(NULLIF(TRIM(@long_term_realized_gain_loss), '') AS DECIMAL(15,2)),
                                    covered = TRIM(@covered),
                                    wash_sale = TRIM(@wash_sale),
                                    disallowed_loss = CAST(NULLIF(TRIM(@disallowed_loss), '') AS DECIMAL(15,2)),
                                    gift_type = TRIM(@gift_type),
                                    gift_date = STR_TO_DATE(NULLIF(TRIM(@gift_date), ''), '%m/%d/%Y'),
                                    date_of_death = STR_TO_DATE(NULLIF(TRIM(@date_of_death), ''), '%m/%d/%Y'),
                                    average_cost_lot = TRIM(@average_cost_lot),
                                    tax_lot_disposition_methodology = TRIM(@tax_lot_disposition_methodology)";
$parse_query['axos']['cbl'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['cbl'][2] = "DELETE FROM tmp WHERE account_number LIKE 'AAS%' OR account_number = ''";

$parse_query['axos']['rep'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    rep_id     = TRIM(SUBSTRING(@row, 1, 15)),
                                    name1      = TRIM(SUBSTRING(@row, 16, 40)),
                                    name2      = TRIM(SUBSTRING(@row, 56, 40)),
                                    address1   = TRIM(SUBSTRING(@row, 96, 40)),
                                    address2   = TRIM(SUBSTRING(@row, 136, 40)),
                                    address3   = TRIM(SUBSTRING(@row, 176, 40)),
                                    city       = TRIM(SUBSTRING(@row, 216, 40)),
                                    state      = TRIM(SUBSTRING(@row, 256, 2)),
                                    zip        = TRIM(SUBSTRING(@row, 258, 10)),
                                    crd_number = TRIM(SUBSTRING(@row, 268, 20)),
                                    firm_id    = TRIM(SUBSTRING(@row, 288, 10)),
                                    alternate_rep_id = TRIM(SUBSTRING(@row, 298, 20)),
                                    phone      = TRIM(SUBSTRING(@row, 318, 10))";
$parse_query['axos']['rep'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['rep'][2] = "DELETE FROM tmp WHERE rep_id LIKE 'AAS%' OR rep_id = ''";

$parse_query['axos']['bkr'][0] = "LOAD DATA LOCAL INFILE ? INTO TABLE tmp
                                  (@row)
                                  SET 
                                    broker_id  = TRIM(SUBSTRING(@row, 1, 15)),
                                    name1      = TRIM(SUBSTRING(@row, 16, 40)),
                                    name2      = TRIM(SUBSTRING(@row, 56, 40)),
                                    address1   = TRIM(SUBSTRING(@row, 96, 40)),
                                    address2   = TRIM(SUBSTRING(@row, 136, 40)),
                                    address3   = TRIM(SUBSTRING(@row, 176, 40)),
                                    city       = TRIM(SUBSTRING(@row, 216, 40)),
                                    state      = TRIM(SUBSTRING(@row, 256, 2)),
                                    zip        = TRIM(SUBSTRING(@row, 258, 10))";
$parse_query['axos']['bkr'][1] = "UPDATE tmp SET filename = ?, insert_date = NOW()";
$parse_query['axos']['bkr'][2] = "DELETE FROM tmp WHERE broker_id LIKE 'AAS%' OR broker_id = ''";


$parse_query['rj']['all'][0] = "LOAD DATA LOCAL INFILE ? 
                                INTO TABLE tmp
                                FIELDS TERMINATED BY '|'
                                LINES TERMINATED BY '\\n'";



$Vtiger_Utils_Log = true;
define('VTIGER6_REL_DIR', '');
include_once('vtlib/Vtiger/Menu.php');
include_once('vtlib/Vtiger/Module.php');


include_once 'includes/main/WebUI.php';

$adb = PearDatabase::getInstance();

$query = "CALL custodian_omniscient.SETUP_FILE_PARSING_TABLE()";
$adb->pquery($query, array());

$query = "SET TRANSACTION ISOLATION LEVEL READ COMMITTED";
$adb->pquery($query, array());

$query = "SELECT id, filename, ftp.skeleton_table, copy_to_table, on_update_fields, ftp.custodian, ftp.table_type
          FROM custodian_omniscient.files_to_parse ftp
          JOIN custodian_omniscient.file_parsing_rules fpr ON ftp.skeleton_table = fpr.skeleton_table
          WHERE finished = 0 AND ftp.skeleton_table IS NOT NULL ORDER BY id ASC";
$to_parse_result = $adb->pquery($query, array());

if($adb->num_rows($to_parse_result) > 0){
    while($v = $adb->fetch_array($to_parse_result)){
        $fields = array();
        $flist = "";
        $list_with_addon = "";
        $custodian = $v['custodian'];
        $table_type = $v['table_type'];
        $skeleton_table = $v['skeleton_table'];
        $mapping = array();

        $query = "DROP TABLE IF EXISTS tmp";
        $result = $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tmp LIKE custodian_omniscient.{$skeleton_table}";
        $result = $adb->pquery($query, array());

        $query = "SELECT addon_field, addon_variable FROM custodian_omniscient.file_parsing_mapping WHERE skeleton_table = ?";
        $result = $adb->pquery($query, array($v['skeleton_table']));
        if($adb->num_rows($result) > 0){
            while($a = $adb->fetch_array($result)){
                $mapping[$a['addon_field']] = $a['addon_variable'];
            }
        }
#        Username: OmniOauth
#Password: Hj#Qzx$c?2GHJ8~?
        foreach($parse_query[$custodian][$table_type] AS $a => $b){//Do the actual parsing into a temp table, and perform any extra setup
            $params = array();
            $field_params = GetParsingParams($skeleton_table, $a);
            foreach($field_params AS $c => $d){
                $params[] = $v[$d];
            }
            $adb->pquery($b, $params);
/*
            if (strpos($v, '?') !== false) {
                $adb->pquery($v, array($v['filename']));//The first query in a series will always have a filename, the rest will have nothing
            }else{
                $adb->pquery($v, array());
            }
*/
        }

        $query = "SHOW COLUMNS FROM tmp";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            while($a = $adb->fetch_array($result)){
                $fields[] = $a['field'];
            }
            // print_r($fields);exit;
            $flist = implode(', ', $fields);//First list is the skeleton list with no addon fields
            foreach($mapping AS $c => $d){
                $fields[] = $c;
            }
            $list_with_addons = implode(', ', $fields);//List with addon now includes our new fields

            $query = "INSERT INTO custodian_omniscient.{$v['copy_to_table']} ({$list_with_addons})
                  SELECT {$flist} ";
            foreach($mapping AS $c => $d){
                if($d == "NOW()"){
                    $pst = new DateTimeZone('America/Los_Angeles');
                    $time = new DateTime('', $pst); // first argument uses strtotime parsing
                    $query .= ", '" . $time->format('Y-m-d H:i:s') . "' ";
                }else {
                    $query .= ", '{$v{$d}}' ";
                }
            }
            $query .= " FROM tmp 
                        ON DUPLICATE KEY UPDATE {$v['on_update_fields']}";

            $result = $adb->pquery($query, array());
            if($result) {
                $query = "SELECT * FROM custodian_omniscient.file_parsing_steps WHERE skeleton_table = 'rj_account_data_skeleton'";
                $steps_result = $adb->pquery($query, array());

                if($adb->num_rows($steps_result) > 0){
                    while ($s = $adb->fetch_array($steps_result)) {
                        switch ($s['todo']) {
                            case "run_query":
                                $variables = explode(',', $s['variables']);
                                $r = $adb->pquery("{$s['command']}", $variables);
                                if(!$r)
                                    echo "No result";
                                break;
                        }
                    }
                }
                $query = "UPDATE custodian_omniscient.files_to_parse SET finished = 1 WHERE id = ?";
                $adb->pquery($query, array($v['id']));
            }else{
                echo $v['filename'] . ' failed to parse<br />';
            }
        }
    }
}

function GetParsingParams($skeleton_table, $step){
    global $adb;
    $params = array();

    $query = "SELECT * FROM custodian_omniscient.file_parsing_params WHERE skeleton_table = ? AND step = ?";
    $params_result = $adb->pquery($query, array($skeleton_table, $step));
    while($a = $adb->fetch_array($params_result)){
        $params[] = $a['field_name'];
    }
    return $params;
}

echo 'done';exit;
