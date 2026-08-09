<?php
class cAxosPositions {
    static public function GetSymbolListFromCustodian(array $account_numbers, $max_only=true){
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');
        $questions = generateQuestionMarks($account_numbers);
        $query = "SELECT symbol FROM custodian_omniscient.custodian_positions_axos 
                  WHERE account_number IN ({$questions}) 
                  AND filename LIKE ?
                  GROUP BY symbol";
        $result = $adb->pquery($query, array($account_numbers, $instance_path . '%'));
        $symbols = array();
        if($adb->num_rows($result) > 0){
            while($r = $adb->fetchByAssoc($result)){
                $symbols[] = $r['symbol'];
            }
        }
        return $symbols;
    }

    static public function CreateNewPositionsForAccounts(array $account_number)
    {
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');
        $questions = generateQuestionMarks($account_number);

        $query = "SELECT pos.symbol, pos.account_number, pos.description 
                  FROM custodian_omniscient.custodian_positions_axos pos 
                  WHERE pos.account_number IN ({$questions}) 
                  AND pos.filename LIKE ?
                  AND (pos.account_number, pos.symbol) NOT IN (SELECT account_number, security_symbol 
                                                       FROM vtiger_positioninformation 
                                                       WHERE security_symbol != '' 
                                                       AND account_number IN ({$questions})) 
                  AND pos.date = (SELECT MAX(date) FROM custodian_omniscient.custodian_positions_axos WHERE account_number = pos.account_number AND filename LIKE ?) 
                  AND pos.symbol != '' 
                  GROUP BY pos.symbol, pos.account_number";
        $result = $adb->pquery($query, array($account_number, $instance_path . '%', $account_number, $instance_path . '%'), true);

        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");
                $ownerid = PortfolioInformation_Module_Model::GetAccountOwnerFromAccountNumber($v['account_number']);

                $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                          VALUES(?, ?, ?, ?, 'PositionInformation', NOW(), NOW(), ?)";
                $adb->pquery($query, array($v['crmid'], $ownerid, $ownerid, $ownerid, $v['symbol']), true);

                $query = "INSERT INTO vtiger_positioninformation (positioninformationid, security_symbol, description, account_number)
                          VALUES(?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], $v['symbol'], $v['description'], $v['account_number']), true);

                $query = "INSERT INTO vtiger_positioninformationcf (positioninformationid, custodian, custodian_source)
                          VALUES(?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], 'AXOS', 'AXOS'), true);
            }
        }
    }

    static public function UpdateAllCRMPositionsAtOnceForAccounts(array $account_number){
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');
        $questions = generateQuestionMarks($account_number);

        $query = "SELECT pos.symbol, pos.account_number, SUM(pos.units) AS quantity, pos.date, pos.filename, pos.description,
                         CASE WHEN SUM(pos.units) != 0 THEN SUM(pos.market_value) / SUM(pos.units) ELSE 1 END AS closing_price,
                         SUM(pos.market_value) AS current_value,
                         cbu.cost_basis AS cbu_cost_basis, ms.securitytype, mscf.aclass, pinfo.positioninformationid
                  FROM custodian_omniscient.custodian_positions_axos pos
                  JOIN vtiger_positioninformation pinfo ON pinfo.account_number = pos.account_number AND pinfo.security_symbol = pos.symbol
                  LEFT JOIN (
                      SELECT account_number, ticker, file_date, SUM(cost_basis) AS cost_basis 
                      FROM custodian_omniscient.custodian_cbu_axos 
                      WHERE filename LIKE ? 
                      GROUP BY account_number, ticker, file_date
                  ) cbu ON cbu.account_number = pos.account_number AND cbu.ticker = pos.symbol AND cbu.file_date = pos.date
                  LEFT JOIN vtiger_modsecurities ms ON ms.security_symbol = pinfo.security_symbol
                  LEFT JOIN vtiger_modsecuritiescf mscf USING (modsecuritiesid)
                  WHERE pos.date = (SELECT MAX(date) FROM custodian_omniscient.custodian_positions_axos WHERE account_number = pos.account_number AND filename LIKE ?)
                    AND pos.account_number IN ({$questions})
                    AND pos.filename LIKE ?
                  GROUP BY pos.account_number, pos.symbol";
        $result = $adb->pquery($query, array($instance_path . '%', $instance_path . '%', $account_number, $instance_path . '%'));

        if($adb->num_rows($result) > 0){
            $query = "UPDATE vtiger_positioninformation p
                      JOIN vtiger_positioninformationcf pcf ON pcf.positioninformationid = p.positioninformationid 
                      SET p.quantity = ?, 
                          p.current_value = ?, 
                          p.description = ?, 
                          p.last_price = ?, 
                          p.cost_basis = ?,
                          p.unrealized_gain_loss = ? - ?,
                          p.gain_loss_percent = CASE WHEN ? != 0 THEN ((? - ?) / ?) * 100 ELSE 0 END,
                          pcf.last_update = ?, 
                          pcf.custodian_source = ?, 
                          pcf.custodian = ?, 
                          pcf.security_type = ?, 
                          pcf.base_asset_class = ?
                      WHERE p.positioninformationid = ?";
            while($v = $adb->fetchByAssoc($result)){
                $cost_basis = $v['cbu_cost_basis'] ? $v['cbu_cost_basis'] : 0;
                $adb->pquery($query, array(
                    $v['quantity'],
                    $v['current_value'],
                    $v['description'],
                    $v['closing_price'],
                    $cost_basis,
                    $v['current_value'], $cost_basis,
                    $cost_basis, $v['current_value'], $cost_basis, $cost_basis,
                    $v['date'],
                    $v['filename'],
                    'AXOS',
                    $v['securitytype'],
                    $v['aclass'],
                    $v['positioninformationid']
                ));
            }
        }
    }

    static public function GetClosestPositionDate(array $account_numbers, $date){
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');

        if(empty($account_numbers))
            return null;

        $questions = generateQuestionMarks($account_numbers);

        $query = "SELECT MAX(date) AS date
                  FROM custodian_omniscient.custodian_positions_axos 
                  WHERE account_number IN ({$questions})
                  AND date <= ?
                  AND filename LIKE ?";
        $result = $adb->pquery($query, array($account_numbers, $date, $instance_path . '%'));

        if($adb->num_rows($result) > 0)
            return $adb->query_result($result, 0, 'date');

        return null;
    }

    static public function GetPositionDataAsOfDate(array $account_number, $date){
        global $adb, $root_directory;
        $instance_path = rtrim($root_directory, '/');
        $questions = generateQuestionMarks($account_number);
        $date = self::GetClosestPositionDate($account_number, $date);

        $query = "SELECT p.symbol, SUM(p.market_value) AS market_value, aclass, security_sector,
                          cf.cusip, m.security_name, p.account_number, SUM(p.units) AS quantity, 
                          (CASE WHEN SUM(p.units) != 0 THEN SUM(p.market_value) / SUM(p.units) ELSE 1 END) AS price,
                          securitytype
                  FROM custodian_omniscient.custodian_positions_axos p
                  JOIN vtiger_modsecurities m ON m.security_symbol = p.symbol
                  JOIN vtiger_modsecuritiescf cf USING (modsecuritiesid)
                  WHERE p.account_number IN ({$questions})
                  AND p.date = ?
                  AND p.filename LIKE ?
                  GROUP BY p.account_number, p.symbol, aclass, security_sector";
        $result = $adb->pquery($query, array($account_number, $date, $instance_path . '%'));

        if($adb->num_rows($result) > 0){
            $data = array();
            while($v = $adb->fetchByAssoc($result)){
                $data[$v['account_number']][] = $v;
            }
            return $data;
        }

        return null;
    }
}

