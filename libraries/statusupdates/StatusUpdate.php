<?php


class StatusUpdate{
    static public function UpdateMessage($code, $message, $database=null){
        global $adb, $dbconfig;
        if($database == null)
            $database = $dbconfig['db_name'];

        $query = "INSERT INTO {$database}.StatusUpdate (code, message, last_update) VALUES (?, ?, NOW())
                  ON DUPLICATE KEY UPDATE message = VALUES(message), last_update = NOW()";
        $adb->pquery($query, array($code, $message), true);
    }

    static public function ReadMessage($code, $database=null){
        global $adb, $dbconfig;
        if($database == null)
            $database = $dbconfig['db_name'];

        $query = "SELECT message, last_update FROM {$database}.StatusUpdate WHERE code = ?";
        $result = $adb->pquery($query, array($code));
        $message = $adb->query_result($result, 0, 'message');
        $last_update = $adb->query_result($result, 0, 'last_update');
        if(strtolower($message) == 'finished'){
            return "finished";
        }else
            return $message . " - " . $last_update;
    }
}
