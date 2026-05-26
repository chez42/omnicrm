<?php

class cPortfolioCenter{
    private $server;
    private $user;
    private $pass;
    private $db;
    private $dbhandle;
    
    /**Convert the sql date to a proper format*/
    public function ConvertDate($date)
    {   
        $time = strtotime($date);
        $time = date('Y-m-d 00:00:00', $time);
        return $time;
    }
    
    /**
     * Converts the data to a proper format, keeping the time in tact
     * @param type $date
     * @return type
     */
    public function ConvertDateKeepingTime($date)
    {   
        $time = strtotime($date);
        $time = date('Y-m-d H:i:s', $time);
        return $time;
    }
    public function __construct($server="lanserver2n", $user="syncuser", $pass="Consec11", $db="PortfolioCenter") {
        $this->server = $server;
        $this->user = $user;
        $this->pass = $pass;
        $this->db = CPortfolioCenter::GetDatabase();//"PortfolioCenter";$db;
    }
    
    public function connect(){
        if (!function_exists('mssql_connect')) {
            return 0;
        }
        $this->dbhandle = mssql_connect($this->server, $this->user, $this->pass);// or die("Couldn't connect to SQL Server on $myServer");
        if(!$this->dbhandle)
            return 0;
        else
            return 1;
    }

    public function PDOConnect(){
        try {
            $opt = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->dbhandle = new PDO("dblib:host=$this->server;dbname=$this->db", $this->user, $this->pass, $opt);
            if(!$this->dbhandle)
                return 0;
        }catch(PDOException $e){
            echo "Failed to get DB handle: " . $e->getMessage() . '<br />';
        }
        return $this->dbhandle;
    }

    public function PDOCloseConnection(){
        $this->dbhandle = null;
    }

    public function GetPDOHandle(){
        return $this->dbhandle;
    }

    static public function GetDatasets(){
        global $adb;
        $query = "SELECT datasets FROM vtiger_datasets";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "datasets");
        }
    }

    static public function GetDatabase(){
        global $adb;
        $query = "SELECT database_name FROM vtiger_datasets";
        $result = $adb->pquery($query, array());
        if($adb->num_rows($result) > 0){
            return $adb->query_result($result, 0, "database_name");
        }
    }

}

?>
