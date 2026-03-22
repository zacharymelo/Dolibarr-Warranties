-- Copyright (C) 2026 DPG Supply
CREATE TABLE llx_svc_request_line(
	rowid                  INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_svc_request            INTEGER       NOT NULL,
	fk_product             INTEGER       NOT NULL,
	product_type           INTEGER       DEFAULT 0,
	description            TEXT,
	qty                    DECIMAL(24,8) DEFAULT 1,
	line_type              VARCHAR(50),
	fk_expedition_line     INTEGER,
	fk_reception_line      INTEGER,
	subprice               DECIMAL(24,8),
	total_ht               DECIMAL(24,8),
	tva_tx                 DECIMAL(6,3)  DEFAULT 0,
	shipped                TINYINT       DEFAULT 0,
	received               TINYINT       DEFAULT 0,
	rang                   INTEGER       DEFAULT 0,
	note                   TEXT,
	tms                    TIMESTAMP
) ENGINE=innodb;
