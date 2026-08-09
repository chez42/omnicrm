USE custodian_omniscient;

-- 1. Cost Basis Unrealized
CREATE TABLE IF NOT EXISTS axos_cbu_skeleton (
    account_number varchar(20),
    ticker varchar(20),
    cusip varchar(20),
    option_symbol varchar(30),
    underlying_ticker varchar(20),
    underlying_cusip varchar(20),
    security_type varchar(10),
    file_date date,
    open_date date,
    units decimal(15,4),
    cost_basis decimal(15,2),
    unit_cost decimal(15,4),
    amortization_adjustment decimal(15,2),
    long_tax_lot varchar(5),
    opening_activity_type varchar(20),
    premium_for_option decimal(15,2),
    covered varchar(5),
    wash_sale varchar(5),
    gift_type varchar(20),
    gift_date date,
    date_of_death date,
    average_cost_lot varchar(5),
    filename varchar(100),
    insert_date datetime
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custodian_cbu_axos (
    account_number varchar(20),
    ticker varchar(20),
    cusip varchar(20),
    option_symbol varchar(30),
    underlying_ticker varchar(20),
    underlying_cusip varchar(20),
    security_type varchar(10),
    file_date date,
    open_date date,
    units decimal(15,4),
    cost_basis decimal(15,2),
    unit_cost decimal(15,4),
    amortization_adjustment decimal(15,2),
    long_tax_lot varchar(5),
    opening_activity_type varchar(20),
    premium_for_option decimal(15,2),
    covered varchar(5),
    wash_sale varchar(5),
    gift_type varchar(20),
    gift_date date,
    date_of_death date,
    average_cost_lot varchar(5),
    filename varchar(100),
    insert_date datetime,
    UNIQUE KEY uniq_cbu (account_number, ticker, open_date, file_date),
    KEY idx_acct (account_number),
    KEY idx_file_date (file_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Cost Basis Realized
CREATE TABLE IF NOT EXISTS axos_cbl_skeleton (
    account_number varchar(20),
    ticker varchar(20),
    cusip varchar(20),
    option_symbol varchar(30),
    underlying_ticker varchar(20),
    underlying_cusip varchar(20),
    security_type varchar(10),
    file_date date,
    open_date date,
    closed_date date,
    units decimal(15,4),
    cost_basis decimal(15,2),
    unit_cost decimal(15,4),
    amortization decimal(15,2),
    long_tax_lot varchar(5),
    closing_activity_type varchar(20),
    proceeds decimal(15,2),
    premium_for_options decimal(15,2),
    short_term_realized_gain_loss decimal(15,2),
    long_term_realized_gain_loss decimal(15,2),
    covered varchar(5),
    wash_sale varchar(5),
    disallowed_loss decimal(15,2),
    gift_type varchar(20),
    gift_date date,
    date_of_death date,
    average_cost_lot varchar(5),
    tax_lot_disposition_methodology varchar(30),
    filename varchar(100),
    insert_date datetime
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custodian_cbl_axos (
    account_number varchar(20),
    ticker varchar(20),
    cusip varchar(20),
    option_symbol varchar(30),
    underlying_ticker varchar(20),
    underlying_cusip varchar(20),
    security_type varchar(10),
    file_date date,
    open_date date,
    closed_date date,
    units decimal(15,4),
    cost_basis decimal(15,2),
    unit_cost decimal(15,4),
    amortization decimal(15,2),
    long_tax_lot varchar(5),
    closing_activity_type varchar(20),
    proceeds decimal(15,2),
    premium_for_options decimal(15,2),
    short_term_realized_gain_loss decimal(15,2),
    long_term_realized_gain_loss decimal(15,2),
    covered varchar(5),
    wash_sale varchar(5),
    disallowed_loss decimal(15,2),
    gift_type varchar(20),
    gift_date date,
    date_of_death date,
    average_cost_lot varchar(5),
    tax_lot_disposition_methodology varchar(30),
    filename varchar(100),
    insert_date datetime,
    UNIQUE KEY uniq_cbl (account_number, ticker, open_date, closed_date, file_date),
    KEY idx_acct (account_number),
    KEY idx_closed_date (closed_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Representative Details
CREATE TABLE IF NOT EXISTS axos_rep_skeleton (
    rep_id varchar(20),
    name1 varchar(100),
    name2 varchar(100),
    address1 varchar(100),
    address2 varchar(100),
    address3 varchar(100),
    city varchar(50),
    state varchar(5),
    zip varchar(20),
    crd_number varchar(20),
    firm_id varchar(20),
    alternate_rep_id varchar(20),
    phone varchar(20),
    filename varchar(100),
    insert_date datetime
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custodian_rep_axos (
    rep_id varchar(20),
    name1 varchar(100),
    name2 varchar(100),
    address1 varchar(100),
    address2 varchar(100),
    address3 varchar(100),
    city varchar(50),
    state varchar(5),
    zip varchar(20),
    crd_number varchar(20),
    firm_id varchar(20),
    alternate_rep_id varchar(20),
    phone varchar(20),
    filename varchar(100),
    insert_date datetime,
    UNIQUE KEY uniq_rep (rep_id),
    KEY idx_rep (rep_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Brokerage Details
CREATE TABLE IF NOT EXISTS axos_bkr_skeleton (
    broker_id varchar(20),
    name1 varchar(100),
    name2 varchar(100),
    address1 varchar(100),
    address2 varchar(100),
    address3 varchar(100),
    city varchar(50),
    state varchar(5),
    zip varchar(20),
    filename varchar(100),
    insert_date datetime
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custodian_bkr_axos (
    broker_id varchar(20),
    name1 varchar(100),
    name2 varchar(100),
    address1 varchar(100),
    address2 varchar(100),
    address3 varchar(100),
    city varchar(50),
    state varchar(5),
    zip varchar(20),
    filename varchar(100),
    insert_date datetime,
    UNIQUE KEY uniq_bkr (broker_id),
    KEY idx_bkr (broker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Derived Balances
CREATE TABLE IF NOT EXISTS custodian_balances_axos (
    account_number varchar(20),
    account_value decimal(20,4),
    net_cash decimal(20,4),
    securities_value decimal(20,4),
    as_of_date date,
    filename varchar(100),
    insert_date datetime,
    UNIQUE KEY uniq_bal (account_number, as_of_date),
    KEY idx_acct (account_number),
    KEY idx_date (as_of_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Reconciliation Audit Results
CREATE TABLE IF NOT EXISTS axos_reconciliation_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number varchar(20),
    symbol varchar(30),
    as_of_date date,
    prior_date date,
    prior_units decimal(15,4),
    trn_units decimal(15,4),
    expected_units decimal(15,4),
    actual_units decimal(15,4),
    discrepancy decimal(15,4),
    status varchar(30),
    insert_date datetime,
    UNIQUE KEY uniq_recon (account_number, symbol, as_of_date),
    KEY idx_acct (account_number),
    KEY idx_date (as_of_date),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

