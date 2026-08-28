<?php
// Set up CLI environment
ini_set('max_execution_time', 0);
set_time_limit(0);

chdir(dirname(__FILE__) . '/../');

// Bootstrap Vtiger
require_once 'config.inc.php';
require_once 'include/utils/utils.php';
require_once 'include/utils/CommonUtils.php';
require_once 'includes/Loader.php';
vimport('includes.runtime.EntryPoint');

require_once 'libraries/custodians/Axos/cAxosReconcile.php';

global $current_user;
$current_user = Users::getActiveAdminUser();

// Parse start date from CLI arguments if provided, default to null (earliest available date)
$start_date = null;
$end_date = date('Y-m-d');

foreach ($argv as $arg) {
    if (strpos($arg, '--start=') === 0) {
        $start_date = substr($arg, 8);
    }
    if (strpos($arg, '--end=') === 0) {
        $end_date = substr($arg, 6);
    }
}

echo "Starting Axos Historical Flow Reconciliation...\n";
echo "Date Range: {$start_date} to {$end_date}\n\n";

$res = cAxosReconcile::ReconcileAccountsForDateRange(array(), $start_date, $end_date);

print_r($res);

echo "\nAxos Historical Reconciliation Completed Successfully!\n";
