USE custodian_omniscient;

DROP PROCEDURE IF EXISTS CONSOLIDATE_BALANCES_DEFINED;

DELIMITER //

CREATE DEFINER=`root`@`%` PROCEDURE CONSOLIDATE_BALANCES_DEFINED(
    IN inAccountNumber VARCHAR(65535),
    IN inCrmDB VARCHAR(50),
    IN startDate DATE,
    IN endDate DATE)
this_proc:BEGIN
DECLARE fidelity VARCHAR(5000);
DECLARE schwab VARCHAR(5000);
DECLARE pershing VARCHAR(5000);
DECLARE td VARCHAR(5000);
DECLARE folio VARCHAR(5000);
DECLARE axos VARCHAR(5000);

SET @fidelity := concat("INSERT INTO ", inCrmDB, ".consolidated_balances
SELECT account_number, net_worth, as_of_date
FROM custodian_balances_fidelity WHERE as_of_date BETWEEN '", startDate, "' AND '", endDate, "' 
AND account_number IN (", inAccountNumber, ") 
ON DUPLICATE KEY UPDATE account_value = VALUES(account_value);");

prepare stmt from @fidelity;
execute stmt;



SET @folio := concat("INSERT INTO ", inCrmDB, ".consolidated_balances
SELECT account_number, account_value, as_of_date
FROM custodian_balances_folio WHERE as_of_date BETWEEN '", startDate, "' AND '", endDate, "' 
AND account_number IN (", inAccountNumber, ") 
ON DUPLICATE KEY UPDATE account_value = VALUES(account_value);");

prepare stmt from @folio;
execute stmt;



SET @pershing := concat("INSERT INTO ", inCrmDB, ".consolidated_balances
SELECT account_number, net_worth, date
FROM custodian_balances_pershing WHERE date BETWEEN '", startDate, "' AND '", endDate, "' 
AND account_number IN (", inAccountNumber, ") 
ON DUPLICATE KEY UPDATE account_value = VALUES(account_value);");

prepare stmt from @pershing;
execute stmt;


SET @schwab := concat("INSERT INTO ", inCrmDB, ".consolidated_balances
SELECT account_number, account_value, as_of_date
FROM custodian_balances_schwab WHERE as_of_date BETWEEN '", startDate, "' AND '", endDate, "' 
AND account_number IN (", inAccountNumber, ") 
ON DUPLICATE KEY UPDATE account_value = VALUES(account_value);");
prepare stmt from @schwab;
execute stmt;



SET @td := concat("INSERT INTO ", inCrmDB, ".consolidated_balances
SELECT account_number, account_value, as_of_date
FROM custodian_balances_td WHERE as_of_date BETWEEN '", startDate, "' AND '", endDate, "' 
AND account_number IN (", inAccountNumber, ") 
ON DUPLICATE KEY UPDATE account_value = VALUES(account_value);");
prepare stmt from @td;
execute stmt;


SET @axos := concat("INSERT INTO ", inCrmDB, ".consolidated_balances
SELECT account_number, account_value, as_of_date
FROM custodian_balances_axos WHERE as_of_date BETWEEN '", startDate, "' AND '", endDate, "' 
AND account_number IN (", inAccountNumber, ") 
ON DUPLICATE KEY UPDATE account_value = VALUES(account_value);");
prepare stmt from @axos;
execute stmt;

END //

DELIMITER ;
