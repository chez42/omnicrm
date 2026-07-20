<?php
class CustodianClassMapping{
    public $portfolios, $positions, $securities, $prices, $transactions;

    public function __construct($account_number){
        $custodian = PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($account_number);

        switch(strtoupper($custodian)){
            case "TD":
                $this->portfolios = "cTDPortfolios"; $this->positions = "cTDPositions";
                $this->securities = "cTDSecurities"; $this->prices = "cTDPrices";
                $this->transactions = "cTDTransactions";
                break;
            case "FIDELITY":
                $this->portfolios = "cFidelityPortfolios"; $this->positions = "cFidelityPositions";
                $this->securities = "cFidelitySecurities"; $this->prices = "cFidelityPrices";
                $this->transactions = "cFidelityTransactions";
                break;
            case "SCHWAB":
                $this->portfolios = "cSchwabPortfolios"; $this->positions = "cSchwabPositions";
                $this->securities = "cSchwabSecurities"; $this->prices = "cSchwabPrices";
                $this->transactions = "cSchwabTransactions";
                break;
            case "PERSHING":
                $this->portfolios = "cPershingPortfolios"; $this->positions = "cPershingPositions";
                $this->securities = "cPershingSecurities"; $this->prices = "cPershingPrices";
                $this->transactions = "cPershingTransactions";
                break;
            case "AXOS":
                $this->portfolios = "cAxosPortfolios"; $this->positions = "cAxosPositions";
                $this->securities = "cAxosSecurities"; $this->prices = "cAxosPrices";
                $this->transactions = "cAxosTransactions";
                break;
            DEFAULT:
                $this->portfolios = "cTDPortfolios"; $this->positions = "cTDPositions";
                $this->securities = "cTDSecurities"; $this->prices = "cTDPrices";
                $this->transactions = "cTDTransactions";
        }
    }
}