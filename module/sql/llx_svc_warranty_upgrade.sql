-- Migration: rename coverage_months -> coverage_days
-- Safe to re-run: will error silently if coverage_months no longer exists
ALTER TABLE llx_svc_warranty CHANGE coverage_months coverage_days INTEGER DEFAULT NULL;

-- v1.9.0: add coverage_terms and exclusions template fields to warranty type dictionary
ALTER TABLE llx_svc_warranty_type ADD COLUMN coverage_terms TEXT AFTER description;
ALTER TABLE llx_svc_warranty_type ADD COLUMN exclusions TEXT AFTER coverage_terms;
