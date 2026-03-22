-- Copyright (C) 2026 DPG Supply
ALTER TABLE llx_svc_request_line ADD INDEX idx_svc_request_line_fk_rma (fk_svc_request);
ALTER TABLE llx_svc_request_line ADD INDEX idx_svc_request_line_fk_product (fk_product);
