CREATE TABLE llx_svc_warranty_type(
	rowid                   INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity                  INTEGER DEFAULT 1 NOT NULL,
	code                    VARCHAR(50) NOT NULL,
	label                   VARCHAR(255) NOT NULL,
	description             TEXT,
	coverage_terms          TEXT,
	exclusions              TEXT,
	default_coverage_days INTEGER DEFAULT 365,
	active                  TINYINT DEFAULT 1 NOT NULL,
	position                INTEGER DEFAULT 0,
	tms                     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key              VARCHAR(14)
) ENGINE=innodb;

INSERT IGNORE INTO llx_svc_warranty_type (entity, code, label, description, default_coverage_days, active, position) VALUES
(1, 'standard', 'Standard Warranty',  'Standard manufacturer warranty covering defects in materials and workmanship.', 365,  1, 10),
(1, 'extended', 'Extended Warranty',  'Extended coverage beyond the standard warranty period.',                        730,  1, 20),
(1, 'limited',  'Limited Warranty',   'Limited coverage specific to certain components or conditions.',                365,  1, 30),
(1, 'service',  'Service Contract',   'Full service and maintenance contract including labour and parts.',             365,  1, 40);
