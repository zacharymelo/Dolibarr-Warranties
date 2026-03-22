-- Copyright (C) 2026 DPG Supply
CREATE TABLE llx_svc_service_log(
	rowid                  INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity                 INTEGER       NOT NULL DEFAULT 1,
	fk_product             INTEGER       NOT NULL,
	serial_number          VARCHAR(128)  NOT NULL,
	service_hours          DECIMAL(10,2) DEFAULT 0,
	service_count          INTEGER       DEFAULT 0,
	last_service_date      DATE,
	condition_score        INTEGER,
	condition_notes        TEXT,
	condition_status       VARCHAR(50)   DEFAULT 'good',
	date_last_updated      DATETIME,
	tms                    TIMESTAMP
) ENGINE=innodb;
