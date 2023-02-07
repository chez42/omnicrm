<?php
/**
 * Created by PhpStorm.
 * User: rsandnes
 * Date: 2016-03-29
 * Time: 1:48 PM
 */
spl_autoload_register(function ($className) {
    if (file_exists("libraries/EODHistoricalData/$className.php")) {
        include_once "libraries/EODHistoricalData/$className.php";
    }
});


class ModSecurities_ConvertCustodian_Model extends Vtiger_Module_Model{
	static $tenant = "custodian_omniscient";

	static public function GetSecurityTypeMapping($custodian){
		global $adb;
		switch($custodian){
			case "td":
				$ex = "_td";
				break;
		}
		$query = "SELECT code, asset_class, type FROM vtiger_security_mapping{$ex}";
		$result = $adb->pquery($query, array());
		if($adb->num_rows($result) > 0){
			foreach($result AS $k => $v){
				$tmp[$v['code']] = $v;
			}
			return $tmp;
		}
		return 0;
	}

	/**
	 * @param $symbol
	 * @param $start_date
	 * @param $end_date
	 * @return mixed
	 * Get the history for the given Security.  If symbol is entered as S&P 500, it automatically converts to query Yahoo for ^GSPC.  Note the returned data will return as ^GSPC
	 */
	static public function GetIndexHistory($symbol, $start_date, $end_date, $exchange = "INDX"){
		switch($symbol){
			case "S&P 500":
				$tmp_symbol = "GSPC";
				$exchange = "INDX";
				break;
            case "AGG":
                $tmp_symbol = "AGG";
                $exchange = "US";
                break;
            case "EFA":
                $tmp_symbol = "EFA";
                $exchange = "US";
                break;
            case "MSCI_EAFE":
                $tmp_symbol = "990100";
                $exchange = "INDX";
                break;
            default:
				$tmp_symbol = $symbol;
				$exchange = "INDX";
				break;
		}
        $eod = new EODHistoricalData('json', $exchange, '63c9aa8ba1bfa1.20321122');
        return $eod->getHistoricalData($tmp_symbol, $start_date, $end_date);

//		return PortfolioInformation_yql_Model::GetPricingHistory($tmp_symbol, $start_date, $end_date);
	}

	/**
	 * @param $symbol
	 * @param $start_date
	 * @param $end_date
	 * Updates the index price table for the given symbol using Yahoo returned date
	 */
	static public function UpdateIndexYahoo($symbol, $start_date, $end_date){
		$data = json_decode(self::GetIndexHistory($symbol, $start_date, $end_date));
		if(!$data->{'query'}->{'results'}->{'quote'})//There was an error retrieving for some reason, try again.  Seems every second attempt fails so this takes care of that
			$data = json_decode(self::GetIndexHistory($symbol, $start_date, $end_date));

		if($data->{'query'}->{'results'}->{'quote'}) {
			foreach ($data->{'query'}->{'results'}->{'quote'} AS $k => $v) {
				ModSecurities_Module_Model::InsertIndexPrice($symbol, $v->Date, $v->Open, $v->High, $v->Low, $v->Close, $v->Volume, $v->Adj_Close);
			};
		}
	}

    /**
     * @param $symbol
     * @param $start_date
     * @param $end_date
     * Updates the index price table for the given symbol using Yahoo returned date
     */
    static public function UpdateIndexEOD($symbol, $start_date, $end_date){
        $data = json_decode(self::GetIndexHistory($symbol, $start_date, $end_date));
        foreach ($data AS $k => $v) {
            ModSecurities_Module_Model::InsertIndexPrice($symbol, $v->date, $v->open, $v->high, $v->low, $v->close, $v->volume, $v->adjusted_close);
        };
    }

    static public function UpdateIndexOmniscient($symbol, $start_date, $end_date){
        global $adb;
        $ids = array();
        require_once("include/utils/cron/cPricingAccess.php");
        require_once("include/utils/cron/cSecuritiesAccess.php");
        $pricing = new cPricingAccess();
        $pricing->PullSecurityPrice($symbol);
        $symbol_info = cSecuritiesAccess::GetSecurityIDsBySymbol($symbol);
        if(is_array($symbol_info)){
            foreach($symbol_info AS $k => $v){
                $ids[] = $v['security_id'];
            }
            $questions = generateQuestionMarks($ids);
            $query = "UPDATE vtiger_pc_security_prices SET symbol = ? WHERE security_id IN ({$questions})";
            $adb->pquery($query, array($symbol, $ids));
        }
        $query = "INSERT INTO vtiger_prices_index(symbol, date, close) 
                   SELECT symbol, price_date, price FROM vtiger_pc_security_prices WHERE symbol = ? AND price_date BETWEEN ? AND ?
                  ON DUPLICATE KEY UPDATE close=VALUES(close)";
#        echo $query . "<br />" . $symbol . "<br />" . $start_date . "<br />" . $end_date;exit;
        $adb->pquery($query, array($symbol, $start_date, $end_date));
    }

    /**
     * Update the security price from data obtained by the EOD API
     * @param $symbol
     * @param $start
     * @param $end
     */
    static public function UpdateSecurityPriceFromEOD($symbol, $start, $end){
        global $adb;
        
		$eod = new EODHistoricalData('json', "US", '63c9aa8ba1bfa1.20321122');
        
		$result = $eod->getSymbolPricing($symbol, $start, $end);
        
		$data = json_decode($result);
        
		$query = "INSERT INTO vtiger_prices (symbol, date, open, high, low, close, adjusted_close, volume) 
		VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE open=VALUES(open), high=VALUES(high), low=VALUES(low), close=VALUES(close), 
		adjusted_close=VALUES(adjusted_close), volume=VALUES(volume)";

        if(sizeof($data) > 0){
            foreach($data AS $k => $v){
                $params = array();
                $params[] = $symbol;
                $params[] = $v->date;
                $params[] = $v->open;
                $params[] = $v->high;
                $params[] = $v->low;
                $params[] = $v->close;
                $params[] = $v->adjusted_close;
                $params[] = $v->volume;
                $adb->pquery($query, $params);
            }
        }

        $query = "UPDATE vtiger_modsecurities m JOIN vtiger_modsecuritiescf USING (modsecuritiesid) 
		SET eod_pricing = NOW() WHERE security_symbol = ?";
        $adb->pquery($query, array($symbol));

    }

    static public function WriteRawEODData($symbol, $data){
        global $adb;
        $query = "UPDATE vtiger_modsecurities SET raw_eod = ? WHERE security_symbol = ?";
        $adb->pquery($query, array($data, $symbol));
    }

    /**
     * Get the raw eod data from the database.  Returns 0 if raw_eod is null or set as {}
     * @param $symbol
     * @return int|null|string|string[]
     * @throws Exception
     */
    static public function GetRawEODDataFromSymbol($symbol){
        global $adb;
        $query = "SELECT raw_eod FROM vtiger_modsecurities WHERE security_symbol = ? AND raw_eod != '{}' AND raw_eod IS NOT NULL";
        $result = $adb->pquery($query, array($symbol));
        if($adb->num_rows($result) > 0)
            return html_entity_decode($adb->query_result($result, 0, 'raw_eod'));
        return 0;
    }

