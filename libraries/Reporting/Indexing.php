<?php
/**
 * Created by PhpStorm.
 * User: ryansandnes
 * Date: 2017-10-17
 * Time: 11:01 AM
 */

function getReferenceReturn($symbol,$startDate,$endDate) {
    global $adb;

    $end = $start = array();
    $symbol = html_entity_decode($symbol);

    $result = $adb->pquery("SELECT to_days(date) as to_days, date AS price_date, close AS price from 
                               vtiger_prices_index where date <= ? - INTERVAL 1 DAY
                               AND symbol = ? 
                               order by date DESC limit 1",array($startDate,$symbol));

    if($adb->num_rows($result) <= 0)
        return 0;

    while($v = $adb->fetchByAssoc($result))
        $start = $v;

      $query = "SELECT to_days(date) as to_days, date AS price_date, close AS price 
				  FROM vtiger_prices_index WHERE date <= ?
                  AND symbol = ?
                  order by price_date desc limit 1";
        $end_result = $adb->pquery($query,array($endDate,$symbol));

    if($adb->num_rows($end_result) <= 0)
        return 0;

    while($v = $adb->fetchByAssoc($end_result))
        $end = $v;

    /*Changes 14June,2016
     * $end 0 => to_days, 1 => price_date, 2 => price
     */

    $intervalDays = $end['to_days'] - $start['to_days'];

/**    $guess = $end['price'] / $start['price'] - 1; */
	
	$delta = $end['price'] - $start['price'];
	$guess = $delta / $start['price'];

    if ($intervalDays >= 365)
        $irr = pow((1+$guess),(365/$intervalDays)) - 1;
    else
        $irr = $guess;

    return $irr * 100;
}
