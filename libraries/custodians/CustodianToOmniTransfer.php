<?php
DEFINE("TD", "TD");
DEFINE("FIDELITY", "Fidelity");
DEFINE("SCHWAB", "Schwab");
DEFINE("PERSHING", "Pershing");
DEFINE("AXOS", "Axos");


class CustodianToOmniTransfer{
    private $account_numbers;

    public function __construct(array $account_number){
        $this->account_numbers = array();
        foreach($account_number AS $k => $v){
            $custodian = PortfolioInformation_Module_Model::GetCustodianFromAccountNumber($v);
            switch (strtoupper($custodian)) {
                case "TD":
                    $this->account_numbers[TD][] = $v;
                    break;
                case "FIDELITY":
                    $this->account_numbers[FIDELITY][] = $v;
                    break;
                case "SCHWAB":
                    $this->account_numbers[SCHWAB][] = $v;
                    break;
                case "PERSHING":
                    $this->account_numbers[PERSHING][] = $v;
                    break;
                case "AXOS":
                    $this->account_numbers[AXOS][] = $v;
                    break;
            }
        }
    }

    public function GetAccounts(){
        return $this->account_numbers;
    }

    /**
     * Pull the latest positions from Custodian Omniscient and insert them into Omniscient
     */
    public function UpdatePositions()
    {
        foreach($this->account_numbers AS $k => $v){
            switch (strtoupper($k)) {
                CASE "TD":
                    self::CreateSecurities();
                    cTDPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                    break;
                CASE "FIDELITY":
                    cFidelityPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                break;
                CASE "SCHWAB":
                    break;
                case "PERSHING":
                    break;
                CASE "AXOS":
                    cAxosPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                    break;
            }
        }
    }

    /**
     * Pull the latest positions from Custodian Omniscient and insert them into Omniscient
     */
    public function CreatePositions()
    {
        foreach($this->account_numbers AS $k => $v){
            switch (strtoupper($k)) {
                CASE "TD":
                    cTDPositions::CreateNewPositionsForAccounts($v);
                    cTDPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                    break;
                CASE "FIDELITY":
                    cFidelityPositions::CreateNewPositionsForAccounts($v);
                    cFidelityPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                    break;
                CASE "SCHWAB":
                    cSchwabPositions::CreateNewPositionsForAccounts($v);
                    cSchwabPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                    break;
                case "PERSHING":
                    break;
                CASE "AXOS":
                    cAxosPositions::CreateNewPositionsForAccounts($v);
                    cAxosPositions::UpdateAllCRMPositionsAtOnceForAccounts($v);
                    break;
            }
        }
    }

    public function CreateTransactions(){
        foreach($this->account_numbers AS $k => $v){
            switch (strtoupper($k)) {
                CASE "TD":
                    cTDTransactions::CreateNewTransactionsForAccounts($v);
                    break;
                CASE "FIDELITY":
                    cFidelityTransactions::CreateNewTransactionsForAccounts($v);
                    break;
                CASE "SCHWAB":
                    cSchwabTransactions::CreateNewTransactionsForAccounts($v);
                    break;
                case "PERSHING":
                    break;
                CASE "AXOS":
                    cAxosTransactions::CreateNewTransactionsForAccounts($v);
                    break;
            }
        }
    }

    public function CreatePortfolios(){
        return;//Currently not used, the code below is a copy of UpdatePortfolios
        foreach($this->account_numbers AS $k => $v){
            switch (strtoupper($k)) {
                CASE "TD":
                    cTDPortfolios::UpdateAllPortfoliosForAccounts($v);
                    break;
                CASE "FIDELITY":
                    cFidelityPortfolios::UpdateAllPortfoliosForAccounts($v);
                    break;
                CASE "SCHWAB":
                    cSchwabPortfolios::UpdateAllPortfoliosForAccounts($v);
                    break;
                case "PERSHING":
                    break;
            }
        }
    }

