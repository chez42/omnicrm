<?php
class cAxosSecurities {
    static public function CreateNewSecurities(array $symbols){
        global $adb;
        $questions = generateQuestionMarks($symbols);

        $query = "SELECT f.cusip, CASE WHEN f.symbol IS NULL OR f.symbol = '' THEN f.cusip ELSE f.symbol END AS symbol, f.description,
                         1 AS multiplier, COALESCE(pr.price, 0) AS closing_price, f.asset_type AS security_type, f.asset_class AS aclass, NOW() AS last_update,
                         f.annual_rate AS interest_rate, f.maturity_date, 'Axos' AS origination, f.filename
                  FROM custodian_omniscient.custodian_securities_axos f
                  LEFT JOIN custodian_omniscient.custodian_prices_axos pr ON pr.cusip = f.cusip AND pr.price_date = (SELECT MAX(price_date) FROM custodian_omniscient.custodian_prices_axos WHERE cusip = f.cusip)
                  WHERE f.symbol IN ({$questions})
                  OR f.cusip IN ({$questions})
                  GROUP BY f.symbol
                  ORDER BY last_update DESC";
        $securities_result = $adb->pquery($query, array($symbols, $symbols), true);

        if($adb->num_rows($securities_result) > 0) {
            while($v = $adb->fetchByAssoc($securities_result)) {
                $v['crmid'] = $adb->getUniqueID("vtiger_crmentity");

                if($v['aclass'] == NULL || TRIM($v['aclass']) == '')
                    $v['aclass'] = self::GetAssetClassFromType($v['security_type']);

                $query = "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, createdtime, modifiedtime, label)
                          VALUES (?, 1, 1, 1, 'ModSecurities', NOW(), NOW(), ?)";
                $adb->pquery($query, array($v['crmid'], $v['description']));

                $query = "INSERT INTO vtiger_modsecurities (modsecuritiesid, security_symbol, security_name, description1, security_price, 
                                                            securitytype, last_update, interest_rate, maturity_date, source)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], $v['symbol'], $v['description'], $v['description'], $v['closing_price'], $v['security_type'], $v['last_update'],
                                           $v['interest_rate'], $v['maturity_date'], $v['filename']));

                $query = "INSERT INTO vtiger_modsecuritiescf (modsecuritiesid, cusip, aclass, provider, security_price_adjustment, interest_rate)
                          VALUES (?, ?, ?, ?, ?, ?)";
                $adb->pquery($query, array($v['crmid'], $v['cusip'], $v['aclass'], $v['origination'], $v['multiplier'], $v['interest_rate']));
            }
        }
    }

    static public function UpdateAllSymbolsAtOnce(array $symbols){
        global $adb;
        $questions = generateQuestionMarks($symbols);

        $query = "SELECT f.cusip, CASE WHEN f.symbol IS NULL OR f.symbol = '' THEN f.cusip ELSE f.symbol END AS symbol, f.description,
                         1 AS multiplier, COALESCE(pr.price, 0) AS closing_price, f.asset_type AS security_type, f.asset_class AS aclass, NOW() AS last_update,
                         f.annual_rate AS interest_rate, f.maturity_date, 'Axos' AS origination, f.filename, m.modsecuritiesid
                  FROM custodian_omniscient.custodian_securities_axos f
                  LEFT JOIN custodian_omniscient.custodian_prices_axos pr ON pr.cusip = f.cusip AND pr.price_date = (SELECT MAX(price_date) FROM custodian_omniscient.custodian_prices_axos WHERE cusip = f.cusip)
                  JOIN vtiger_modsecurities m ON m.security_symbol = f.symbol
                  WHERE f.symbol IN ({$questions})
                  OR f.cusip IN ({$questions})
                  GROUP BY f.symbol
                  ORDER BY last_update DESC";
        $result = $adb->pquery($query, array($symbols, $symbols), true);

        if($adb->num_rows($result) > 0) {
            $query = "UPDATE vtiger_modsecurities m
                      JOIN vtiger_modsecuritiescf cf ON m.modsecuritiesid = cf.modsecuritiesid
                      JOIN vtiger_crmentity e ON e.crmid = m.modsecuritiesid
                      SET cf.cusip = ?, m.security_name = ?, m.security_price = ?,
                          cf.security_price_adjustment = ?, 
                          cf.aclass = CASE 
                              WHEN cf.aclass IS NULL OR cf.aclass = '' THEN ? 
                              WHEN cf.aclass = 'Cash' AND m.securitytype IN ('FUND', 'Mutual Fund', 'ETF', 'Common Stock', 'Equity') AND m.security_symbol NOT IN ('CASHTCA', 'SCASH', 'CASH', 'USDOLLAR', '\$CASH') THEN ?
                              ELSE cf.aclass 
                          END,
                          m.interest_rate = ?, m.maturity_date = ?, cf.provider = ?, m.last_update = ?,
                          cf.interest_rate = ?,
                          e.modifiedtime = ?, m.source = ? 
                      WHERE m.modsecuritiesid = ?";
            while($v = $adb->fetchByAssoc($result)) {
                if($v['aclass'] == NULL || TRIM($v['aclass']) == '')
                    $v['aclass'] = self::GetAssetClassFromType($v['security_type']);
                $adb->pquery($query, array($v['cusip'], $v['description'], $v['closing_price'], $v['multiplier'], $v['aclass'], $v['aclass'], $v['interest_rate'],
                                           $v['maturity_date'], $v['origination'], $v['last_update'], $v['interest_rate'], $v['last_update'],
                                           $v['origination'], $v['modsecuritiesid']));
            }
        }
    }

    static public function GetAssetClassFromType($asset_type){
        switch (trim($asset_type)) {
            case 'C':
            case 'CM':
            case 'RHTA':
            case 'ETF':
                return 'Stocks';
            case 'M':
                return 'Funds';
            case 'MM':
                return 'Cash';
            case 'WF':
                return 'Alternatives';
            default:
                return 'Funds';
        }
    }
}
