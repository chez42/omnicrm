<?php
// Set up CLI context
ini_set('max_execution_time', 0);
set_time_limit(0);

chdir(dirname(__FILE__) . '/../');

// Bootstrap Vtiger
require_once 'config.inc.php';
require_once 'include/utils/utils.php';
require_once 'include/utils/CommonUtils.php';
require_once 'includes/Loader.php';
vimport ('includes.runtime.EntryPoint');

global $current_user;
$current_user = Users::getActiveAdminUser();

echo "Starting Axos Data Ingestion...\n";
require_once 'cron/modules/Custodian/AxosPull.service';
echo "\nAxos Data Ingestion Completed!\n";
