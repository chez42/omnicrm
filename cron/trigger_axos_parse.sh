#!/bin/bash
EXTENSIONS=("REG" "SEC" "PRI" "POS" "TRN" "CBU" "CBL" "REP" "BKR")
for ext in "${EXTENSIONS[@]}"; do
  echo "Parsing extension: $ext"
  curl -s "http://127.0.0.1:8085/OmniServ/AutoParse?custodian=axos&tenant=custodian_omniscient&user=root&password=Consec8439&connection=172.31.55.203&dbname=custodian_omniscient&vtigerDBName=live_omniscient&skipDays=7&dontIgnoreFileIfExists=1&operation=writefiles&extension=$ext"
  echo ""
done
