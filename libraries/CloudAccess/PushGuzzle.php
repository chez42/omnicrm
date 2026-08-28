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

class cPushGuzzle{
    private $custodian;
    private $guz;
    private $tenant, $user, $password, $connection, $dbname, $vtDBName;
    private $operation, $parse_program;
    public function __construct($custodian, $operation){
        $this->custodian = $custodian;
        global $dbconfig;
        $this->tenant = "Omniscient";
        $this->user = $dbconfig['db_username'];
        $this->password = $dbconfig['db_password'];
        $this->connection = $dbconfig['db_server'];
        $this->dbname = "custodian_omniscient";
        $this->vtDBName = "live_omniscient";

        $this->DetermineParseProgram();
        $this->DetermineOperation($operation);
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

    private function DetermineOperation($operation){
        switch(strtolower($operation)){
            case "push_securities":
                $this->operation = "updatesecurities";
                break;
            case "push_positions":
                $this->operation = "updatepositions";
                break;
            case "push_portfolios":
                $this->operation = "updateportfolios";
                break;
            case "push_transactions":
                $this->operation = "updatetransactions";
        }
        global $adb;
        $query = "SELECT extension FROM parse_mapping WHERE parse_type = ? AND custodian = ?";
        $result = $adb->pquery($query, array($this->parse_type, $this->custodian));
        $extensions = array();
        if($adb->num_rows($result) > 0){
            while($row = $adb->fetchByAssoc($result)){
                $extensions[] = $row['extension'];
            }
        }else{
            return 0;
        }
        $this->file_extensions = $extensions;
        return 1;
    }

    private function FillURL(){
        $this->url_extension = $this->parse_program .
            "custodian=" . $this->custodian .
            "&tenant=" . $this->tenant .
            "&user=" . $this->user .
            "&password=" . $this->password .
            "&connection=" . $this->connection .
            "&dbname=" . $this->dbname .
            "&vtigerDBName=" . $this->vtDBName .
            "&operation=" . $this->operation;
    }

    public function pushFiles(){
        $this->FillURL();
        $url = URI_DEFAULT . $this->url_extension;
        $res = $this->guz->get($url);
        return $res;
    }
}