    /**
     * Pull the latest portfolios from Custodian Omniscient and insert them into Omniscient
     */
    public function UpdatePortfolios()
    {
        foreach($this->account_numbers AS $k => $v){
            switch (strtoupper($k)) {
                CASE "TD":
                    cTDPortfolios::UpdateAllPortfoliosForAccounts($this->account_numbers[TD]);
#                    cTDPortfolios::UpdateAllPortfoliosForAccounts($v);
                    break;
                CASE "FIDELITY":
                    cFidelityPortfolios::UpdateAllPortfoliosForAccounts($this->account_numbers[FIDELITY]);
#                    cFidelityPortfolios::UpdateAllPortfoliosForAccounts($v);
                    break;
                CASE "SCHWAB":
                    cSchwabPortfolios::UpdateAllPortfoliosForAccounts($this->account_numbers[SCHWAB]);
                    break;
                case "PERSHING":
                    break;
                CASE "AXOS":
                    cAxosPortfolios::UpdateAllPortfoliosForAccounts($this->account_numbers[AXOS]);
                    break;
            }
        }
    }

    public function CreateSecurities()
    {
        foreach ($this->account_numbers AS $k => $v) {
            switch (strtoupper($k)) {
                CASE "TD":
                    $symbols = cTDPositions::GetSymbolListFromCustodian($v);//Get a list of existing positions

                    if(empty($symbols))
                        return;

                    $missing_symbols = ModSecurities_Module_Model::GetMissingSymbolsFromList($symbols);//Get list of securities that don't exist in the CRM but do have positions
                    if(!empty($missing_symbols)) {
                        cTDSecurities::CreateNewSecurities($missing_symbols);//Create securities in the CRM
                    }
                    cTDSecurities::UpdateAllSymbolsAtOnce($symbols);
                    break;

                CASE "FIDELITY":
                    $symbols = cFidelityPositions::GetSymbolListFromCustodian($v);//Get a list of existing positions

                    if(empty($symbols))
                        return;

                    $missing_symbols = ModSecurities_Module_Model::GetMissingSymbolsFromList($symbols);//Get list of securities that don't exist in the CRM but do have positions
                    if(!empty($missing_symbols)) {
                        cFidelitySecurities::CreateNewSecurities($missing_symbols);//Create securities in the CRM
                    }
                    cFidelitySecurities::UpdateAllSymbolsAtOnce($symbols);
                    break;

                CASE "SCHWAB":
                    $symbols = cSchwabPositions::GetSymbolListFromCustodian($v);//Get a list of existing positions

                    if(empty($symbols))
                        return;

                    $missing_symbols = ModSecurities_Module_Model::GetMissingSymbolsFromList($symbols);//Get list of securities that don't exist in the CRM but do have positions
                    if(!empty($missing_symbols)) {
                        cSchwabSecurities::CreateNewSecurities($missing_symbols);//Create securities in the CRM
                    }
                    cSchwabSecurities::UpdateAllSymbolsAtOnce($symbols);

                    break;
                case "PERSHING":
                    break;
                CASE "AXOS":
                    $symbols = cAxosPositions::GetSymbolListFromCustodian($v);
                    if(empty($symbols))
                        return;
                    $missing_symbols = ModSecurities_Module_Model::GetMissingSymbolsFromList($symbols);
                    if(!empty($missing_symbols)) {
                        cAxosSecurities::CreateNewSecurities($missing_symbols);
                    }
                    cAxosSecurities::UpdateAllSymbolsAtOnce($symbols);
                    break;
            }
        }
    }

    public function UpdateSecurities(){
        foreach ($this->account_numbers AS $k => $v) {
            switch (strtoupper($k)) {
                CASE "TD":
                    $symbols = cTDPositions::GetSymbolListFromCustodian($v);//Get a list of existing positions
                    cTDSecurities::UpdateAllSymbolsAtOnce($symbols);
                    break;
                CASE "FIDELITY":
                    $symbols = cFidelityPositions::GetSymbolListFromCustodian($v);//Get a list of existing positions
                    cFidelitySecurities::UpdateAllSymbolsAtOnce($symbols);
#                    $symbols = cFidelityPositions::GetSymbolListFromCustodian($v);

#                    $symbols = cFidelityPositions::GetSymbolListFromCustodian($v);//Get a list of existing positions
#                    cFidelityPositions::
#                    $missing_symbols = ModSecurities_Module_Model::GetMissingSymbolsFromList($symbols);//Get list of securities that don't exist in the CRM but do have positions
#                    cFidelitySecurities::CreateNewSecurities($missing_symbols);//Create securities in the CRM
                    break;
                CASE "SCHWAB":
                    break;
                case "PERSHING":
                    break;
            }
        }
    }
}