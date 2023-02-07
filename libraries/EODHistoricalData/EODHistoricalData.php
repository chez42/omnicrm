<?php


class EODHistoricalData {
    private $eodUrl;
    private $exchange;
    private $options = array();

    public function __construct($format='json', $exchange = "US", $apiToken = "63c9aa8ba1bfa1.20321122") {
        $this->exchange = $exchange;
        $this->options['api_token'] = $apiToken;
        if (isset($format)) {
            switch ($format) {
                case 'json':
                    $this->options['fmt'] = 'json';
                    break;
            }
        }
    }

    public function getSymbolPricing($symbol, $start, $end){
        $options = $this->options;
        $options['from'] = $start;
        $options['to'] = $end;
        $options['period'] = "d";
        $this->eodUrl = "https://eodhistoricaldata.com/api/eod/{$symbol}." . $this->exchange;
        return $this->execQuery($options);
    }

    public function getSymbolData($symbol){
        $this->eodUrl = "https://eodhistoricaldata.com/api/fundamentals/{$symbol}." . $this->exchange;
        $options = $this->options;
        return $this->execQuery($options);
    }

    public function getSymbolDividends($symbol, $from, $to){
        $this->eodUrl = "https://eodhistoricaldata.com/api/div/{$symbol}." . $this->exchange;
        $options = $this->options;
        $options['from'] = $from;
        $options['to'] = $to;
        return $this->execQuery($options);
    }

    public function getHistoricalData($symbol, $startDate, $endDate) {
        $this->eodUrl = "https://eodhistoricaldata.com/api/eod/{$symbol}." . $this->exchange;
        if (is_object($startDate) && get_class($startDate) == 'DateTime') {
            $startDate = $this->dateToDBString($startDate);
        }
        if (is_object($endDate) && get_class($endDate) == 'DateTime') {
            $endDate = $this->dateToDBString($endDate);
        }

        $options = $this->options;
        $options['from'] = $startDate;
        $options['to'] = $endDate;
        $options['period'] = 'd';

//        $options['q'] = "env 'store://datatables.org/alltableswithkeys'; select * from yahoo.finance.historicaldata where startDate='{$startDate}' and endDate='{$endDate}' and symbol='{$symbol}'";

        return $this->execQuery($options);
    }

    public function getQuotes($symbols) {
        if (is_string($symbols)) {
            $symbols = array($symbols);
        }

        $options = $this->options;
        $options['q'] = "env 'store://datatables.org/alltableswithkeys'; select * from yahoo.finance.quotes where symbol in ('" . implode("','", $symbols) . "')";

        return $this->execQuery($options);
    }

    public function getQuotesList($symbols) {
        if (is_string($symbols)) {
            $symbols = array($symbols);
        }

        $options = $this->options;
        $options['q'] = "env 'store://datatables.org/alltableswithkeys'; select * from yahoo.finance.quoteslist where symbol in ('" . implode("','", $symbols) . "')";

        return $this->execQuery($options);
    }
//https://eodhistoricaldata.com/api/eod/AAPL.US?from=2017-01-05&to=2017-02-10&api_token=OeAFFmMliFG5orCUuwAKQ8l4WWFQ67YX&period=d&fmt=json
//https://eodhistoricaldata.com/api/eod/AAPL.US?from=2017-01-01&to=2017-08-02
    private function execQuery($options) {
        $eod_query_url = $this->getUrl($options);
        $session = curl_init($eod_query_url);
        curl_setopt($session, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($session);
        return $result;//curl_exec($session);
    }

    private function getUrl($options) {
        $url = $this->eodUrl;
        $i=0;
        foreach ($options as $k => $qstring) {
            if ($i==0) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= "$k=" . urlencode($qstring);
            $i++;
        }
        return $url;
    }

    private function dateToDBString($date) {
        assert('is_object($date) && get_class($date) == "DateTime"');

        return $date->format('Y-m-d');
    }




}
