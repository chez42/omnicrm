<?php
class PortfolioInformation_Reports_Model extends Vtiger_Module {
	public function getDisplayValue($value, $record = false, $recordInstance = false) {
            $info = new PortfolioInformation_ReportTopNavigation_View();

//            $module_name = $this->get('field')->getModuleName();
            $module_name = $value->get('module');
            switch($module_name){
                case "PortfolioInformation":
                    $instance = "account_number";
                    if($recordInstance)//Apparently there may not be a record instance, so we have to put in this conditional so things don't crash
                        $acct = $recordInstance->get('account_number');
                    break;
                case "Accounts":
                    $instance = "household";
                    $acct = $value->get('record');
                    break;
                case "Contacts":
                    $instance = "contacts";
                    $acct = $value->get('record');
                    break;
            }
            
            $request = new Vtiger_Request($_REQUEST, $_REQUEST);
            $request->set('module','PortfolioInformation');
            $request->set('instance',$instance);
            $request->set('acct',$acct);
            
            echo $info->process($request);
        }

	/**
	 * Get the mailing information for the given account based on the module.  For example if the account #1234 is passed in with Contacts or PortfolioInformation, it will get the address from the Contact module.
	 * If it is the Accounts module, it gets it from the household address
	 * @param $module
	 * @param array $accounts
	 */
	static public function GetMailingInformationForAccount($module, array $accounts){
		$mailing_info = null;
		if($module == "")
		    $module = "PortfolioInformation";
        $accounts[0] = str_replace("combined_", "", $accounts[0]);
		switch($module){//We want the contact module information
			case "PortfolioInformation":
			case "Contacts":
				$contact = PortfolioInformation_Module_Model::GetContactEntityFromAccountNumber($accounts[0]);
				if($contact) {
                    $data = $contact->getData();
                    $mailing_info['name'] = $data['firstname'] . " " . $data['lastname'];
                    $mailing_info['street'] = $data['mailingstreet'];
                    $mailing_info['city'] = $data['mailingcity'];
                    $mailing_info['state'] = $data['mailingstate'];
                    $mailing_info['zip'] = $data['mailingzip'];
                }
				break;
			case "Accounts"://We want the account module information
				$household = PortfolioInformation_Module_Model::GetHouseholdEntityFromAccountNumber($accounts[0]);
			    if($household) {
                    $data = $household->getData();
                    $mailing_info['name'] = $data['accountname'] . " Household";
                    $mailing_info['street'] = $data['ship_street'];
                    $mailing_info['city'] = $data['ship_city'];
                    $mailing_info['state'] = $data['ship_state'];
                    $mailing_info['zip'] = $data['ship_code'];
                }
				break;
		}
		return $mailing_info;
	}

	static public function GetPieFromTable($table_name = "PieTable"){
	    global $adb;

	    $query = "SELECT title, value, color FROM {$table_name}";
	    $result = $adb->pquery($query, array());

	    if($adb->num_rows($result) > 0){
	        $pie = array();
	        while($v = $adb->fetchByAssoc($result)){
	            $tmp['title'] = $v['title'];
	            $tmp['value'] = $v['value'];
	            $tmp['color'] = $v['color'];
	            if(is_null($tmp['color']))
	                $tmp['color'] = "#c27b0c";
	            $pie[] = $tmp;
            }
            return $pie;
        }
        return 0;
    }

    /**
     * Takes in the passed in value and calculates the percentage value for the pie chart
     * @param $global_total
     */
    static public function AddPercentageTotalToPie(array $pie_chart, $global_total){
	    foreach($pie_chart AS $k => $v){
	        $pie_chart[$k]['percentage'] = $v['value'] / $global_total * 100;
        }
        return $pie_chart;
    }

    static private function MapHeadingNames(&$headings){
	    global $adb;
	    $questions = generateQuestionMarks($headings);
	    $query = "SELECT field, heading, heading_span_style, heading_td_style, hidden FROM vtiger_table_headings WHERE field IN ({$questions})";
	    $result = $adb->pquery($query, array($headings));
	    if($adb->num_rows($result) > 0){
	        while($v = $adb->fetchByAssoc($result)){
	            $headings[strtolower($v['field'])] = array("heading" => $v['heading'],
                                                           "heading_span_style" => $v['heading_span_style'],
                                                           "heading_td_style" => $v['heading_td_style'],
                                                           "hidden" => $v['hidden']);//strtolower is required because there are times a capital is returned which dosn't string match and adds another element to the array
            }
        }
    }

