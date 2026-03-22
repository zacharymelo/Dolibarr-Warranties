-- Copyright (C) 2026 DPG Supply
ALTER TABLE llx_svc_warranty ADD UNIQUE INDEX uk_svc_warranty_serial (serial_number, entity);
ALTER TABLE llx_svc_warranty ADD INDEX idx_svc_warranty_fk_soc (fk_soc);
ALTER TABLE llx_svc_warranty ADD INDEX idx_svc_warranty_fk_product (fk_product);
ALTER TABLE llx_svc_warranty ADD INDEX idx_svc_warranty_status (status);
