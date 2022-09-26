<?php
/**
 * Created by PhpStorm.
 * User: ryansandnes
 * Date: 2018-08-29
 * Time: 5:01 PM
 */

class PortfolioInformation_GainLoss_Model extends Vtiger_Module{
    static public function CreateGainLossTables($account_numbers){
        global $adb;
        $questions = generateQuestionMarks($account_numbers);
        $params = array();
        $params[] = $account_numbers;

        $query = "CALL GAIN_LOSS(\"{$questions}\")";
        $adb->pquery($query, $params);
    }


}