    /**
     * Uses the passed in array to allow for selected fields.  If nothing is passed in, it selects all
     * @param array|null $elements
     * @return string
     */
    static private function SelectTableFields(array $elements=null){
        if(!$elements)
            $fields = "*";
        else
            $fields = implode(", ", $elements);

        return $fields;
    }

    static public function GetTableHeadings($tablename, array $elements=null){
        global $adb;

        $fields = self::SelectTableFields($elements);

        $query = "SELECT {$fields} FROM {$tablename} LIMIT 1";
        $result = $adb->pquery($query, array());

        $headings = array("heading" => "  ");//The space here gives it a little indenting for the heading (should be done in CSS probably)
        if($adb->num_rows($result) > 0) {
            while ($v = $adb->fetchByAssoc($result)) {
                foreach ($v AS $k => $b) {
                    $headings[$k] = array("heading" => strtolower($k));
                }
            }
            self::MapHeadingNames($headings);
            return $headings;
        }
        return 0;
    }

    static private function GetValueMap(array $elements = null){
        global $adb;

        $questions = generateQuestionMarks($elements);

        $query = "SELECT field, value_span_style, value_td_style, smarty_modifier, smarty_prefix, smarty_suffix, prefix, suffix, cat_prefix, cat_suffix, cat_smarty_modifier, cat_span_style, cat_td_style, cat_smarty_prefix, cat_smarty_suffix, hide_from_total, total_td_style, total_span_style, total_smarty_prefix, total_smarty_suffix, total_smarty_modifier, total_prefix, total_suffix, hidden, html_td_modifiers, value_as_data FROM vtiger_table_values WHERE field IN ($questions)";
        $result = $adb->pquery($query, array($elements));
        if($adb->num_rows($result) > 0){
            while($row = $adb->fetchByAssoc($result)){
                $values[$row['field']] = $row;
            }
            return $values;
        }
        return 0;
    }

    static private function GetFieldsFromRow(array $row){
        $fields = array();
        foreach($row AS $k => $v){
            $fields[] = $k;
        }
        return $fields;
    }

    static public function GetTableCategories($tablename, array $categories = null){
        global $adb;

        if($categories) {
            $fields = self::SelectTableFields($categories);
            $query = "SELECT {$fields} FROM {$tablename} GROUP BY {$fields}";
            $result = $adb->pquery($query, array());
            $cats = array();
            $x = 0;
            if ($adb->num_rows($result) > 0) {
                while ($v = $adb->fetchByAssoc($result)) {
                    $v['category_id'] = $x;
                    $cats[] = $v;
                    $x++;
                }
                return $cats;
            }
        }
        return null;
    }

    /**
     * @param $tablename
     * @param array|null $elements
     * @param array|null $category_list
     * @param null $categories
     * @return array|int
     * Gets the table rows and the category it belongs to.  Category list refers to the actual category fields that make up the categories and the $categories refers to the already calculated categories
     */
    static public function GetTableValues($tablename, array $elements = null, array $category_list = null, array $categories = null){
        global $adb;

        if(!$category_list)
            $merged = $elements;
        else
            $merged = array_merge($elements, $category_list);//Merge the elements and categories so all fields are selected initially... This will be split up later
        $fields = self::SelectTableFields($merged);//Determine the fields to select from the table.  Uses * if nothing passed in

        $query = "SELECT {$fields} FROM {$tablename} ORDER BY {$fields}";
        $result = $adb->pquery($query, array());

        $values = array();
        if($adb->num_rows($result) > 0){
            foreach($result AS $k => $v){//We loop through this way because if we use fetchByAssoc, the token gets screwed up and doesn't return the very first row!
                $f = self::GetFieldsFromRow($v);
                break;
            }
#            $rules = $adb->fetchByAssoc($result, 0);//Get the very first rule so we know what rules are going to be needed for styling/etc
#            $f = self::GetFieldsFromRow($rules);//Get the field names only
            $element_rules = self::GetValueMap($f);//Determine the rules for each individual field.  $f is the field name
            while($row = $adb->fetchByAssoc($result)){
                $tmp = array("");
                foreach($row AS $k => $v){
                    if(in_array($k, $elements)){//Determine the fields to use in the row
                        $tmp[$k] = $v;
                    }
                }
#                print_r($tmp);echo '<br />';
                $expected_count = sizeof($category_list);//We need to match this number to determine the category ID
                $counter = 0;
                if($categories) {
                    foreach ($categories AS $a => $category) {
                        foreach ($category_list AS $k => $heading) {
                            if ($row[$heading] == $category[$heading]) {
#                                echo "{$row[$heading]} equals {$category[$heading]}<br />";
                                $counter++;
                                if ($counter == $expected_count) {
#                                    print_r($row); echo "<br />";
#                                    echo "ROW: " . $row[$heading] . ", CAT: " . $category[$heading] . '<br />';

                                    $cat_id = $category['category_id'];
#echo "WE HAVE OUR CATEGORY FOR {$row['security_symbol']} and it is {$category['category_id']}! HEADING OF {$heading}<br />";
                                }
                            }
                        }
                        $counter = 0;//Reset the counter
                    }
                }else{
                    $cat_id = 0;
                }
                $r = array("fields" => $tmp, "category_id" => $cat_id);
                $values['rows'][] = $r;//Get the row itself for displaying, as well as the category it belongs to
            }
            $values['rules'] = $element_rules;
#            print_r($values);exit;
            return $values;
        }
        return 0;
    }

