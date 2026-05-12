-- v1.32.0: add security seal number field to service requests
ALTER TABLE llx_svc_request ADD COLUMN seal_number VARCHAR(128) AFTER serial_out;

-- v1.32.2: migrate stale 'expedition' element type to 'shipping' in element_element.
-- Old trigger code called add_object_linked('expedition',...) but Expedition::$element = 'shipping',
-- so those rows were invisible to Dolibarr's Related Objects renderer. Fix in-place.
UPDATE llx_element_element
SET sourcetype = 'shipping'
WHERE sourcetype = 'expedition'
  AND targettype IN ('warrantysvc_svcwarranty', 'svcwarranty');

UPDATE llx_element_element
SET targettype = 'shipping'
WHERE targettype = 'expedition'
  AND sourcetype IN ('warrantysvc_svcwarranty', 'svcwarranty');
