<?php

chdir("/var/www/sites/opt/");

include_once "includes/main/WebUI.php";

global $adb;

$indexes = array('GSPC.INDX', 'AGG', 'VFINX.US', 'EEM', 'MSCIEAFE.INDX', 'DVG.INDX', 'SP500BDT.INDX', 'IDCOTCTR.INDX');

// Check if we should perform a full sync or incremental sync (default is 30 days)
$from_date = "";
if (php_sapi_name() == "cli") {
    $options = getopt("", ["full"]);
    if (!isset($options['full'])) {
        $from_date = date("Y-m-d", strtotime("-30 days"));
    }
} else {
    $from_date = date("Y-m-d", strtotime("-30 days"));
}

foreach($indexes as $index){
			
	//$url = "https://eodhistoricaldata.com/api/eod/$index?api_token=59838effd9cac&fmt=json";
	$url = "https://eodhistoricaldata.com/api/eod/$index?api_token=63c9aa8ba1bfa1.20321122&fmt=json";
	if ($from_date) {
		$url .= "&from=" . $from_date;
	}
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
	
	$response = curl_exec($ch);
	
	curl_close($ch);

	$response = json_decode($response, true);

	if($index == 'GSPC.INDX'){
		$index = 'GSPC';
	}

	if($index == 'VFINX.US'){
		$index = 'VFINX';

        }

        if($index == 'MSCIEAFE.INDX'){
                $index = 'MSCI_EAFE';
        }

	if($index == 'DVG.INDX'){
                $index = 'DVG';
        }

        if($index == 'SP500BDT.INDX'){
                $index = 'SP500BDT';
        }

        if($index == 'IDCOTCTR.INDX'){
                $index = 'IDCOTCTR';
        }

	if (is_array($response)) {
		foreach($response as $data){
			$adb->pquery("insert into 360vew_opt.vtiger_prices_index(symbol, date, open, high, low, close, volume,adj_close) 
			values(?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE open=VALUES(open), high=VALUES(high), low=VALUES(low), close=VALUES(close), volume=VALUES(volume), adj_close=VALUES(adj_close)", array($index, $data['date'], $data['open'], $data['high'], 
			$data['low'], $data['close'], $data['volume'], $data['adjusted_close']));
		}
	}
}