    /**
     * Attaches the add-on fields without "summing" them
     * @param array $elements
     */
    static private function GetAddonFields(array $elements){
        if(sizeof($elements) <= 0)
            return;
        $q = "";
        foreach($elements AS $k => $v){
            if($k == end(array_keys($elements)))
                $q .= " {$v} AS {$v} ";
            else
                $q .= " {$v} AS {$v}, ";
        }
        return $q;
    }

    /**
     * Gets the SUM of the passed in fields
     */
    static private function GetSumQuery(array $elements){
        if(sizeof($elements) <= 0)
            return;
        $q = "";
        foreach($elements AS $k => $v){
            if($k == end(array_keys($elements)))
                $q .= " SUM({$v}) AS {$v} ";
            else
                $q .= " SUM({$v}) AS {$v}, ";
        }
        return $q;
    }
    /**
     * Gets the SUM of each element
     * @param $tablename
     * @param array|null $elements
     */
    static public function GetTableTotals($tablename, array $elements = null){
        global $adb;
        $q = "";
        if(sizeof($elements) > 0){
            $q = self::GetSumQuery($elements);
            $query = "SELECT {$q} FROM {$tablename}";
            $result = $adb->pquery($query, array());
            if($adb->num_rows($result) > 0){
                while($v = $adb->fetchByAssoc($result)){
                    $row = $v;
                }
                return $row;
            }
        }
        return 0;
    }

    static public function MergeTotalsIntoCategoryRows($categories, &$table, $category_totals){
        $match_num = 0;
        $token = 0;
        while($token < sizeof($table['table_categories'])){
            foreach($category_totals AS $k => $v) {
                foreach ($categories AS $c => $d) {
                    if ($table['table_categories'][$token][$d] == $v[$d]) {
                        $match_num++;
                    }
                }
                if($match_num == sizeof($categories))
                    $table['table_categories'][$token]['totals'] = $v;
                $match_num = 0;
            }
            $token++;
        }
    }

    static public function SetTableCategories($categories, &$table, $category_totals){
        $match_num = 0;
        $token = 0;

        while($token < sizeof($table['table_categories'])){
            foreach($category_totals AS $k => $v) {
                foreach ($categories AS $c => $d) {
                    if ($table['table_categories'][$token][$d] == $v[$d]) {
                        $match_num++;
                    }
                }
                if($match_num == sizeof($categories))
                    $table['table_categories'][$token]['totals'] = $v;
                $match_num = 0;
            }
            $token++;
        }
    }

