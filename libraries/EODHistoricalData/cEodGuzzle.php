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

DEFINE("URI_PRICING","https://eodhistoricaldata.com/api/eod");
DEFINE("URI_REALTIME","https://eodhistoricaldata.com/api/real-time");
DEFINE("URI_BONDS","https://eodhistoricaldata.com/api/bond-fundamentals");
DEFINE("URI_FUNDAMENTALS","https://eodhistoricaldata.com/api/fundamentals");
DEFINE("URI_DIVIDENDS", "https://eodhistoricaldata.com/api/div");
DEFINE("URI_OPTIONS","https://eodhistoricaldata.com/api/options");
DEFINE("URI_LOGOS","https://eodhistoricaldata.com");
DEFINE("URI_TICKER","https://eodhistoricaldata.com/api/exchange-symbol-list");
DEFINE("URI_EXCHANGES","https://eodhistoricaldata.com/api/exchanges-list");

class cEodGuzzle{
    public $api_token, $uri_symbol;
    private $guz;
    public function __construct($exchange = "US", $apiToken = "63c9aa8ba1bfa1.20321122"){
        $this->api_token = $apiToken;
        $this->exchange = $exchange;
        $this->uri_symbol =

        $this->guz = new Guzzle();
    }

    public function getSymbolPricing($symbol, $start, $end, $exchange = 'US'){
        $options['from'] = $start;
        $options['to'] = $end;
        $options['period'] = "d";
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';

        $headers = ['test' => 'testing'];

        $res = $this->guz->get(URI_PRICING . "/{$symbol}.{$exchange}", ['query' => $options]);
        return $res->getBody()->getContents();
#        $request = new Request("GET", $this->uri_symbol . "/{$symbol}.{$exchange}");

//        $res = $this->guz->Request("GET", $this->uri_symbol . "/{$symbol}.{$exchange}");//?api_token={$this->api_token}")->getBody()->getContents();
#        echo $request->getUri();
#        $request->
#        $query = $this->guz->getQuery();
#        echo $query;exit;
        /*
                $options = $this->options;
                $options['from'] = $start;
                $options['to'] = $end;
                $options['period'] = "d";
                $this->eodUrl = "https://eodhistoricaldata.com/api/eod/{$symbol}." . $this->exchange;
                return $this->execQuery($options);*/
    }

    public function getSymbolRealTimePricing($symbol, $exchange = 'US'){
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';

        try {
            $res = $this->guz->get(URI_REALTIME . "/{$symbol}.{$exchange}", ['query' => $options]);
        }catch(Exception $e){
            return null;
        }
        return $res->getBody()->getContents();
    }

    public function getFundamentals($symbol, $exchange = 'US'){
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';
        try {
            $res = $this->guz->get(URI_FUNDAMENTALS . "/{$symbol}.{$exchange}", ['query' => $options]);
        }catch(Exception $e){//Symbol not found (404 error causes an exception)
            return null;
        }
        return $res->getBody()->getContents();
    }

    public function getCorporateBond($symbol){
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';
        try {
            $res = $this->guz->get(URI_BONDS . "/{$symbol}", ['query' => $options]);
        }catch(Exception $e){
            return null;
        }
        return $res->getBody()->getContents();
    }

    public function getBonds($symbol){
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';
        try {
            $res = $this->guz->get(URI_BONDS . "/{$symbol}", ['query' => $options]);
        }catch(Exception $e){
            return null;
        }
        return $res->getBody()->getContents();
    }

    public function getOptions($symbol, $exchange = 'US'){
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';
        try {
            $res = $this->guz->get(URI_OPTIONS . "/{$symbol}.{$exchange}", ['query' => $options]);
        }catch(Exception $e){
            return null;
        }
        return $res->getBody()->getContents();
    }

    public function getOptionContract($option, $exchange = 'US'){
        $options['api_token'] = $this->api_token;
        $options['from'] = "2000-01-01";
        $options['contract_name'] = str_replace(" ", "", $option);//Remove spaces for EOD purposes
        $options['fmt'] = 'json';
        $symbol = OptionsMapping::GetSymbolFromStandardizedOption($option);
        try {
            $res = $this->guz->get(URI_OPTIONS . "/{$symbol}", ['query' => $options]);
        }catch(Exception $e){
            return null;
        }
        return $res->getBody()->getContents();
    }


    public function getDividends($symbol, $exchange = 'US', $from, $to){
        $options['api_token'] = $this->api_token;
        $options['fmt'] = 'json';
        $options['from'] = $from;
        $options['to'] = $to;
        try {
            $res = $this->guz->get(URI_DIVIDENDS . "/{$symbol}.{$exchange}", ['query' => $options]);
        }catch(Exception $e){
            return null;
        }
        return $res->getBody()->getContents();
    }

    public function getTickers($exchange_code = "US"){
        $options['api_token'] = $this->api_token;
        try {
            $res = $this->guz->get(URI_TICKER . "/{$exchange_code}", ['query' => $options]);
        }catch(Exception $e){
            echo 'no result';
            return null;
        }
        return $res->getBody()->getContents();
    }

    /**
     * Writes the getTickers result into the database
     * @param $tickers
     */
    public function writeTickers($tickers){
        global $adb;
        $separator = "\r\n";
        $line = strtok($tickers, $separator);//Separate the string into lines

        while ($line !== false) {
            $line = strtok( $separator );//Get the line
            $params = str_getcsv($line);//Separate the line's CSV into an array
            $questions = generateQuestionMarks($params);
            $query = "INSERT INTO custodian_omniscient.eod_securities (code, name, country, exchange, currency, type)
                      VALUES ({$questions})
                      ON DUPLICATE KEY UPDATE type = VALUES(type)";
            $adb->pquery($query, $params);
        }
    }

    public function GetExchanges(){
        $options['api_token'] = $this->api_token;
        try {
            $res = $this->guz->get(URI_EXCHANGES . "/", ['query' => $options]);
        }catch(Exception $e){
            echo 'no result';
            return null;
        }
        return $res->getBody()->getContents();
    }
}
