-- Copyright (C) 2026 DPG Supply
ALTER TABLE llx_svc_request ADD UNIQUE INDEX uk_svc_request_ref (ref, entity);
ALTER TABLE llx_svc_request ADD INDEX idx_svc_request_fk_soc (fk_soc);
ALTER TABLE llx_svc_request ADD INDEX idx_svc_request_fk_product (fk_product);
ALTER TABLE llx_svc_request ADD INDEX idx_svc_request_status (status);
ALTER TABLE llx_svc_request ADD INDEX idx_svc_request_serial (serial_number);