    static public function GetTableCategoryTotals($tablename, array &$categories = null, array $elements = null, array $extra_fields = null){
        if(sizeof($categories) <= 0 || sizeof($elements) <= 0)
            return 0;

        global $adb;
        $q = self::GetSumQuery($elements);
        if(sizeof($extra_fields) > 0)
            $q .= ", " . self::GetAddonFields($extra_fields);
        $q .= ", " . implode(", ", $categories);

        $group = "";
        foreach($categories AS $k => $v){
            if($k == end(array_keys($categories)))
                $group .= "{$v}";
            else
                $group .= "{$v}, ";
        }

        $query = "SELECT {$q} FROM {$tablename} GROUP BY {$group}";

        $result = $adb->pquery($query, array());
        $row = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $row[] = $v;
            }
            return $row;
        }
        return;
    }

    /**
     * @param $tablename -- The name of the table to generate from
     * @param array|null $elements -- The elements we want from the table
     * @param array|null $categories -- The categories we want the elements to show up under
     * @return array|int
     */
    static public function GetTable($table_title = "", $tablename, array $elements = null, array $categories=null, array $hidden_rows=null){
        $headings = self::GetTableHeadings($tablename, $elements);//The headings used to display the table

        if($headings){
            $cats = self::GetTableCategories($tablename, $categories);
            $values = self::GetTableValues($tablename, $elements, $categories, $cats);
            if(!$cats){
                $cats = array();
                $cats[] = array("no_category" => " ", "category_id" => 0);
#                $cats = array(0 => " ", "category_id" => 0);
            }
            $tmp = array("table_headings" => $headings, "table_values" => $values, "table_categories" => $cats, "table_title" => $table_title);
            if($hidden_rows != null)
                self::RemoveHiddenRowFields($tmp, $hidden_rows);
            return $tmp;
        }
        return 0;
    }

    static public function RemoveHiddenRowFields(&$table, $hidden_rows){
        foreach($table['table_values']['rows'] AS $key => $value){
            foreach($value['fields'] AS $k => $v){
                if(in_array($k, $hidden_rows)){
                    $table['table_values']['rows'][$key]['fields'][$k] = '';
                }
            }
        }
    }

    static public function GeneratePositionsValuesTable($account_numbers, $as_of_date){
        global $adb, $dbconfig;
        $db_name = $dbconfig['db_name'];
        $custodianDB = $dbconfig['custodianDB'];

        $questions = generateQuestionMarks($account_numbers);
        $params = array();

        $params[] = $account_numbers;
        $params[] = $as_of_date;
        $params[] = $db_name;
#        $params[] = $custodianDB;
        $query = "CALL CREATE_POSITIONS_VALUES_TABLE(\"{$questions}\", ?, ?)";
#        $query = "CALL CREATE_POSITIONS_VALUES_TABLE(\"'939741719'\", '2017-12-31', 'live_omniscient');";
        $adb->pquery($query, $params, true);
    }

    static public function GetPositionValuesPie(){
        global $adb;
        $query = "SELECT * FROM PositionValuesPie";
        $result = $adb->pquery($query, array());
        $pie = array();
        $defaultPalette = array("#292447", "#f27b0c", "#9bb556", "#eab378", "#407fe5", "#478899", "#5cafc4", "#ff1a1a", "#2b4224");
        $i = 0;
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                if(empty($v['color']) || $v['color'] == 'null'){
                    $v['color'] = $defaultPalette[$i % count($defaultPalette)];
                }
                $pie[] = $v;
                $i++;
            }
            return $pie;
        }
        return 0;
    }

    static public function GetPositionSectorsPie(){
        global $adb;
        $query = "SELECT * FROM PositionValuesSector";
        $result = $adb->pquery($query, array());
        $pie = array();
        $defaultPalette = array("#292447", "#f27b0c", "#9bb556", "#eab378", "#407fe5", "#478899", "#5cafc4", "#ff1a1a", "#2b4224");
        $i = 0;
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                if(empty($v['color']) || $v['color'] == 'null'){
                    $v['color'] = $defaultPalette[$i % count($defaultPalette)];
                }
                $pie[] = $v;
                $i++;
            }
            return $pie;
        }
        return 0;
    }

    static public function GetPositionsFromValuesTable(){
        global $adb;
        $query = "SELECT * FROM PositionValues p 
                  JOIN vtiger_modsecurities m ON p.symbol = m.security_symbol
                  JOIN vtiger_modsecuritiescf cf USING (modsecuritiesid)";
        $result = $adb->pquery($query, array());
        $row = array();

        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)){
                $row[] = $v;
            }
            return $row;
        }
        return 0;
    }
}

?>