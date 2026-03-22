-- Migration: rename coverage_months -> coverage_days
-- Safe to re-run: will error silently if coverage_months no longer exists
ALTER TABLE llx_svc_warranty CHANGE coverage_months coverage_days INTEGER DEFAULT NULL;
