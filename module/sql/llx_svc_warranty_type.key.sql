ALTER TABLE llx_svc_warranty_type ADD UNIQUE INDEX uk_svc_warranty_type_code (code, entity);
ALTER TABLE llx_svc_warranty_type ADD INDEX idx_svc_warranty_type_entity (entity);
