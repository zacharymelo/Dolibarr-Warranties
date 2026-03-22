-- Copyright (C) 2026 DPG Supply
ALTER TABLE llx_svc_service_log ADD INDEX idx_svc_service_log_serial (serial_number);
ALTER TABLE llx_svc_service_log ADD INDEX idx_svc_service_log_product (fk_product);
