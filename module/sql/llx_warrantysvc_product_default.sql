-- Copyright (C) 2026 DPG Supply
CREATE TABLE llx_warrantysvc_product_default(
	rowid          INTEGER      AUTO_INCREMENT PRIMARY KEY,
	fk_product     INTEGER      NOT NULL,
	entity         INTEGER      NOT NULL DEFAULT 1,
	warranty_type  VARCHAR(50)  NOT NULL,
	coverage_days  INTEGER      DEFAULT NULL,
	date_creation  DATETIME     NOT NULL,
	fk_user_creat  INTEGER
) ENGINE=innodb;
