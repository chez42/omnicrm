<?php
/**
 * Created by PhpStorm.
 * User: ryansandnes
 * Date: 2019-02-21
 * Time: 4:22 PM
 */

require_once("vendor/autoload.php");

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;

DEFINE("URI_DEFAULT","http://lanserver24.concertglobal.com:8085/OmniServ/");

class FileParsing{
    private $custodian, $parse_type, $num_days, $parse_program, $rep_code;
    private $guz;
    private $url_extension, $file_extensions;
    private $tenant, $user, $password, $connection, $dbname, $vtDBName, $dont_ignore_if_exists;
    public function __construct($custodian, $parse_type, $num_days = 7, $dont_ignore_if_exists=0, $rep_code){
        global $dbconfig;
        $this->custodian = $custodian;
        $this->parse_type = $parse_type;
        $this->num_days = $num_days;
        $this->tenant = "Omniscient";
        $this->user = $dbconfig['db_username'];
        $this->password = $dbconfig['db_password'];
        $this->connection = $dbconfig['db_server'];
        $this->dbname = "custodian_omniscient";
        $this->vtDBName = $dbconfig['db_name'];
        $this->rep_code = $rep_code;
        $this->dont_ignore_if_exists = $dont_ignore_if_exists;

        $this->DetermineParseProgram();
        $this->DetermineExtension();

        $this->guz = new Guzzle();
    }

    private function DetermineParseProgram(){
        switch(strtolower($this->custodian)){
            case "td":
                $this->parse_program = "TDParse?";
                break;
            case "fidelity":
                $this->parse_program = "FidelityParse?";
                break;
            case "schwab":
                $this->parse_program = "SchwabParse?";
                break;
            case "pershing":
                $this->parse_program = "PershingParse?";
                break;
        }
    }

    private function DetermineExtension(){
        global $adb;
        $query = "SELECT extension FROM custodian_omniscient.parse_mapping WHERE parse_type = ? AND custodian = ?";
        $result = $adb->pquery($query, array($this->parse_type, $this->custodian));
        $extensions = array();

        if($adb->num_rows($result) > 0){
            while($row = $adb->fetchByAssoc($result)){

#                echo $row['extension'];
                if( strpos($row['extension'], ',') !== false ) {
                    $extensions = explode(',', $row['extension']);
                }else
                    $extensions[] = $row['extension'];
            }
        }else{
            return 0;
        }

        $this->file_extensions = $extensions;
        return 1;
    }

    private function FillURL($file_extension){
        $extend = "";
        if(strtolower($file_extension != "none")){
            $extend = "&extension=" . $file_extension;
        }
        $this->url_extension = $this->parse_program .
            "custodian=" . $this->custodian .
            "&tenant=" . $this->tenant .
            "&user=" . $this->user .
            "&password=" . $this->password .
            "&connection=" . $this->connection .
            "&dbname=" . $this->dbname .
            "&vtigerDBName=" . $this->vtDBName .
            "&skipDays=" . $this->num_days .
            "&dontIgnoreFileIfExists=" . $this->dont_ignore_if_exists .
            "&repcode=" . $this->rep_code .
            "&operation=writefiles" .
            $extend;
    }

    public function parseFiles(){
        foreach($this->file_extensions AS $k => $v){
            $this->FillURL($v);
            $url = URI_DEFAULT . $this->url_extension;
            StatusUpdate::UpdateMessage("MANUALPARSING", "Parsing data for " .
                    $this->custodian . " using Rep Code " .
                    $this->rep_code . " File Type " . $v
            );
            $res = $this->guz->get($url);
        }
        StatusUpdate::UpdateMessage("MANUALPARSING", "Parsing Complete!  Updating last position date for data");

        switch(strtolower($this->custodian)){
            case "fidelity":
                $date_field = "as_of_date";
                break;
            case "pershing":
                $date_field = "position_date";
                break;
            default:
                $date_field = "date";
                break;
        }
        $url = "http://synctest.360vew.com/lh/RepCodeDates.php?custodian={$this->custodian}&date_field={$date_field}&rep_code={$this->rep_code}";
        $res = $this->guz->get($url);
        StatusUpdate::UpdateMessage("MANUALPARSING", "{$this->rep_code} should now have latest data");
    }
}
