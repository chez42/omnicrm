<?php
class PortfolioInformation_Indexes_Model extends Vtiger_Module {

    static public function SavePreferences($preferences){
        global $adb;
        $current_user = Users_Record_Model::getCurrentUserModel();
        $str = implode (",", $preferences);

        $query = "INSERT INTO vtiger_user_index_settings SET userid = ?, indexids = ? ON DUPLICATE KEY UPDATE indexids=VALUES(indexids)";
        $adb->pquery($query, array($current_user->getId(), $str));
    }

    static public function GetIndexPreferences(){
        global $adb;
        $current_user = Users_Record_Model::getCurrentUserModel();
        $query = "SELECT indexids
                  FROM vtiger_user_index_settings
                  WHERE userid = ?";
        $result = $adb->pquery($query, array($current_user->getId()));
        $list = array();
        if($adb->num_rows($result) > 0){
            $ids = $adb->query_result($result, 0, "indexids");
        }
        return explode(',', $ids);
    }

    static public function GetSelectedIndexes(){
        global $adb;
        $ids = array("102", "328");// Standardized firm-wide: GSPC (102) and AGG (328)
        $questions = generateQuestionMarks($ids);
        $query = "SELECT symbol_id, symbol, description, security_symbol
                  FROM vtiger_index_list
                  WHERE symbol_id IN ({$questions})";
        $result = $adb->pquery($query, array($ids));
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $list[$v['symbol_id']] = $v;
            }
        }
        return $list;
    }

    static public function GetIndexList(){
        global $adb;
        $query = "SELECT symbol_id, symbol, description, security_symbol, capitalization, style, international, sector, base_asset_class
                  FROM vtiger_index_list";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $list[$v['security_symbol']] = $v;
            }
        }
        return $list;
    }

    static public function GetIndexListFiltered($filter_by = null){
        $list = self::GetIndexList();
        $filtered = array();
        foreach($list as $symbol => $v){
            $filtered[$v[$filter_by]][] = $v;
        }
        return $filtered;
    }

    static public function GetCapitalizationList(){
        global $adb;
        $query = "SELECT symbol_id, capitalization FROM vtiger_index_list GROUP BY capitalization";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $tmp = array();
                $tmp['id'] = $v['symbol_id'];
                $tmp['title'] = $v['capitalization'];
                $list[] = $tmp;
            }
        }
        return $list;
    }

    static public function GetStyleList(){
        global $adb;
        $query = "SELECT symbol_id, style FROM vtiger_index_list GROUP BY style";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $tmp = array();
                $tmp['id'] = $v['symbol_id'];
                $tmp['title'] = $v['style'];
                $list[] = $tmp;
            }
        }
        return $list;
    }

    static public function GetInternationalList(){
        global $adb;
        $query = "SELECT symbol_id, international FROM vtiger_index_list GROUP BY international";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $tmp = array();
                $tmp['id'] = $v['symbol_id'];
                $tmp['title'] = $v['international'];
                $list[] = $tmp;
            }
        }
        return $list;
    }

    static public function GetSectorList(){
        global $adb;
        $query = "SELECT symbol_id, sector FROM vtiger_index_list GROUP BY sector";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $tmp = array();
                $tmp['id'] = $v['symbol_id'];
                $tmp['title'] = $v['sector'];
                $list[] = $tmp;
            }
        }
        return $list;
    }

    static public function GetBaseAssetClassList(){
        global $adb;
        $query = "SELECT symbol_id, base_asset_class FROM vtiger_index_list GROUP BY base_asset_class";
        $result = $adb->pquery($query, array());
        $list = array();
        if($adb->num_rows($result) > 0){
            while($v = $adb->fetchByAssoc($result)) {
                $tmp = array();
                $tmp['id'] = $v['symbol_id'];
                $tmp['title'] = $v['base_asset_class'];
                $list[] = $tmp;
            }
        }
        return $list;
    }
}