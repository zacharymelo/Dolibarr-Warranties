-- Copyright (C) 2026 DPG Supply
CREATE TABLE llx_svc_warranty_extrafields(
	rowid      INTEGER AUTO_INCREMENT PRIMARY KEY,
	tms        TIMESTAMP,
	fk_object  INTEGER NOT NULL,
	import_key VARCHAR(14)
) ENGINE=innodb;
