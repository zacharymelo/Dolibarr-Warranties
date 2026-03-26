-- Copyright (C) 2026 DPG Supply
ALTER TABLE llx_warrantysvc_product_default
	ADD UNIQUE INDEX uk_warrantysvc_proddefault (fk_product, entity);