    static public function searchArrayValueByKey(array $array, $search) {
        foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($array)) as $key => $value) {
            if ($search === $key)
                return $value;
        }
        return false;
    }

    static public function getParentKeyFromArray($array, $search_field, $search_value){
        foreach($array as $key => $value) {
            if($value->{$search_field} == $search_value)
                return $key;
        }
        return -1;
    }

    static public function getAllSiblingData($array, $search_field, $search_value){
        foreach($array as $key => $value) {
            if($value->{$search_field} == $search_value)
                return $value;
        }
        return -1;
    }

    static public function getSiblingData($array, $search_field, $search_value, $sibling_key){
        foreach($array as $key => $value) {
            if($value->{$search_field} == $search_value)
                return $value->{$sibling_key};
        }
        return -1;
    }

    static public function UpdateSecurityFromEOD($symbol, $exchange){
        
		$type = OmnisolReader::DetermineSecurityTypeGivenByEOD($symbol);
        
		$writer = new OmniscientWriter();
        
		$symData = OmnisolReader::MatchSymbolsOfSecurityType(array($symbol), $type);
        
		$writer->WriteEodToOmni($symbol);
    }

    static public function UpdateFromEODGuzzleResult($symbolData, $dividendData = null, $symbol){
        global $adb;
        $params = array();
        $set = "";

        $start = date('Y')  - 1 . "-01-01";
        $end = date('Y') - 1 . "-12-31";

        if(count((array)$symbolData) > 0 ){#|| count((array)$dividendData) > 0){
            switch(strtolower($symbolData->General->Type)){
                case "mutual fund":
                case "fund":
                    $params[] = $symbolData->General->Name;
                    $params[] = $symbolData->General->Type;
                    $params[] = $symbolData->General->Exchange;
                    $params[] = $symbolData->General->CurrencyCode;
                    $params[] = $symbolData->General->CountryName;
                    $params[] = $symbolData->General->Isin;
                    $params[] = $symbolData->General->Fund_Summary;
                    $params[] = $symbolData->General->Fund_Family;

                    $params[] = $symbolData->MutualFund_Data->Nav;
                    $params[] = $symbolData->MutualFund_Data->Net_Assets;
                    $params[] = $symbolData->MutualFund_Data->Morning_Star_Rating;
                    $params[] = $symbolData->MutualFund_Data->Morning_Star_Risk_Rating;
                    $params[] = $symbolData->MutualFund_Data->Morning_Star_Category;
                    $params[] = $symbolData->MutualFund_Data->Incepton_Date;
                    $params[] = $symbolData->MutualFund_Data->Domicile;
                    $params[] = $symbolData->MutualFund_Data->Yield;

                    $cash = self::getSiblingData($symbolData->MutualFund_Data->Asset_Allocation, "Type", "Cash", "Net_%");
                    $us_stock = self::getSiblingData($symbolData->MutualFund_Data->Asset_Allocation, "Type", "US Stock", "Net_%");
                    $non_us_stock = self::getSiblingData($symbolData->MutualFund_Data->Asset_Allocation, "Type", "Non US Stock", "Net_%");
                    $bond = self::getSiblingData($symbolData->MutualFund_Data->Asset_Allocation, "Type", "Bond", "Net_%");
                    $other = self::getSiblingData($symbolData->MutualFund_Data->Asset_Allocation, "Type", "Other", "Net_%");
                    $params[] = $cash;
                    $params[] = $us_stock;
                    $params[] = $non_us_stock;
                    $params[] = $bond;
                    $params[] = $other;

                    $aclass = array("Cash" => $cash, "Stocks" => $us_stock + $non_us_stock, "Bonds" => $bond, "Other" => $other);
                    arsort($aclass);
                    reset($aclass);
                    $aclass = key($aclass);

                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Sensitive, "Type", "Technology", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Sensitive, "Type", "Energy", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Sensitive, "Type", "Industrials", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Sensitive, "Type", "Communication Services", "Amount_%");

                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Defensive, "Type", "Consumer Defensive", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Defensive, "Type", "Healthcare", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Defensive, "Type", "Utilities", "Amount_%");

                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Cyclical, "Type", "Basic Materials", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Cyclical, "Type", "Consumer Cyclical", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Cyclical, "Type", "Financial Services", "Amount_%");
                    $params[] = self::getSiblingData($symbolData->MutualFund_Data->Sector_Weights->Cyclical, "Type", "Real Estate", "Amount_%");

                    $set .= " security_name = ?, securitytype = ?, stock_exchange = ?, currency_code = ?, country = ?, isin = ?, summary = ?, fund_family = ?, nav = ?, net_assets = ?, 
                          Morning_Star_Rating = ?, Morning_Star_Risk_Rating = ?, Morning_Star_Category = ?, inception_date = ?, domicile = ?, dividend_yield = ?,
                          cash_net = ?, us_stock = ?, intl_stock = ?, us_bond = ?, other_net = ?, technology_weight = ?, energy_weight = ?, industrials_weight = ?, communication_services_weight = ?,
                          consumer_defensive_weight = ?, healthcare_weight = ?, utilities_weight = ?, basic_materials_weight = ?, consumer_cyclical_weight = ?, financial_services_weight = ?, real_estate_weight = ?";
                    break;
                case "common stock":
                    $params[] = $symbolData->General->Name;
                    $params[] = $symbolData->General->Type;
                    $params[] = $symbolData->General->Exchange;
                    $params[] = $symbolData->General->CurrencyCode;
                    $params[] = $symbolData->General->CountryName;
                    $params[] = $symbolData->General->ISIN;
                    $params[] = $symbolData->General->CUSIP;
                    $params[] = $symbolData->General->Sector;
                    $params[] = $symbolData->General->Industry;
                    $params[] = $symbolData->General->Description;
                    $params[] = $symbolData->Technicals->Beta;

                    $params[] = $symbolData->Highlights->MarketCapitalization;
                    $params[] = $symbolData->Highlights->EBITDA;
                    $params[] = $symbolData->Highlights->PERatio;
                    $params[] = $symbolData->Highlights->PEGRatio;
                    $params[] = $symbolData->Highlights->WallStreetTargetPrice;
                    $params[] = $symbolData->Highlights->BookValue;
                    $params[] = $symbolData->Highlights->DividendShare;
                    $params[] = $symbolData->Highlights->DividendYield;
                    $params[] = $symbolData->Highlights->EarningsShare;
                    $params[] = $symbolData->Highlights->EPSEstimateCurrentYear;
                    $params[] = $symbolData->Highlights->EPSEstimateNextYear;
                    $params[] = $symbolData->Highlights->EPSEstimateNextQuarter;

                    $set .= " security_name = ?, securitytype = ?, stock_exchange = ?, currency_code = ?, country = ?, isin = ?, cusip = ?, security_sector = ?, industrypl = ?, summary = ?, beta = ?,
                              market_capitalization = ?, ebitda = ?, peratio = ?, pegratio = ?, one_year_target_price = ?, book_value = ?, dividend_share = ?, 
                              dividend_yield = ?, earnings_share = ?, eps_estimate_current_year = ?, eps_estimate_next_year = ?, eps_estimate_next_quarter = ?, aclass = 'Stocks' ";
                    break;

                case "etf":
                    $params[] = $symbolData->General->Type;
                    $params[] = $symbolData->General->Name;
                    $params[] = $symbolData->General->Exchange;
                    $params[] = $symbolData->General->CountryName;
                    $params[] = $symbolData->General->Description;
                    $params[] = $symbolData->General->Category;

                    $params[] = $symbolData->ETF_Data->Isin;
                    $params[] = $symbolData->ETF_Data->Yield;
                    $params[] = $symbolData->ETF_Data->Dividend_Paying_Frequency;

                    $stock = $symbolData->ETF_Data->Asset_Allocation->Stock->{'Net_Assets_ % '};
                    $bond = $symbolData->ETF_Data->Asset_Allocation->Bond->{'Net_Assets_ % '};
                    $property = $symbolData->ETF_Data->Asset_Allocation->Property->{'Net_Assets_ % '};
                    $cash = $symbolData->ETF_Data->Asset_Allocation->Cash->{'Net_Assets_ % '};
                    $other = $symbolData->ETF_Data->Asset_Allocation->Other->{'Net_Assets_ % '};

                    $params[] = $stock;
                    $params[] = $bond;
                    $params[] = $property;
                    $params[] = $cash;
                    $params[] = $other;

                    $aclass = array("Cash" => $cash, "Stocks" => $stock, "Bonds" => $bond, "Other" => $other + $property);
                    arsort($aclass);
                    reset($aclass);
                    $aclass = key($aclass);

                    $params[] = $symbolData->ETF_Data->World_Regions->{'United States'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->Canada->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'Latin America'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'United Kingdom'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'Europe - except Euro'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'Europe - Emerging'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->Africa->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'Middle East'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->Japan->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->Australasia->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'Asia - Developed'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->World_Regions->{'Asia - Emerging'}->{'Equity_ % '};

                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Basic Materials'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Consumer Cyclical'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Financial Services'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Real Estate'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Consumer Defensive'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Healthcare'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Utilities'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Communication Services'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Energy'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Industrials'}->{'Equity_ % '};
                    $params[] = $symbolData->ETF_Data->Sector_Weights->{'Technology'}->{'Equity_ % '};

                    $set .= "securitytype = ?, security_name = ?, stock_exchange = ?, country = ?, summary = ?, Morning_Star_Category = ?, isin = ?, 
                             dividend_yield = ?, pay_frequency = ?, us_stock = ?, us_bond = ?, unclassified_net = ?, cash_net = ?, other_net = ?, us_equity = ?, 
                             canada_equity = ?, Latin_America_equity = ?, UK_equity = ?, Europe_ex_euro_equity = ?, Europe_Emerging_equity = ?, Africa_equity = ?, 
                             Middle_East_equity = ?, Japan_equity = ?, Australasia_equity = ?, Asia_Developed_equity = ?, Asia_Emerging_equity = ?, Basic_Materials_Weight = ?, 
                             Consumer_Cyclical_Weight = ?, Financial_Services_Weight = ?, Real_Estate_Weight = ?, Consumer_Defensive_Weight = ?, Healthcare_Weight = ?, 
                             Utilities_Weight = ?, Energy_Weight = ?, Industrials_Weight = ?, Communication_Services_Weight = ?, technology_weight = ? ";
                    break;
            }

        }

        if(count((array)$dividendData) > 0){
            switch(count((array)$dividendData)){
                case 2:
                    $frequency = "SemiAnnual";
                    break;
                case 4:
                case 5:
                case 6:
                    $frequency = "Quarterly";
                    break;
                default:
                    $frequency = "Annual";
                    break;
            }

            $params[] = $frequency;
            if(strlen($set) > 10)
                $set .= ", pay_frequency = ? ";
            else
                $set .= " pay_frequency = ? ";
        }

        if(count((array)$symbolData) > 0  || count((array)$dividendData) > 0) {
			
            $params[] = $symbol;

            $query = "UPDATE vtiger_modsecurities m 
            JOIN vtiger_modsecuritiescf USING (modsecuritiesid) SET last_eod = NOW(), {$set}
            WHERE security_symbol = ?";
            $adb->pquery($query, array($params));

            if(strlen($aclass) > 0){
                //@Ryan Please recheck if this is Required
				/*$query = "UPDATE vtiger_modsecurities m 
                JOIN vtiger_modsecuritiescf USING (modsecuritiesid) SET aclass = ?
                WHERE security_symbol = ? AND (aclass = 'Funds' OR aclass IS NULL OR aclass = '')";
                $adb->pquery($query, array($aclass, $symbol));*/
				
            }
			
        } else {
			$query = "UPDATE vtiger_modsecurities m 
			JOIN vtiger_modsecuritiescf USING (modsecuritiesid)
			SET last_eod = NOW() WHERE security_symbol = ?";
			$adb->pquery($query, array($symbol));
        }
    }

	/**
	 *
	 * @param $custodian
	 * @param $date
	 * @param $comparitor
	 * @return array|int
	 */
	static public function GetSecurities($custodian){
		global $adb;

		$tenant = self::$tenant;
		$query = "SELECT * FROM {$tenant}.custodian_securities_{$custodian} WHERE symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities)";
		$result = $adb->pquery($query, array());
		if($adb->num_rows($result) > 0){
			foreach($result AS $k => $v){
				$tmp[] = $v;
			}
			return $tmp;
		}
		return 0;
	}

	/**
	 *
	 * @param $custodian
	 * @param $date
	 * @param $comparitor
	 * @return array|int
	 */
	static public function GetNewSecurities($custodian){
		global $adb;

#		if($custodian == "td")
#			$custodian = "tda";
		$tenant = self::$tenant;

		switch($custodian){
			case "pershing":
			    $query = "SELECT * FROM {$tenant}.custodian_securities_pershing sec
                          LEFT JOIN vtiger_global_securities_mapping gsm ON gsm.{$custodian}_code = sec.type
                          WHERE (symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
                          AND cusip NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != ''))";
			break;
			case "schwab":
				$query = "SELECT * FROM {$tenant}.custodian_securities_{$custodian} sec
						  LEFT JOIN vtiger_global_securities_mapping gsm ON gsm.{$custodian}_code = sec.prod_code
						  WHERE symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
						  AND cusip NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
						  AND sec_nbr NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')";
			break;
			case "fidelity":
				$query = "SELECT * FROM {$tenant}.custodian_securities_{$custodian} sec
						  LEFT JOIN vtiger_global_securities_mapping gsm ON gsm.{$custodian}_code = sec.type
						  WHERE symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
						  AND symbol != ''";
			break;
            case "td":
                $query = "SELECT * FROM custodian_omniscient.custodian_securities_td sec
                          WHERE symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
                          AND symbol != ''";
                break;
			default:
				$query = "SELECT * FROM {$tenant}.custodian_securities_{$custodian} sec
						  LEFT JOIN vtiger_global_securities_mapping gsm ON gsm.{$custodian}_code = sec.prod_code
						  WHERE symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
						  AND symbol != ''";
			break;
		}

		$result = $adb->pquery($query, array());
		if($adb->num_rows($result) > 0){
			foreach($result AS $k => $v){
				if(($custodian == 'schwab' || $custodian == 'pershing') && trim($v['symbol']) == ''){
					$v['symbol'] = $v['cusip'];
				}
				$tmp[] = $v;
			}
		}

		return $tmp;
	}

	/**
	 * Check if the security symbol already exists
	 * @param $original_id
	 * @param $custodian
	 */
	static public function DoesSecurityAlreadyExist($symbol){
		global $adb;
		$query = "SELECT modsecuritiesid FROM vtiger_modsecurities WHERE security_symbol = ?";
		$result = $adb->pquery($query, array($symbol));
		if($adb->num_rows($result) > 0){
			return $adb->query_result($result, 0, 'modsecuritiesid');
		}
		return 0;
	}

	static public function UpdateSecurities($custodian, $symbol){
		global $adb;
		$tenant = self::$tenant;
		$query = "SELECT * FROM vtiger_modsecurities m JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
				  JOIN {$tenant}.custodian_securities_{$custodian} f ON f.symbol = m.security_symbol";
		$adb->pquery($query, array());
	}

	static public function UpdateAllTypesAndAssetClass($custodian){return;//This is no longer used this way and don't want to run by accident
		global $adb;
		$tenant = self::$tenant;
		$query = "UPDATE vtiger_modsecurities ms
				  JOIN vtiger_modsecuritiescf cf ON ms.modsecuritiesid = cf.modsecuritiesid
				  JOIN {$tenant}.custodian_securities_{$custodian} f ON f.symbol = ms.security_symbol
				  JOIN vtiger_security_mapping map ON map.code = f.type
				  SET ms.securitytype = map.type, cf.aclass = map.asset_class
				  WHERE cf.ignore_auto_update = 0";
		$adb->pquery($query, array());
	}

	static private function UseTDRules(&$custodian, &$security_type_map, &$cloudData, &$data){
		$data['security_name'] = $cloudData['description'];
		$data['security_symbol'] = $cloudData['symbol'];
		$data['aclass'] = $security_type_map[$cloudData['type']]['asset_class'];
		$data['securitytype'] = $security_type_map[$cloudData['type']]['type'];
#		$data['cusip'] = $cloudData['cusip'];
		$data['interest_rate'] = $cloudData['interest_rate'];
		$data['maturity_date'] = $cloudData['maturity'];
	}

	static private function UseFidelityRules(&$custodian, &$security_type_map, &$cloudData, &$data){
		//If the source is dvsplit, treat it like a regular buy
		//If the source is dvwash,
		//if type FC/MM/MF, create a cash withdrawal for the amount if it is of type buy.  If it is type sell, create a cash deposit for the amount.
		//If the source is cash, do not pay dividend
		$data['security_name'] = $cloudData['description'];
		$data['security_symbol'] = $cloudData['symbol'];
		$data['aclass'] = $security_type_map[$cloudData['type']]['asset_class'];
		$data['securitytype'] = $security_type_map[$cloudData['type']]['type'];
		$data['cusip'] = $cloudData['cusip'];
		$data['interest_rate'] = $cloudData['interest_rate'];
		$data['maturity_date'] = $cloudData['maturity_date'];
	}

	static private function UseSchwabRules(&$custodian, &$security_type_map, &$cloudData, &$data){
		//If the source is dvsplit, treat it like a regular buy
		//If the source is dvwash,
		//if type FC/MM/MF, create a cash withdrawal for the amount if it is of type buy.  If it is type sell, create a cash deposit for the amount.
		//If the source is cash, do not pay dividend
		$data['security_name'] = $cloudData['description1'];
		if($cloudData['symbol'] != '')
			$data['security_symbol'] = $cloudData['symbol'];
		else
			$data['security_symbol'] = $cloudData['sec_nbr'];
		$data['aclass'] = $cloudData['asset_class'];
		$data['securitytype'] = cloudData['security_type'];

		$data['header'] = $cloudData['header'];
		$data['custodian_id'] = $cloudData['custodian_id'];
		$data['master_account_number'] = $cloudData['master_account_number'];
		$data['master_account_name'] = $cloudData['master_account_name'];
		$data['business_date'] = $cloudData['business_date'];
		$data['prod_code'] = $cloudData['prod_code'];
		$data['prod_catg_code'] = $cloudData['prod_catg_code'];
		$data['tax_code'] = $cloudData['tax_code'];
		$data['ly'] = $cloudData['ly'];
		$data['industry_ticker_symbol'] = $cloudData['industry_ticker_symbol'];
		$data['cusip'] = $cloudData['cusip'];
		$data['sec_nbr'] = $cloudData['sec_nbr'];
		$data['reorg_sec_nbr'] = $cloudData['reorg_sec_nbr'];
		$data['item_issue_id'] = $cloudData['item_issue_id'];
		$data['rulst_sufid'] = $cloudData['rulst_sufid'];
		$data['isin'] = $cloudData['isin'];
		$data['sedol'] = $cloudData['sedol'];
		$data['options_display_symbol'] = $cloudData['options_display_symbol'];
		$data['description1'] = $cloudData['description1'];
		$data['description2'] = $cloudData['description2'];
		$data['description3'] = $cloudData['description3'];
		$data['scrty_des'] = $cloudData['scrty_des'];
		$data['underlying_ticker_symbol'] = $cloudData['underlying_ticker_symbol'];
		$data['underlying_industry_ticker_symbol'] = $cloudData['underlying_industry_ticker_symbol'];
		$data['underlying_cusip'] = $cloudData['underlying_cusip'];
		$data['underly_schwab'] = $cloudData['underly_schwab'];
		$data['underlying_itm_iss_id'] = $cloudData['underlying_itm_iss_id'];
		$data['unrul_sufid'] = $cloudData['unrul_sufid'];
		$data['underlying_isin'] = $cloudData['underlying_isin'];
		$data['underly_sedol'] = $cloudData['underly_sedol'];
		$data['mnymk_code'] = $cloudData['mnymk_code'];
		$data['last_update'] = $cloudData['last_update'];
		$data['s_f'] = $cloudData['s_f'];
		$data['closing_price'] = $cloudData['closing_price'];
		$data['secprice_lstupd'] = $cloudData['secprice_lstupd'];
		$data['security_valuation_unit'] = $cloudData['security_valuation_unit'];
		$data['optnrt_symbol'] = $cloudData['optnrt_symbol'];
		$data['opt_expr_date'] = $cloudData['opt_expr_date'];
		$data['c_p'] = $cloudData['c_p'];
		$data['strike_price'] = $cloudData['strike_price'];
		$data['interest_rate'] = $cloudData['interest_rate'];
		$data['maturity_date'] = $cloudData['maturity_date'];
		$data['tips_factor'] = $cloudData['tips_factor'];
		$data['asset_backed_factor'] = $cloudData['asset_backed_factor'];
		$data['face_value_amt'] = $cloudData['face_value_amt'];
		$data['st_cd'] = $cloudData['st_cd'];
		$data['vers_mrkr_1'] = $cloudData['vers_mrkr_1'];
		$data['p_i'] = $cloudData['p_i'];
		$data['o_i'] = $cloudData['o_i'];
		$data['vers_mrkr_2'] = $cloudData['vers_mrkr_2'];
		$data['closing_price_unfactored'] = $cloudData['closing_price_unfactored'];
		$data['factor'] = $cloudData['factor'];
		$data['factor_date'] = $cloudData['factor_date'];

	}

	static private function UsePershingRules(&$custodian, &$security_type_map, &$cloudData, &$data){
		$data['security_name'] = $cloudData['security_description_1'];
		$data['security_symbol'] = $cloudData['symbol'];
        $data['prod_code'] = $cloudData['type'];
	}

	/**
	 * Maps the Custodian data to be compatible with the Transactions module
	 * @param $custodian
	 * @param $$security_type_map
	 * @param $cloudData
	 * @param $data
	 */
	static private function MapCloudToModuleData(&$custodian, &$security_type_map, &$cloudData, &$data){
		switch($custodian){
			case "td":
			case "millenium":
				self::UseTDRules($custodian, $security_type_map, $cloudData, $data);
				break;
			case "fidelity":
				self::UseFidelityRules($custodian, $security_type_map, $cloudData, $data);
				break;
			case "omniscient":
				self::UseOmniscientRules($custodian, $security_type_map, $cloudData, $data);
				break;
			case "schwab":
				self::UseSchwabRules($custodian, $security_type_map, $cloudData, $data);
				break;
			case "pershing":
				self::UsePershingRules($custodian, $security_type_map, $cloudData, $data);
				break;
		}
	}

	static public function ConvertCustodian($custodian, $date, $comparitor){
		self::CloudToModuleConversion($custodian, $date, $comparitor);
	}


	/**
	 * Update the individual price for a security
	 * @param $symbol
	 */
	static public function UpdateIndividualPrice($symbol){
		global $adb;
		$query = "UPDATE vtiger_modsecurities s
				  JOIN vtiger_crmentity e ON e.crmid = s.modsecuritiesid
				  SET security_price = (SELECT price
				  FROM vtiger_pc_security_prices
				  INNER JOIN
				  (SELECT symbol, MAX(price_date) as TopDate
				   FROM vtiger_pc_security_prices
				   WHERE symbol = ?
				   GROUP BY symbol) AS EachItem ON
				   EachItem.TopDate = vtiger_pc_security_prices.price_date
			       AND EachItem.symbol = vtiger_pc_security_prices.symbol), e.lastmodified = NOW()
				  WHERE s.security_symbol = ?";
		$adb->pquery($query, array($symbol, $symbol));
	}

	static public function UpdateAllPricesFromCloud($custodian){
		global $adb;
		$tenant = self::$tenant;
		$query = "SELECT * FROM {$tenant}.custodian_securities_{$custodian} WHERE symbol NOT IN (SELECT security_symbol FROM vtiger_modsecurities)";

		$query = "UPDATE vtiger_modsecurities sec
				  JOIN vtiger_crmentity e ON e.crmid = sec.modsecuritiesid
				  JOIN {$tenant}.custodian_prices_{$custodian} pr ON pr.symbol = sec.security_symbol
				  SET sec.security_price = pr.price, e.modifiedtime = NOW()
				  WHERE pr.price_date = CURDATE() - INTERVAL 1 DAY";
		$adb->pquery($query, array());
	}

	static public function UpdateAllPrices(){
		global $adb;
		//////USE THIS IF WE WANT TO UPDATE FROM THE CUSTODIAN PRICES AFTER PULLING PC VALUE
		/*		$query = "UPDATE vtiger_pc_security_prices pr
						  JOIN custodian_omniscient.custodian_prices_fidelity f ON (pr.symbol = f.symbol AND pr.price_date = f.price_date)
						  SET pr.price = f.price
						  WHERE pr.price_date = CURDATE() - INTERVAL 1 DAY";
				$adb->pquery($query, array());*/

		$query = "UPDATE vtiger_modsecurities sec
				  JOIN vtiger_crmentity e ON e.crmid = sec.modsecuritiesid
				  JOIN vtiger_pc_security_prices pr ON pr.symbol = sec.security_symbol
				  SET sec.security_price = pr.price, e.modifiedtime = NOW()
				  WHERE pr.price_date = CURDATE() - INTERVAL 1 DAY";
		$adb->pquery($query, array());

		/*		$query = "DROP TABLE IF EXISTS tmp_prices";
				$adb->pquery($query, array());

				if(strlen($symbol) > 2)
					$and = " AND t2.symbol = ?";
				$query = "CREATE TEMPORARY TABLE tmp_prices
				select * from vtiger_pc_security_prices t1
				where trade_date = (select max(trade_date) from vtiger_pc_security_prices t2 where t1.symbol = t2.symbol {$and})";
				if(strlen($symbol) > 2)
					$adb->pquery($query, array($symbol));
				else
					$adb->pquery($query, array());

				$query = "UPDATE vtiger_modsecurities sec
						  JOIN vtiger_crmentity e ON e.crmid = sec.modsecuritiesid
							JOIN tmp_prices pri ON sec.security_symbol = pri.symbol
							SET sec.security_price = pri.price, e.modifiedtime = NOW()";
				$adb->pquery($query, array());*/
	}

	static private function CreateTemporarySecurityPriceTable($custodian, $date){
		global $adb;
		$tenant = self::$tenant;
		$query = "DROP TABLE IF EXISTS security_info";
		$adb->pquery($query, array());

		switch($custodian){
			case "fidelity":
				$query = "CREATE TEMPORARY TABLE security_info (INDEX symbol_i (symbol))
				  SELECT symbol, close_price, current_factor 
				  FROM {$tenant}.custodian_positions_{$custodian} 
				  WHERE as_of_date=?
				  GROUP BY symbol";
				break;
			case "td":
				$query = "CREATE TEMPORARY TABLE security_info (INDEX symbol_i (symbol))
				  SELECT tdp.symbol, tdpr.price AS close_price, tdpr.factor AS current_factor
				  FROM {$tenant}.custodian_positions_{$custodian} tdp
				  JOIN {$tenant}.custodian_prices_{$custodian} tdpr ON tdpr.symbol = tdp.symbol
				  WHERE tdpr.date=?
				  GROUP BY tdp.symbol";
				break;
			case "schwab":
				$query = "CREATE TEMPORARY TABLE security_info (INDEX symbol_i (symbol))
				  SELECT spos.symbol, spr.price AS close_price, 0 AS current_factor
				  FROM {$tenant}.custodian_positions_{$custodian} spos
				  JOIN {$tenant}.custodian_prices_{$custodian} spr ON spr.symbol = LEFT(spos.symbol, 8)
				  WHERE spr.date=?
				  GROUP BY spos.symbol";
				break;
			case "pershing":
				$query = "DROP TABLE IF EXISTS security_info";
				$adb->pquery($query, array());

				$query = "CREATE TEMPORARY TABLE security_info (INDEX symbol_i (symbol))
				  SELECT spos.symbol, spr.latest_price AS close_price, 0 AS current_factor
				  FROM {$tenant}.custodian_positions_{$custodian} spos
				  JOIN {$tenant}.custodian_prices_{$custodian} spr ON spr.symbol = spos.symbol
				  WHERE spr.latest_price_date=?
				  GROUP BY spos.symbol";
				$adb->pquery($query, array($date));

				$query = "INSERT INTO security_info
				  SELECT spos.symbol, spr.latest_price AS close_price, 0 AS current_factor
				  FROM {$tenant}.custodian_positions_{$custodian} spos
				  JOIN {$tenant}.custodian_prices_{$custodian} spr ON spr.cusip = spos.symbol
				  WHERE spr.latest_price_date=? AND spos.symbol NOT IN (SELECT symbol FROM custodian_omniscient.custodian_prices_pershing WHERE latest_price_date = ?)
				  GROUP BY spos.symbol";
				$adb->pquery($query, array($date, $date));

				$query = "UPDATE security_info SET close_price = close_price / 1000";
				$adb->pquery($query, array());
				break;
		}

		$adb->pquery($query, array($date));
	}

	static public function GetSecurityTypeInfoFromMappingTable($symbol, $custodian){
		global $adb;
		$params = array();

		if($symbol){
			$params[] = $symbol;
			$where = " WHERE m.security_symbol = ?";
		}

		$query = "SELECT m.security_symbol, map.* FROM vtiger_modsecurities m
				  JOIN vtiger_global_securities_mapping map ON m.prod_code = map.{$custodian}_code
				  {$where}";
		$result = $adb->pquery($query, $params);

		$tmp = array();
		if($adb->num_rows($result) > 0){
			while($v = $adb->fetchByAssoc($result)){
				$tmp[] = $v;
			}
			return $tmp;
		}
		return 0;
	}

	static public function UpdateSecurityTypePershing($symbol){
	    global $adb;
	    $params = array();

        $omniview = ModSecurities_Module_Model::GetAndSetOmniViewNumbersString($params, 0, 0, 100);

    }

    static public function UpdateOptionsTD($symbol = null){
	    global $adb;
        $tenant = self::$tenant;
        $params = array();

        if($symbol){
            $questions = generateQuestionMarks($symbol);
            $params[] = $symbol;
            $and = " AND m.security_symbol IN ({$questions}) ";
        }

	    $query = "UPDATE vtiger_modsecurities m
	              JOIN vtiger_modsecuritiescf cf ON m.modsecuritiesid = cf.modsecuritiesid
	              JOIN {$tenant}.custodian_securities_td s ON m.security_symbol = s.symbol
	              SET cf.us_bond = 0, cf.unclassified_net = 0, cf.other_net = 100, cf.cash_net = 0, cf.us_stock = 0,  cf.aclass = 'Alternative', m.securitytype = 'Option'
	              WHERE s.security_type = 'OP' {$and}";
	    $adb->pquery($query, $params);
    }

    static public function UpdateOptionsSchwab($symbol = null){
        global $adb;
        $tenant = self::$tenant;
        $params = array();

        if($symbol){
            $questions = generateQuestionMarks($symbol);
            $params[] = $symbol;
            $and = " AND m.security_symbol IN ({$questions}) ";
        }

        $query = "UPDATE vtiger_modsecurities m
                  JOIN vtiger_modsecuritiescf cf ON m.modsecuritiesid = cf.modsecuritiesid
                  JOIN {$tenant}.custodian_securities_schwab s ON m.security_symbol = s.symbol
                  SET cf.us_bond = 0, cf.unclassified_net = 0, cf.other_net = 100, cf.cash_net = 0, cf.us_stock = 0,  cf.aclass = 'Alternative', m.securitytype = 'Option'
                  WHERE s.prod_code = 'OEQ' {$and}";

        $adb->pquery($query, $params);
    }

	static public function UpdateSecurityType($custodian, $symbol = null){
		global $adb;
		$params = array();

		if($symbol){
		    $questions = generateQuestionMarks($symbol);
			$params[] = $symbol;
			$and = " AND m.security_symbol IN ({$questions}) ";
		}

		$query = "UPDATE vtiger_modsecurities m
				  JOIN vtiger_modsecuritiescf cf ON m.modsecuritiesid = cf.modsecuritiesid
				  JOIN vtiger_global_securities_mapping map ON m.prod_code = map.{$custodian}_code
				  SET m.securitytype = map.security_type, cf.security_price_adjustment = map.multiplier, cf.aclass = map.asset_class,
                  cf.us_bond = CASE WHEN map.security_type LIKE('%Bond%') THEN 100 ELSE cf.us_bond END,
                  cf.unclassified_net = CASE WHEN map.security_type LIKE('%Bond%') THEN 0 ELSE cf.unclassified_net END,
                  cf.other_net = CASE WHEN map.security_type LIKE('%Bond%') THEN 0 ELSE cf.other_net END,
                  cf.cash_net = CASE WHEN map.security_type LIKE('%Bond%') THEN 0 ELSE cf.cash_net END,
                  cf.us_stock = CASE WHEN map.security_type LIKE ('%Bond%') THEN 0 ELSE cf.us_stock END,
                  cf.ignore_auto_update = CASE WHEN map.security_type LIKE ('%Bond%') THEN 1 ELSE cf.ignore_auto_update END
				  WHERE cf.cash_instrument != 1 AND m.prod_code != '' {$and}";
		$adb->pquery($query, $params);
	}

	static public function UpdateSecurityFieldsTD($symbol = null, $setprice = false){
	    global $adb;
	    $tenant = self::$tenant;
	    $params = array();
	    if($symbol){
	        $questions = generateQuestionMarks($symbol);
	        $where = " WHERE p.symbol IN ({$questions}) ";
	        $params[] = $symbol;
        }

        $query = "DROP TABLE IF EXISTS use_prices";
	    $adb->pquery($query, array());

        $query = "DROP TABLE IF EXISTS tprices";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE use_prices
				  SELECT symbol, max(date) AS price_date
				  FROM {$tenant}.custodian_prices_td
				  GROUP BY symbol";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tprices
				  SELECT p.symbol, p.date, p.price, p.factor, sec.description, sec.interest_rate, sec.maturity, sec.security_type
				  FROM {$tenant}.custodian_prices_td p
				  JOIN use_prices u ON u.symbol = p.symbol 
				  JOIN {$tenant}.custodian_securities_td sec ON p.symbol = sec.symbol AND u.price_date = p.date
				  {$where}";
        $adb->pquery($query, $params);

        $price = '';
        if($setprice){
            $price = " m.security_price = pr.price, ";
        }
        $query = "UPDATE vtiger_modsecurities m
				  JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
				  JOIN vtiger_crmentity e ON e.crmid = m.modsecuritiesid
				  JOIN tprices pr ON m.security_symbol = pr.symbol
				  SET {$price} m.security_name = pr.description, m.interest_rate = pr.interest_rate, 
				  			   m.maturity_date = pr.maturity, m.prod_code = pr.security_type";
        $adb->pquery($query, array());

        self::UpdateSecurityType("td", $symbol);
        self::UpdateOptionsTD($symbol);
    }

	static public function UpdateSecurityFieldsFidelity($symbol = null, $setprice = false){
		global $adb;
		$tenant = self::$tenant;
		$params = array();
		if($symbol) {
		    $questions = generateQuestionMarks($symbol);
			$where = " WHERE p.symbol IN ({$questions}) ";
			$params[] = $symbol;
		}

		$query = "DROP TABLE IF EXISTS use_prices";
		$adb->pquery($query, array());
		$query = "DROP TABLE IF EXISTS tprices";
		$adb->pquery($query, array());

		$query = "CREATE TEMPORARY TABLE use_prices
				  SELECT symbol, max(price_date) AS price_date
				  FROM {$tenant}.custodian_prices_fidelity p 
				  {$where}
				  GROUP BY symbol";
		$adb->pquery($query, $params);

		$query = "CREATE TEMPORARY TABLE tprices
				  SELECT p.symbol, p.price_date, p.price, p.factor_par, sec.cusip, sec.description, sec.interest_rate, sec.maturity_date, sec.type
				  FROM {$tenant}.custodian_prices_fidelity p
				  JOIN use_prices u ON u.symbol = p.symbol 
				  JOIN {$tenant}.custodian_securities_fidelity sec ON p.symbol = sec.symbol AND u.price_date = p.price_date
				  {$where}";
		$adb->pquery($query, $params);

		$where = '';
		$price = '';
		if($setprice){
			$price = " m.security_price = pr.price, ";
//			$where = " WHERE pr.price_date >= e.modifiedtime";
		}
		$query = "UPDATE vtiger_modsecurities m
				  JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
				  JOIN vtiger_crmentity e ON e.crmid = m.modsecuritiesid
				  JOIN tprices pr ON m.security_symbol = pr.symbol
				  SET {$price} m.security_name = pr.description, mcf.cusip = pr.cusip, m.interest_rate = pr.interest_rate, 
				  			   m.maturity_date = pr.maturity_date, m.prod_code = pr.type
				  {$where}";

		$adb->pquery($query, array());

        self::UpdateSecurityType("fidelity", $symbol);
	}

    static public function UpdateSecurityFieldsPershing($symbol = null, $setprice = false){
        global $adb;
        $tenant = self::$tenant;
        $params = array();
        if($symbol) {
            $questions = generateQuestionMarks($symbol);
            $where = " WHERE (p.symbol IN ({$questions}) OR p.cusip IN ({$questions}))";
            $params[] = $symbol;
            $params[] = $symbol;
        }

        $query = "DROP TABLE IF EXISTS use_prices1";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS use_prices";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS tprices";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE use_prices1
                  SELECT CASE WHEN TRIM(symbol) = '' THEN TRIM(cusip) ELSE TRIM(symbol) END AS symbol, TRIM(cusip) AS cusip, max(latest_price_date) AS price_date
                  FROM {$tenant}.custodian_prices_pershing p 
                  {$where}
                  GROUP BY symbol, cusip";
        $adb->pquery($query, $params);

        $query = "CREATE TEMPORARY TABLE use_prices
                  SELECT * FROM use_prices1 GROUP BY symbol ORDER BY price_date DESC";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE tprices
                  SELECT u.symbol, p.latest_price_date, p.latest_price/1000 AS latest_price, sec.cusip, sec.type, sec.security_description_1
				  FROM {$tenant}.custodian_prices_pershing p
				  JOIN use_prices u ON (u.cusip = p.cusip)
				  JOIN {$tenant}.custodian_securities_pershing sec ON (p.cusip = sec.cusip) AND u.price_date = p.latest_price_date
				  {$where}";
        $adb->pquery($query, $params);

        $where = '';
        $price = '';
        if($setprice){
            $price = " m.security_price = pr.latest_price, ";
//			$where = " WHERE pr.price_date >= e.modifiedtime";
        }
        $query = "UPDATE vtiger_modsecurities m
				  JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
				  JOIN vtiger_crmentity e ON e.crmid = m.modsecuritiesid
				  JOIN tprices pr ON m.security_symbol = pr.symbol
				  SET {$price} m.security_name = pr.security_description_1, 
				               mcf.cusip = pr.cusip, m.prod_code = pr.type";

        $adb->pquery($query, array());

        self::UpdateSecurityType("pershing", $symbol);
    }

	static public function UpdateSecurityFieldsSchwab($symbol = null){
		global $adb;
		$tenant = self::$tenant;
		$params = array();

		if(sizeof(symbol) < 1)
		    return;

        if($symbol) {
            $questions = generateQuestionMarks($symbol);
            $where = " WHERE p.symbol IN ({$questions}) ";
            $params[] = $symbol;
        }

        $query = "DROP TABLE IF EXISTS use_prices";
        $adb->pquery($query, array());
        $query = "DROP TABLE IF EXISTS tprices";
        $adb->pquery($query, array());

        $query = "CREATE TEMPORARY TABLE use_prices
				  SELECT symbol, max(date) AS price_date
				  FROM {$tenant}.custodian_prices_schwab p 
				  {$where}
				  GROUP BY symbol";
        $adb->pquery($query, $params);

        $query = "CREATE TEMPORARY TABLE tprices
                  SELECT p.date, p.price, p.security_type, sec.*, CASE WHEN sec.symbol != '' THEN sec.symbol
						                                               WHEN sec.cusip != '' THEN sec.cusip
						                                               WHEN sec.sec_nbr != '' THEN sec.sec_nbr END AS symbol_override
				  FROM {$tenant}.custodian_prices_schwab p
				  JOIN use_prices u ON u.symbol = p.symbol 
				  JOIN {$tenant}.custodian_securities_schwab sec ON p.symbol = CASE WHEN LEFT(sec.symbol,8) != '' THEN LEFT(sec.symbol,8) 
                                                                                    WHEN LEFT(sec.cusip,8) != '' THEN LEFT(sec.cusip,8) 
                                                                                    WHEN LEFT(sec.sec_nbr,8) != '' THEN LEFT(sec.sec_nbr,8) END 
                  AND u.price_date = p.date
				  {$where}";
        $adb->pquery($query, $params);

        $query = "UPDATE tprices SET symbol = symbol_override WHERE symbol = ''";
        $adb->pquery($query, array());

        $query = "UPDATE vtiger_modsecurities m
                  JOIN vtiger_modsecuritiescf cf ON m.modsecuritiesid = cf.modsecuritiesid
                  JOIN tprices tmp ON tmp.symbol = m.security_symbol
                  JOIN vtiger_global_securities_mapping map ON map.schwab_code = tmp.prod_code
                  SET m.security_price = tmp.price, m.header=tmp.header, m.custodian_id=tmp.custodian_id, m.master_account_number=tmp.master_account_number, m.master_account_name=tmp.master_account_name, m.business_date=tmp.business_date, m.prod_code=tmp.prod_code, m.prod_catg_code=tmp.prod_catg_code, m.tax_code=tmp.tax_code,
                    m.ly=tmp.ly, m.industry_ticker_symbol=tmp.industry_ticker_symbol, cf.cusip=tmp.cusip, m.sec_nbr=tmp.sec_nbr, m.reorg_sec_nbr=tmp.reorg_sec_nbr, m.item_issue_id=tmp.item_issue_id, m.rulst_sufid=tmp.rulst_sufid, m.isin=tmp.isin, m.sedol=tmp.sedol, 
                    m.options_display_symbol=tmp.options_display_symbol, m.description1=tmp.description1, m.description2=tmp.description2, m.description3=tmp.description3, m.scrty_des=tmp.scrty_des, m.underlying_ticker_symbol=tmp.underlying_ticker_symbol, m.underlying_industry_ticker_symbol=tmp.underlying_industry_ticker_symbol,
                    m.underlying_cusip=tmp.underlying_cusip, m.underly_schwab=tmp.underly_schwab, m.underlying_itm_iss_id=tmp.underlying_itm_iss_id, m.unrul_sufid=tmp.unrul_sufid, m.underlying_isin=tmp.underlying_isin, m.underly_sedol=tmp.underly_sedol, m.mnymk_code=tmp.mnymk_code, 
                    m.last_update=tmp.last_update, m.s_f=tmp.s_f, m.closing_price=tmp.closing_price, m.secprice_lstupd=tmp.secprice_lstupd, m.security_valuation_unit=tmp.security_valuation_unit, m.optnrt_symbol=tmp.optnrt_symbol, m.opt_expr_date=tmp.opt_expr_date, m.c_p=tmp.c_p, 
                    m.strike_price=tmp.strike_price, m.interest_rate=tmp.interest_rate, cf.maturity_date=tmp.maturity_date, m.tips_factor=tmp.tips_factor, m.asset_backed_factor=tmp.asset_backed_factor, m.face_value_amt=tmp.face_value_amt, m.st_cd=tmp.st_cd, m.vers_mrkr_1=tmp.vers_mrkr_1, 
                    m.p_i=tmp.p_i, m.o_i=tmp.o_i, m.vers_mrkr_2=tmp.vers_mrkr_2, m.closing_price_unfactored=tmp.closing_price_unfactored, m.schwab_factor=tmp.factor, m.factor_date=tmp.factor_date, m.source='Schwab',
                    cf.aclass=map.asset_class,cf.security_price_adjustment=map.multiplier,m.securitytype=map.security_type";
        $adb->pquery($query, array());

        self::UpdateSecurityType("schwab", $symbol);
        self::UpdateOptionsSchwab($symbol);
	
	}

	static public function UpdateSecurityPrices($custodian, $date){
		global $adb;
		$tenant = self::$tenant;

		self::CreateTemporarySecurityPriceTable($custodian, $date);

		$query = "UPDATE vtiger_modsecurities m
				  JOIN vtiger_modsecuritiescf mcf ON m.modsecuritiesid = mcf.modsecuritiesid
				  JOIN security_info si ON m.security_symbol = si.symbol
				  JOIN {$tenant}.custodian_securities_{$custodian} f ON f.symbol = si.symbol
				  SET security_price = close_price, factor = current_factor";
		$adb->pquery($query, array());
	}

	static private function DeterminePriceAdjustmentFidelity($data){
		switch($data['type']){
			case "CB":
			case "MB":
			case "GB":
				return 0.01;
				break;
			default:
				return 1;
		}
	}

	static private function DeterminePriceAdjustmentTD($data){
		switch($data['type']){
			case "CP":
			case "FI":
			case "CD":
			case "MB":
			case "TB":
				return 0.01;
				break;
			default:
				return 1;
		}
	}

	static private function DeterminePriceAdjustmentPershing($data){
		switch($data['type']){
			case 5:
			case 6:
            case 7:
				return 0.01;
				break;
            case 8:
                return 100;
			default:
				return 1;
		}
	}

	static private function DeterminePriceAdjustmentSchwab($data){
		switch($data['ly']){
			case "cb":
			case "gb":
			case "mb":
			case "cd":
			case "tn":
			case "zt":
				return 0.01;
				break;
			default:
				return 1;
		}
	}

	static private function RemapSecurities($custodian){

	}

	static private function GetNewMoneySecuritiesPershing(){
	    global $adb;
        $tenant = self::$tenant;

        $query = "SELECT fund_mnemonic AS symbol, fund_manager AS security_description_1, 'PCASH' AS type
                  FROM {$tenant}.custodian_money_pershing WHERE fund_mnemonic NOT IN (SELECT security_symbol FROM vtiger_modsecurities WHERE security_symbol != '')
                  AND fund_mnemonic != ''";

        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
        foreach($result AS $k => $v){
            $tmp[] = $v;
            }
        }

        return $tmp;
    }

	static private function CloudToModuleConversion($custodian, $date, $comparitor){
		$security_type_map = self::GetSecurityTypeMapping($custodian);
		$securities = self::GetNewSecurities($custodian);
/*		THIS PORTION NEEDS TESTED... At the time of writing this, all money securities already exist in the CRM so we get no result
		if($custodian == "pershing"){
		    $m = self::GetNewMoneySecuritiesPershing();
		    $securities = array_merge($securities, $m);
        }
#        echo "HI!";exit;
*/
		$count = 0;

		if($securities){
			set_time_limit ( 0 );
			foreach($securities AS $k => $v){
//				echo "START OF LOOP: " . memory_get_peak_usage() . " - Count: " . $count . PHP_EOL;
				$record = self::DoesSecurityAlreadyExist($v['symbol']);
				if($record){//If the record exists, use it instead
					$tmp = Vtiger_Record_Model::getInstanceById($record, "ModSecurities");
					$tmp->set('mode', 'edit');
//					echo "EDIT<br />";
				}else{
					$tmp = Vtiger_Record_Model::getCleanInstance("ModSecurities");
					$tmp->set('mode', 'create');
//					echo "NEW<br />";
				}

				$data = $tmp->getData();
				switch($custodian){
					case "fidelity":
						$data['security_price_adjustment'] = self::DeterminePriceAdjustmentFidelity($v);
						break;
					case "td":
						$data['security_price_adjustment'] = self::DeterminePriceAdjustmentTD($v);
						break;
					case "pershing":
						$data['security_price_adjustment'] = self::DeterminePriceAdjustmentPershing($v);
						break;
					case "schwab":
						$data['security_price_adjustment'] = self::DeterminePriceAdjustmentSchwab($v);
						break;
				}

				self::MapCloudToModuleData($custodian, $security_type_map, $v, $data);
				$tmp->setData($data);
				$tmp->save();
				ModSecurities_ConvertCustodian_Model::UpdateSecurityType($custodian, $v['security_symbol']);
				ModSecurities_Module_Model::FillWithYQLOrXigniteData($v['symbol']);
				$count++;
			}
		}
		echo "{$count} securities added from {$custodian}";
		self::UpdateAllPrices();
	}

	static public function WriteAllEODBonds(){
        global $adb;
        $guz = new cEodGuzzle();

        $query = "SELECT cusip, security_symbol
                  FROM vtiger_modsecurities m 
                  JOIN vtiger_modsecuritiescf cf USING (modsecuritiesid) 
                  WHERE aclass = 'Bonds' AND cusip IS NOT NULL AND cusip != '' AND raw_eod IS NULL LIMIT 300";
        $result = $adb->pquery($query, array());
        $count = 1;
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                echo "Trying: {$count} -- " . $v['cusip'] . '<br />';
                $rawData = $guz->getBonds($v['cusip']);
                self::WriteRawEODData($v['security_symbol'], $rawData);
                $count++;
            }
        }
    }

    static public function UpdateIndexSymbolsEOD(array $symbol, $sdate, $edate){
        $token_date = $sdate;
        while(strtotime($token_date) <= strtotime($edate)){
            $tmp_end = date("Y-m-t", strtotime($token_date));
            if($token_date > $edate)
                return;
            if($tmp_end > $edate)
                $tmp_end = $edate;

            foreach($symbol AS $k => $v){
                ModSecurities_ConvertCustodian_Model::UpdateIndexEOD($v, $token_date, $tmp_end);
            }
            $token_date = date("Y-m-01", (strtotime('+1 month', strtotime($token_date) ) ));
        }
    }

    static public function UpdateAllIndexesEOD($sdate, $edate){
	    $token_date = $sdate;
        $indexes = ModSecurities_Module_Model::GetAllIndexes();
        while(strtotime($token_date) <= strtotime($edate)){
            $tmp_end = date("Y-m-t", strtotime($token_date));
            if($token_date > $edate)
                return;
            if($tmp_end > $edate)
                $tmp_end = $edate;

            foreach($indexes AS $k => $v){
                ModSecurities_ConvertCustodian_Model::UpdateIndexEOD($v, $token_date, $tmp_end);
            }
            $token_date = date("Y-m-01", (strtotime('+1 month', strtotime($token_date) ) ));
        }
    }
}
