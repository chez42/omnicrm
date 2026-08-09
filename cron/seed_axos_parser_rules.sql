-- Seed File Parsing Rules for Axos
INSERT INTO custodian_omniscient.file_parsing_rules (skeleton_table, copy_to_table, on_update_fields) VALUES
('axos_positions_skeleton', 'custodian_positions_axos', 'market_value=VALUES(market_value), units=VALUES(units), book_value=VALUES(book_value), rep_id=VALUES(rep_id), description=VALUES(description), contract_no=VALUES(contract_no), price_date=VALUES(price_date), composite_model_id=VALUES(composite_model_id), filename=VALUES(filename), insert_date=VALUES(insert_date), date=VALUES(date)'),
('axos_transactions_skeleton', 'custodian_transactions_axos', 'units=VALUES(units), journal_date=VALUES(journal_date), trade_date=VALUES(trade_date), activity_code=VALUES(activity_code), amount=VALUES(amount), book_value=VALUES(book_value), unit_cost=VALUES(unit_cost), fee_assess=VALUES(fee_assess), offset_journal_id=VALUES(offset_journal_id), trade_fee=VALUES(trade_fee), fed_withholding=VALUES(fed_withholding), security_fees=VALUES(security_fees), rep_id=VALUES(rep_id), model_from=VALUES(model_from), dist_id=VALUES(dist_id), composite_model_id=VALUES(composite_model_id), filename=VALUES(filename), insert_date=VALUES(insert_date)'),
('axos_securities_skeleton', 'custodian_securities_axos', 'cusip=VALUES(cusip), description=VALUES(description), description2=VALUES(description2), asset_type=VALUES(asset_type), asset_class=VALUES(asset_class), frequency=VALUES(frequency), payment_day=VALUES(payment_day), issue_date=VALUES(issue_date), maturity_date=VALUES(maturity_date), annual_rate=VALUES(annual_rate), filename=VALUES(filename), insert_date=VALUES(insert_date)'),
('axos_prices_skeleton', 'custodian_prices_axos', 'cusip=VALUES(cusip), price=VALUES(price), filename=VALUES(filename), insert_date=VALUES(insert_date)'),
('axos_reg_skeleton', 'custodian_portfolios_axos', 'name1=VALUES(name1), name2=VALUES(name2), address1=VALUES(address1), address2=VALUES(address2), city=VALUES(city), state=VALUES(state), zip=VALUES(zip), account_type=VALUES(account_type), tax_id=VALUES(tax_id), rep_id=VALUES(rep_id), alpha_sort=VALUES(alpha_sort), alternate_id=VALUES(alternate_id), filename=VALUES(filename), insert_date=VALUES(insert_date)')
ON DUPLICATE KEY UPDATE copy_to_table=VALUES(copy_to_table), on_update_fields=VALUES(on_update_fields);

-- Seed File Parsing Params for Axos (Binding filename parameter to steps 0 and 1)
INSERT INTO custodian_omniscient.file_parsing_params (skeleton_table, field_name, step) VALUES
('axos_positions_skeleton', 'filename', 0),
('axos_positions_skeleton', 'filename', 1),
('axos_transactions_skeleton', 'filename', 0),
('axos_transactions_skeleton', 'filename', 1),
('axos_securities_skeleton', 'filename', 0),
('axos_securities_skeleton', 'filename', 1),
('axos_prices_skeleton', 'filename', 0),
('axos_prices_skeleton', 'filename', 1),
('axos_reg_skeleton', 'filename', 0),
('axos_reg_skeleton', 'filename', 1)
ON DUPLICATE KEY UPDATE field_name=VALUES(field_name);
