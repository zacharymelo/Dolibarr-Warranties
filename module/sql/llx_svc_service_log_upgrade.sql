-- Copyright (C) 2026 DPG Supply
-- Upgrade: migrate condition_status from VARCHAR to SMALLINT and add import_key

-- Step 1: add new integer column
ALTER TABLE llx_svc_service_log ADD COLUMN condition_status_int SMALLINT DEFAULT 0;

-- Step 2: map existing string values to integers
UPDATE llx_svc_service_log SET condition_status_int = 0 WHERE condition_status = 'good';
UPDATE llx_svc_service_log SET condition_status_int = 1 WHERE condition_status = 'fair';
UPDATE llx_svc_service_log SET condition_status_int = 2 WHERE condition_status = 'poor';
UPDATE llx_svc_service_log SET condition_status_int = 3 WHERE condition_status = 'scrap';

-- Step 3: drop the old column
ALTER TABLE llx_svc_service_log DROP COLUMN condition_status;

-- Step 4: rename new column to condition_status
ALTER TABLE llx_svc_service_log CHANGE condition_status_int condition_status SMALLINT DEFAULT 0;

-- Step 5: add import_key
ALTER TABLE llx_svc_service_log ADD COLUMN import_key VARCHAR(14);
