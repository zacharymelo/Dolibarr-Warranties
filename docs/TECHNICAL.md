# WarrantySvc -- Technical Reference

Module ID: **510000** | Family: `crm` | Version: **1.27.2**
Requires: Dolibarr >= 16.0, PHP >= 7.0
Dependencies: `modSociete`, `modProduct`, `modStock`

---

## Pages & URL Parameters

### Service Request Pages

| File | Purpose | Key GET/POST Parameters |
|------|---------|------------------------|
| `card.php` | Service Request create / view / edit card | `id` (int), `ref` (alpha), `action` (aZ09: create, edit, update, confirm_validate, confirm_delete, confirm_close, confirm_cancel, confirm_reopen, addline, updateline, deleteline, createshipment, validateshipment, createreception, validatereception, createintervention, sendreminder, invoicenonreturn, builddoc, setresolved, setinprogress, setawaitreturn, createorder, createcustomerreturn, voidwarranty), `cancel`, `backtopage`, `lineid`, `rma_sr_id` |
| `list.php` | Service Request list with filters and pagination | `action`, `massaction`, `contextpage`, `optioncss`, `projectid` (int -- pre-filter by project), `search_ref`, `search_company`, `search_serial`, `search_resolution`, `search_status` (intcomma), `search_assigned` (int, -1=unassigned), `search_warranty`, `search_date_startday/month/year`, `search_date_endday/month/year`, `preset` (alpha: myopen, awaitreturn, unassigned), `sortfield`, `sortorder`, `page` |
| `note.php` | Notes tab for Service Request | `id` (int), `action` |
| `troubleshoot.php` | Guided diagnostic checklist workflow | `id` (int), `action` (aZ09: save) |

### Warranty Pages

| File | Purpose | Key GET/POST Parameters |
|------|---------|------------------------|
| `warranty_card.php` | Warranty create / view / edit card | `id` (int), `ref` (alpha), `action` (aZ09: create, edit, update, confirm_delete, voidwarranty) |
| `warranty_list.php` | Warranty list with filters | `action`, `optioncss`, `socid` (int -- pre-filter by third party), `search_*` filters, `sortfield`, `sortorder`, `page` |
| `warranty_note.php` | Notes tab for Warranty | `id` (int), `action` |
| `warranty_type_list.php` | CRUD management for warranty types | `action` (aZ09: add, edit, update, confirm_delete), `id` (int) |

### Admin Pages

| File | Purpose | Key GET/POST Parameters |
|------|---------|------------------------|
| `admin/setup.php` | Module configuration (warehouses, strategies, toggles) | `action` (aZ09: update), all `WARRANTYSVC_*` constants as POST fields |
| `admin/svcrequest_extrafields.php` | Manage extrafields for `svc_request` table | Standard Dolibarr extrafields params (`action`, `attrname`) |
| `admin/svcwarranty_extrafields.php` | Manage extrafields for `svc_warranty` table | Standard Dolibarr extrafields params (`action`, `attrname`) |

### AJAX Endpoints

| File | Purpose | GET Parameters |
|------|---------|---------------|
| `ajax/serials.php` | Shipped serials for a product without warranty | `fk_product` (int), `fk_soc` (int), `token` |
| `ajax/warranty_serials.php` | Unassigned serials shipped to a customer for a product | `socid` (int), `fk_product` (int) |
| `ajax/sr_products.php` | Products shipped to a customer (for SR form) | `socid` (int) |
| `ajax/warranty_products.php` | Products with unassigned serials for a customer (for warranty form) | `socid` (int) |
| `ajax/stock_serials.php` | Serials in stock sorted by replacement strategy | `fk_product` (int), `fk_warehouse` (int, optional), `token` |
| `ajax/projects.php` | Open projects for a third party | `socid` (int) |
| `ajax/debug.php` | Diagnostic endpoint (admin + debug mode only) | `mode` (overview, object, links, settings, classes, sql, triggers, hooks, all), `type`, `id`, `q` |
| `ajax/debug_links.php` | Show element_element rows for a specific SR | `id` (int) |

### Templates

| File | Purpose |
|------|---------|
| `svcrequest/tpl/linkedobjectblock.tpl.php` | Linked object display block for Service Requests |
| `svcwarranty/tpl/linkedobjectblock.tpl.php` | Linked object display block for Warranties |

---

## Classes & Methods

### SvcRequest (`class/svcrequest.class.php`)

Extends `CommonObject`. Manages RMA/service request cases.

**Constants:**

| Constant | Value | Description |
|----------|-------|-------------|
| `STATUS_DRAFT` | 0 | Initial state |
| `STATUS_VALIDATED` | 1 | Approved, ready to work |
| `STATUS_IN_PROGRESS` | 2 | Being worked on |
| `STATUS_AWAIT_RETURN` | 3 | Waiting for customer to return unit |
| `STATUS_RESOLVED` | 4 | Issue fixed |
| `STATUS_CLOSED` | 5 | Finalized |
| `STATUS_CANCELLED` | 9 | Cancelled |
| `RESOLUTION_COMPONENT` | 'component' | Ship part, no return |
| `RESOLUTION_COMPONENT_RETURN` | 'component_return' | Ship part + require return |
| `RESOLUTION_SWAP_CROSS` | 'swap_cross' | Cross-ship replacement |
| `RESOLUTION_SWAP_WAIT` | 'swap_wait' | Customer returns first |
| `RESOLUTION_INTERVENTION` | 'intervention' | On-site service |
| `RESOLUTION_GUIDANCE` | 'guidance' | Troubleshooting only |

**Key properties:** `$module = 'warrantysvc'`, `$element = 'svcrequest'`, `$table_element = 'svc_request'`, `$TRIGGER_PREFIX = 'WARRANTYSVC'`

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `create($user, $notrigger=0)` | int (id or <0) | Create RMA record in draft status. Auto-generates ref, inserts extrafields, fires `WARRANTYSVC_CREATE`. |
| `fetch($id, $ref='')` | int | Load by ID or ref. Also calls `fetchLines()` and `fetch_optionals()`. |
| `fetchLines()` | int | Load all `SvcRequestLine` children. |
| `update($user, $notrigger=0)` | int | Update all fields. Inserts extrafields. Fires `WARRANTYSVC_MODIFY`. |
| `delete($user, $notrigger=0)` | int | Delete record, lines, extrafields. Fires `WARRANTYSVC_DELETE`. |
| `validate($user, $notrigger=0)` | int | Draft -> Validated. Sets `date_validation`, auto-checks warranty. Fires `WARRANTYSVC_VALIDATE`. |
| `setInProgress($user)` | int | Validated -> In Progress. Fires `WARRANTYSVC_SETINPROGRESS` (via update trigger). |
| `setAwaitingReturn($user)` | int | Any -> Awaiting Return. |
| `resolve($user)` | int | Any -> Resolved. |
| `close($user, $notrigger=0)` | int | Resolved/InProgress/AwaitReturn -> Closed. Sets `date_closed`. Fires `WARRANTYSVC_CLOSE`. |
| `cancel($user)` | int | Any (except closed/cancelled) -> Cancelled. |
| `reopen($user)` | int | Closed/Resolved -> In Progress. Clears `date_closed`. |
| `checkWarrantyStatus()` | string | Looks up warranty by `fk_warranty` or `serial_number`. Sets `warranty_status` and `billable`. Returns 'active', 'expired', or 'none'. |
| `syncLinkedObjects()` | void | Ensures `element_element` rows exist for all FK relationships (warranty, order, shipment, invoice, reception, intervention). Cleans stale unprefixed type names. |
| `isWarrantyCovered()` | bool | Returns true if `warranty_status == 'active'`. |
| `getNomUrl($withpicto, $option, $notooltip)` | string | HTML link to card.php. |
| `createReturnReception($user, $fk_warehouse)` | int | Creates a Reception object for customer return. Inserts `receptiondet_batch` lines from the outbound shipment. |
| `validateReception($user)` | int | Validates the linked reception. Sets `date_return_received`. |
| `linkShipment($shipment_id, $tracking, $carrier, $user)` | int | Associates an Expedition and sets tracking/carrier/date_shipped. |
| `linkReception($reception_id, $condition, $user)` | int | Associates a Reception and sets `date_return_received`. |
| `createLinkedIntervention($user, $type='repair')` | int | Creates a Fichinter linked to this SR. Sets extrafields `rma_ref` and `rma_serial_in`. |
| `linkIntervention($fichinter_id, $user)` | int | Links an existing intervention. |
| `sendReturnReminder($user)` | int | Sends reminder email to customer. Increments `return_reminder_count`. |
| `invoiceForNonReturn($user, $validate=false)` | int | Creates a Facture for an unreturned unit. Sets `fk_facture` and `billable=1`. |
| `suggestReplacementSerial($product_id, $strategy)` | string/null | (Stub) Suggests serial from refurbished stock. |
| `checkOverdueReturns()` | int | Cron method. Sends reminders and auto-invoices for overdue returns. |
| `getNextNumRef($objsoc)` | string | Delegates to `mod_warrantysvc_standard`. Format: `SRQ-YYYYMMDD-NNNN`. |
| `getLibStatut($mode)` / `LibStatut($status, $mode)` | string | Returns status badge HTML. |
| `getResolutionLabel($type)` | string | Translated resolution type label. |
| `getCustomerSerials($fk_soc, $fk_product)` | array | Serials from shipment history for a customer/product. |
| `createFromCall($actioncomm_id, $user)` | int | Factory method: creates SR pre-filled from an `actioncomm` phone call record. Links via `fk_pbxcall` and `add_object_linked`. |
| `countForProject($projectid)` | int | Count SRs for a project (used by tab badge). |

### SvcRequestLine (`class/svcrequestline.class.php`)

Extends `CommonObjectLine`. Component/part lines on an RMA case.

**Constants:**

| Constant | Value | Description |
|----------|-------|-------------|
| `TYPE_COMPONENT_OUT` | 'component_out' | Part shipped to customer |
| `TYPE_COMPONENT_IN` | 'component_in' | Part returned from customer |
| `TYPE_CONSUMED_SITE` | 'consumed_onsite' | Part used during on-site visit |

**Methods:** `fetch($rowid)`, `insert($user, $notrigger)`, `update($user, $notrigger)`, `delete($user, $notrigger)`

### SvcWarranty (`class/svcwarranty.class.php`)

Extends `CommonObject`. Manages warranty records per serialized product.

**Constants:**

| Constant | Value |
|----------|-------|
| `STATUS_ACTIVE` | 'active' |
| `STATUS_EXPIRED` | 'expired' |
| `STATUS_VOIDED` | 'voided' |

**Key properties:** `$module = 'warrantysvc'`, `$element = 'svcwarranty'`, `$table_element = 'svc_warranty'`, `$TRIGGER_PREFIX = 'SVCWARRANTY'`

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `create($user, $notrigger=0)` | int (id) | Creates warranty. Auto-generates ref (`WTY-YYYYMM-NNNN`). Auto-computes `expiry_date` from `start_date + coverage_days`. Fires `SVCWARRANTY_CREATE`. |
| `fetch($id, $ref='')` | int | Load by ID or ref. Calls `syncStatus()` and `fetch_optionals()`. |
| `fetchBySerial($serial_number)` | int | Loads the most recent warranty for a serial. |
| `update($user, $notrigger=0)` | int | Update all fields + extrafields. Fires `SVCWARRANTY_MODIFY`. |
| `delete($user)` | int | Deletes record, linked objects, extrafields. |
| `getNomUrl($withpicto, $option, $notooltip)` | string | HTML link to warranty_card.php. |
| `syncStatus()` | void | Auto-sets status to 'expired' if `expiry_date < now` (unless voided). |
| `getNextNumRef()` | string | Format: `WTY-YYYYMM-NNNN`. |
| `LibStatut($status, $mode)` | string | Status badge HTML. |
| `countForThirdparty($socid)` | int | Static. Count warranties for a third party (tab badge). |

### SvcWarrantyType (`class/svcwarrantytype.class.php`)

Extends `CommonObject`. User-defined warranty type templates.

**Properties:** `$element = 'svcwarrantytype'`, `$table_element = 'svc_warranty_type'`

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `create($user, $notrigger=0)` | int (id) | Insert new type. |
| `fetch($id, $code='')` | int | Load by ID or code. |
| `update($user, $notrigger=0)` | int | Update type. |
| `delete($user, $notrigger=0)` | int | Delete type. |
| `fetchAll()` | array | All types (active + inactive) for management page. |
| `fetchAllActive($db)` | array | Static. Active types as `code => label` for dropdowns. |
| `fetchAllForForm($db)` | array | Static. Active type objects with `code`, `label`, `coverage_terms`, `exclusions`, `default_coverage_days`. |
| `getLabelByCode($db, $code)` | string | Static. Returns label for a type code. |

### SvcServiceLog (`class/svcservicelog.class.php`)

Standalone class (no CommonObject parent). Tracks per-serial service history for replacement selection scoring.

**Condition constants:** `CONDITION_GOOD` (0), `CONDITION_FAIR` (1), `CONDITION_POOR` (2), `CONDITION_SCRAP` (3)

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `fetchBySerial($serial_number, $entity=1)` | int | Load most recent log for a serial. |
| `save($user)` | int | Upsert: inserts or updates based on serial+entity. |
| `computeConditionScore()` | int | Formula: `(service_count * 10) + (service_hours / 100)`. Lower = better. |
| `getAvailableSerials($db, $product_id, $warehouse_id, $strategy)` | array | Static. Queries `product_lot` + `product_stock` joined with service log. Strategies: 'fifo', 'least_serviced', 'best_condition'. Returns array of `[serial, lot_id, condition_score, service_count, service_hours, qty_available]`. |

### ActionsWarrantySvc (`class/actions_warrantysvc.class.php`)

Hook actions class. Registered on contexts: `elementproperties`, `productcard`, `commonobject`, `ordercard`.

**Methods:**

| Method | Hook Context | Description |
|--------|-------------|-------------|
| `getElementProperties(...)` | elementproperties | Resolves `svcwarranty` and `svcrequest` element types (plus aliases `warrantysvc`, `warrantysvc_svcrequest`, `warrantysvc_svcwarranty`) so Dolibarr can find classes for linked-object lookups. |
| `formObjectOptions(...)` | productcard, ordercard | On **product card** (view/edit): injects "Warranty Default Type" and "Default Coverage Days" fields. Supports variant inheritance from parent product. On **order creation**: injects hidden `origin`/`originid` fields when `rma_sr_id` is set in GET. |
| `showLinkToObjectBlock(...)` | commonobject | Injects `svcwarranty` and `svcrequest` into the "Link to..." dropdown on any Dolibarr object card. |
| `doActions(...)` | productcard | On product update: saves/deletes `warrantysvc_product_default` rows for per-product warranty type/days configuration. |

### Library Functions (`lib/warrantysvc.lib.php`)

| Function | Description |
|----------|-------------|
| `warrantysvc_prepare_head($object)` | Returns tab array for SR card (Details, Notes, Troubleshoot, Documents). |
| `svcwarranty_prepare_head($object)` | Returns tab array for Warranty card (Details, Notes). |
| `svcrequest_status_badge($status, $mode)` | HTML badge for SR status. |
| `svcwarranty_status_badge($status, $mode)` | HTML badge for warranty status (active/expired/voided/none). |
| `svcrequest_resolution_label($type)` | Translated label for a resolution type constant. |
| `svcrequest_resolution_types()` | Key-value array of all resolution types for select boxes. |
| `warrantysvc_admin_prepare_head()` | Returns tab array for admin pages (Settings, SR Extra Fields, Warranty Extra Fields). |

### Library Functions (`lib/troubleshoot.lib.php`)

| Function | Description |
|----------|-------------|
| `warrantysvc_checklist_steps($fk_product)` | Returns product-specific or generic diagnostic checklist steps. Steps include categories: safety, power, mechanical, consumable, software, general. |

---

## REST API Endpoints

Base path: `/api/index.php/warrantysvc`

### Service Requests

| Method | Endpoint | Function | Parameters | Permission |
|--------|----------|----------|-----------|------------|
| GET | `/requests` | `indexRequests()` | `sortfield` (default: t.rowid), `sortorder` (default: ASC), `limit` (default: 100), `page` (default: 0), `sqlfilters` | svcrequest:read |
| GET | `/requests/{id}` | `getRequest($id)` | `id` (int) | svcrequest:read |
| POST | `/requests` | `postRequest($request)` | JSON body with SvcRequest fields | svcrequest:write |
| PUT | `/requests/{id}` | `putRequest($id, $request)` | `id` (int), JSON body with fields to update (rowid, entity, ref, date_creation are immutable) | svcrequest:write |
| POST | `/requests/createfromcall/{id}` | `postRequestFromCall($id, $request)` | `id` (int -- actioncomm ID), optional JSON body with override fields | svcrequest:write |

### Warranties

| Method | Endpoint | Function | Parameters | Permission |
|--------|----------|----------|-----------|------------|
| GET | `/warranties` | `indexWarranties()` | `sortfield`, `sortorder`, `limit`, `page`, `sqlfilters` | svcwarranty:read |
| GET | `/warranties/{id}` | `getWarranty($id)` | `id` (int) | svcwarranty:read |
| GET | `/warranties/byserial/{serial}` | `getWarrantyBySerial($serial)` | `serial` (string) | svcwarranty:read |

---

## Hooks

Registered in module descriptor `module_parts['hooks']`:

```php
'hooks' => array(
    'data' => array('elementproperties', 'productcard', 'commonobject', 'ordercard'),
    'entity' => '0'
)
```

| Hook Context | Hook Method | What It Does |
|-------------|-------------|-------------|
| `elementproperties` | `getElementProperties()` | Resolves custom element types (`svcwarranty`, `svcrequest`, and prefixed aliases) so Dolibarr can load the correct class for linked-object operations. |
| `productcard` | `formObjectOptions()` | Injects "Warranty Default Type" dropdown and "Default Coverage Days" input on the product card (edit and view modes). Supports variant/parent inheritance. |
| `productcard` | `doActions()` | On product update action: persists the per-product warranty type/days to `llx_warrantysvc_product_default`. |
| `commonobject` | `showLinkToObjectBlock()` | Adds "Link to warranty" and "Link to service request" entries in the "Link to..." dropdown on all Dolibarr object cards. |
| `ordercard` | `formObjectOptions()` | On order creation with `rma_sr_id` in GET: injects hidden `origin` + `originid` fields to auto-link the new SO to the originating SR. |

---

## Triggers

File: `core/triggers/interface_99_modWarrantySvc_WarrantySvcTrigger.class.php`
Class: `InterfaceWarrantySvcTrigger` (extends `DolibarrTriggers`)

| Trigger Code | Source | Action |
|-------------|--------|--------|
| `WARRANTYSVC_CREATE` | SvcRequest created | Syncs `claim_count` on linked warranty. |
| `WARRANTYSVC_VALIDATE` | SvcRequest validated | Syncs `claim_count`. Emails assigned technician. |
| `WARRANTYSVC_SETINPROGRESS` | SvcRequest set in progress | Emails assigned technician. |
| `WARRANTYSVC_AWAITRETURN` | SvcRequest awaiting return | Emails customer with tracking info. |
| `WARRANTYSVC_RESOLVE` | SvcRequest resolved | Emails customer with resolution notes. |
| `WARRANTYSVC_CLOSE` | SvcRequest closed | Logs to syslog. |
| `SVCWARRANTY_CREATE` | Warranty created | Emails customer (if `WARRANTYSVC_NOTIFY_WARRANTY_CREATED` enabled). |
| `FICHINTER_CLOSE` | Intervention closed | Finds linked SR by `fk_intervention`, upserts `SvcServiceLog` with accumulated hours and incremented service count. |
| `SHIPPING_VALIDATE` | Shipment validated | Auto-creates warranties for serialized lines (if configured via `WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT` + `WARRANTYSVC_WARRANTY_TRIGGER_EVENT`). |
| `SHIPPING_CLOSED` | Shipment closed | Same as above, gated by trigger event setting. |
| `ORDER_CLOSE` | Order delivered | Auto-creates warranties by iterating linked shipments (if `WARRANTYSVC_AUTO_WARRANTY_ON_ORDER_CLOSE` enabled). |
| `ORDER_CREATE` | Order created | If origin is `warrantysvc_svcrequest`, auto-links SO to SR via `element_element` and stores `fk_commande` on the SR. |
| `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE` | Customer Return validated | If linked to an SR, sets `date_return_received` and advances SR status from Await Return to In Progress (if `WARRANTYSVC_USE_CUSTOMERRETURN` enabled). |

**Auto-warranty logic:** When a shipment is closed/validated, for each `expeditiondet_batch` line with a serial, the trigger: (1) skips if warranty already exists for that serial+expedition, (2) voids active warranties for the same serial held by a different customer (resale scenario), (3) resolves warranty type via product default -> parent product default -> first active type, (4) creates the warranty record with coverage terms/exclusions from the type, (5) links to expedition, order, and invoices via `element_element`.

---

## Database Schema

### llx_svc_request

Main service request / RMA case table.

| Column | Type | Notes |
|--------|------|-------|
| rowid | INTEGER AUTO_INCREMENT | PK |
| ref | VARCHAR(30) NOT NULL | Format: SRQ-YYYYMMDD-NNNN |
| entity | INTEGER NOT NULL DEFAULT 1 | Multi-entity |
| fk_soc | INTEGER NOT NULL | FK to llx_societe |
| fk_product | INTEGER NOT NULL | FK to llx_product |
| serial_number | VARCHAR(128) | Customer's defective unit serial |
| fk_contact | INTEGER | FK to llx_socpeople |
| customer_site | VARCHAR(255) | Physical location |
| fk_project | INTEGER | FK to llx_projet |
| fk_commande | INTEGER | FK to llx_commande (replacement order) |
| fk_expedition_origin | INTEGER | FK to original outbound shipment |
| fk_lot | INTEGER | FK to llx_product_lot |
| issue_description | TEXT | Customer complaint |
| issue_date | DATETIME | When reported |
| reported_via | VARCHAR(50) | phone, email, onsite, other |
| fk_pbxcall | INTEGER | FK to llx_actioncomm (CRM call) |
| resolution_type | VARCHAR(50) | component, component_return, swap_cross, swap_wait, intervention, guidance, informational |
| resolution_notes | TEXT | How issue was resolved |
| fk_warranty | INTEGER | FK to llx_svc_warranty |
| warranty_status | VARCHAR(20) DEFAULT 'none' | active, expired, none |
| billable | TINYINT DEFAULT 0 | 1 = invoice customer |
| fk_facture | INTEGER | FK to llx_facture |
| serial_in | VARCHAR(128) | Returning serial |
| serial_out | VARCHAR(128) | Replacement serial |
| fk_warehouse_source | INTEGER | Replacement from warehouse |
| fk_warehouse_return | INTEGER | Return to warehouse |
| outbound_carrier | VARCHAR(100) | |
| outbound_tracking | VARCHAR(100) | |
| date_shipped | DATETIME | |
| fk_shipment | INTEGER | FK to llx_expedition |
| return_carrier | VARCHAR(100) | |
| return_tracking | VARCHAR(100) | |
| date_return_expected | DATE | |
| date_return_received | DATETIME | |
| return_reminder_count | INTEGER DEFAULT 0 | |
| fk_reception | INTEGER | FK to llx_reception |
| fk_intervention | INTEGER | FK to llx_fichinter |
| fk_user_assigned | INTEGER | FK to llx_user |
| status | INTEGER NOT NULL DEFAULT 0 | 0=draft, 1=validated, 2=in_progress, 3=await_return, 4=resolved, 5=closed, 9=cancelled |
| date_creation | DATETIME NOT NULL | |
| date_validation | DATETIME | |
| date_closed | DATETIME | |
| tms | TIMESTAMP | Auto-update |
| fk_user_creat | INTEGER | |
| fk_user_valid | INTEGER | |
| fk_user_close | INTEGER | |
| import_key | VARCHAR(14) | |
| model_pdf | VARCHAR(255) | |
| last_main_doc | VARCHAR(255) | |
| note_private | TEXT | |
| note_public | TEXT | |

**Indexes:** `uk_svc_request_ref` (ref, entity) UNIQUE, `idx_svc_request_fk_soc`, `idx_svc_request_fk_product`, `idx_svc_request_status`, `idx_svc_request_serial`

### llx_svc_request_line

Component/part lines on an RMA case.

| Column | Type | Notes |
|--------|------|-------|
| rowid | INTEGER AUTO_INCREMENT | PK |
| fk_svc_request | INTEGER NOT NULL | FK to parent |
| fk_product | INTEGER NOT NULL | FK to llx_product |
| product_type | INTEGER DEFAULT 0 | 0=product, 1=service |
| description | TEXT | |
| qty | DECIMAL(24,8) DEFAULT 1 | |
| line_type | VARCHAR(50) | component_out, component_in, consumed_onsite |
| fk_expedition_line | INTEGER | FK to llx_expeditiondet |
| fk_reception_line | INTEGER | FK to llx_receptiondet_batch |
| subprice | DECIMAL(24,8) | Unit price |
| total_ht | DECIMAL(24,8) | Line total |
| tva_tx | DECIMAL(6,3) DEFAULT 0 | VAT rate |
| shipped | TINYINT DEFAULT 0 | |
| received | TINYINT DEFAULT 0 | |
| rang | INTEGER DEFAULT 0 | Display order |
| note | TEXT | |
| tms | TIMESTAMP | |

**Indexes:** `idx_svc_request_line_fk_rma` (fk_svc_request), `idx_svc_request_line_fk_product`

### llx_svc_warranty

Warranty records per serialized product.

| Column | Type | Notes |
|--------|------|-------|
| rowid | INTEGER AUTO_INCREMENT | PK |
| ref | VARCHAR(30) | Format: WTY-YYYYMM-NNNN |
| entity | INTEGER NOT NULL DEFAULT 1 | |
| fk_product | INTEGER NOT NULL | |
| serial_number | VARCHAR(128) NOT NULL | |
| fk_soc | INTEGER NOT NULL | |
| warranty_type | VARCHAR(50) | FK code to llx_svc_warranty_type.code |
| start_date | DATE NOT NULL | |
| expiry_date | DATE | Auto-computed if blank |
| coverage_days | INTEGER | |
| coverage_terms | TEXT | What is covered |
| exclusions | TEXT | What is excluded |
| status | VARCHAR(20) NOT NULL DEFAULT 'active' | active, expired, voided |
| fk_contract | INTEGER | |
| fk_commande | INTEGER | Origin order |
| fk_expedition | INTEGER | Origin shipment |
| claim_count | INTEGER DEFAULT 0 | Auto-synced by trigger |
| total_claimed_value | DECIMAL(24,8) DEFAULT 0 | |
| date_creation | DATETIME NOT NULL | |
| tms | TIMESTAMP | |
| fk_user_creat | INTEGER | |
| import_key | VARCHAR(14) | |
| note_private | TEXT | |
| note_public | TEXT | |

**Indexes:** `uk_svc_warranty_serial` (serial_number, entity) UNIQUE, `idx_svc_warranty_fk_soc`, `idx_svc_warranty_fk_product`, `idx_svc_warranty_status`

### llx_svc_warranty_type

Dictionary of warranty type templates.

| Column | Type | Notes |
|--------|------|-------|
| rowid | INTEGER AUTO_INCREMENT | PK |
| entity | INTEGER NOT NULL DEFAULT 1 | |
| code | VARCHAR(50) NOT NULL | Machine-readable key |
| label | VARCHAR(255) NOT NULL | Human-readable name |
| description | TEXT | |
| coverage_terms | TEXT | Default coverage terms template |
| exclusions | TEXT | Default exclusions template |
| default_coverage_days | INTEGER DEFAULT 365 | |
| active | TINYINT NOT NULL DEFAULT 1 | |
| position | INTEGER DEFAULT 0 | Sort order |
| tms | TIMESTAMP | |
| import_key | VARCHAR(14) | |

**Indexes:** `uk_svc_warranty_type_code` (code, entity) UNIQUE, `idx_svc_warranty_type_entity`

**Seed data:** standard (365d), extended (730d), limited (365d), service (365d)

### llx_svc_service_log

Per-serial service/condition history for replacement selection scoring.

| Column | Type | Notes |
|--------|------|-------|
| rowid | INTEGER AUTO_INCREMENT | PK |
| entity | INTEGER NOT NULL DEFAULT 1 | |
| fk_product | INTEGER NOT NULL | |
| serial_number | VARCHAR(128) NOT NULL | |
| service_hours | DECIMAL(10,2) DEFAULT 0 | Accumulated hours |
| service_count | INTEGER DEFAULT 0 | Times serviced |
| last_service_date | DATE | |
| condition_score | INTEGER | Computed: (count*10)+(hours/100) |
| condition_notes | TEXT | |
| condition_status | SMALLINT DEFAULT 0 | 0=good, 1=fair, 2=poor, 3=scrap |
| date_last_updated | DATETIME | |
| tms | TIMESTAMP | |
| import_key | VARCHAR(14) | |

**Indexes:** `idx_svc_service_log_serial`, `idx_svc_service_log_product`

### llx_warrantysvc_product_default

Per-product warranty type defaults (used on product card and auto-warranty creation).

| Column | Type | Notes |
|--------|------|-------|
| rowid | INTEGER AUTO_INCREMENT | PK |
| fk_product | INTEGER NOT NULL | |
| entity | INTEGER NOT NULL DEFAULT 1 | |
| warranty_type | VARCHAR(50) NOT NULL | Code from llx_svc_warranty_type |
| coverage_days | INTEGER DEFAULT NULL | NULL = use type default |
| date_creation | DATETIME NOT NULL | |
| fk_user_creat | INTEGER | |

**Indexes:** `uk_warrantysvc_proddefault` (fk_product, entity) UNIQUE

### llx_svc_request_extrafields

Standard Dolibarr extrafields table for service requests.

| Column | Type |
|--------|------|
| rowid | INTEGER AUTO_INCREMENT PK |
| tms | TIMESTAMP |
| fk_object | INTEGER NOT NULL |
| import_key | VARCHAR(14) |

**Indexes:** `idx_svc_request_extra_fk` (fk_object)

### llx_svc_warranty_extrafields

Standard Dolibarr extrafields table for warranties.

| Column | Type |
|--------|------|
| rowid | INTEGER AUTO_INCREMENT PK |
| tms | TIMESTAMP |
| fk_object | INTEGER NOT NULL |
| import_key | VARCHAR(14) |

**Indexes:** `idx_svc_warranty_extra_fk` (fk_object)

### Upgrade Scripts

| File | Description |
|------|-------------|
| `llx_svc_warranty_upgrade.sql` | Renames `coverage_months` to `coverage_days`. Adds `coverage_terms` and `exclusions` columns to `llx_svc_warranty_type`. |
| `llx_svc_service_log_upgrade.sql` | Migrates `condition_status` from VARCHAR to SMALLINT. Adds `import_key`. |

---

## Permissions

Module: `warrantysvc` | Rights class: `warrantysvc`

### Service Request Permissions

| ID | Object | Action | Description |
|----|--------|--------|-------------|
| 510001 | svcrequest | read | Read service requests |
| 510002 | svcrequest | write | Create and edit service requests |
| 510003 | svcrequest | delete | Delete service requests |
| 510004 | svcrequest | validate | Validate service requests |
| 510005 | svcrequest | close | Close service requests |

### Warranty Permissions

| ID | Object | Action | Description |
|----|--------|--------|-------------|
| 510011 | svcwarranty | read | Read warranties |
| 510012 | svcwarranty | write | Create and edit warranties |
| 510013 | svcwarranty | delete | Delete warranties |

**Usage in code:** `$user->hasRight('warrantysvc', 'svcrequest', 'read')`, `$user->hasRight('warrantysvc', 'svcwarranty', 'write')`, etc.

---

## Cron Jobs

| Index | Label | Class/Method | Frequency | Default Status | Description |
|-------|-------|-------------|-----------|----------------|-------------|
| 0 | CheckOverdueRMAReturns | `SvcRequest::checkOverdueReturns()` | Daily (86400s) | Disabled (0) | Scans `status=3` (awaiting return) records where `date_return_expected` is past `WARRANTYSVC_RETURN_GRACE_DAYS`. Sends reminder emails to customers. Auto-creates invoices for records past `WARRANTYSVC_RETURN_INVOICE_DAYS`. |

---

## Language Keys

File: `langs/en_US/warrantysvc.lang`

| Category | Example Keys |
|----------|-------------|
| Module identity | `WarrantySvc`, `WarrantySvcDescription` |
| Menu labels | `SvcRequests`, `NewSvcRequest`, `Warranties`, `NewWarranty`, `WarrantyTypes` |
| Object labels | `SvcRequest`, `SvcWarranty`, `ServiceLog`, `LinkToWarranty`, `LinkToServiceRequest` |
| Status labels | `SvcDraft`, `SvcValidated`, `SvcInProgress`, `AwaitingReturn`, `SvcResolved`, `SvcClosed`, `SvcCancelled`, `SvcActive`, `SvcExpired`, `SvcVoided`, `NoCoverage` |
| Resolution types | `ResolutionComponent`, `ResolutionComponentReturn`, `ResolutionSwapCross`, `ResolutionSwapWait`, `ResolutionIntervention`, `ResolutionGuidance`, `ResolutionInformational` + `*Desc` variants |
| Field labels | `SvcSerialNumber`, `SerialIn`, `SerialOut`, `IssueDescription`, `IssueDate`, `ReportedVia`, `CustomerSite`, `ResolutionNotes`, `WarrantyStatus`, `Billable`, `OutboundCarrier`, `OutboundTracking`, `ReturnCarrier`, `ReturnTracking`, `CoverageDays`, `CoverageTerms`, `Exclusions`, `ClaimCount` |
| Reported via options | `ReportedViaPhone`, `ReportedViaEmail`, `ReportedViaOnSite`, `ReportedViaOther` |
| Warranty type management | `WarrantyType`, `AddWarrantyType`, `WarrantyTypeAdded`, `SelectWarrantyType`, `ErrorWarrantyTypeCodeLabelRequired` |
| Condition labels | `ConditionGood`, `ConditionFair`, `ConditionPoor`, `ConditionScrap` |
| Replacement strategies | `StrategyFIFO`, `StrategyLeastServiced`, `StrategyBestCondition`, `StrategyManual` |
| Action buttons | `ValidateSvcRequest`, `SetInProgress`, `SendReplacement`, `ReceiveReturn`, `CreateIntervention`, `MarkResolved`, `CloseSvcRequest`, `ReopenSvcRequest`, `CancelSvcRequest`, `DeleteSvcRequest`, `SendReminder`, `InvoiceNonReturn`, `VoidWarranty` |
| Section headings | `SectionResolution`, `SectionComponents`, `SectionWarranty`, `SectionShipping`, `SectionReturn`, `SectionLinkedObjects` |
| Info banners | `WarrantyCoveredBanner`, `WarrantyExpiredBanner`, `WarrantyNoneBanner` |
| PDF labels | `SvcRequestSlip`, `ReturnInstructions`, `ReturnShipTo`, `ComponentLines`, `TechnicianSignature`, `CustomerSignature` |
| Tab labels | `SvcDetails`, `SvcNotes`, `SvcDocuments`, `Troubleshoot` |
| Troubleshoot workflow | `TroubleshootSaved`, `TroubleshootSummary`, `TroubleshootOutcome`, `DiagnosticStep`, `Finding`, `SaveFindings`, `ChecklistSafety*`, `ChecklistPower*`, `ChecklistComp*`, `ChecklistCustomerSteps`, `ChecklistReproduce`, `ChecklistFirmware` |
| Email notifications | `NotifTechValidateSubject`, `NotifTechValidateBody`, `NotifTechInProgressSubject`, `NotifCustAwaitReturnSubject`, `NotifCustResolvedSubject`, `NotifWarrantyCreatedSubject`, `ReminderReturnSubject`, `ReminderReturnBody` |
| Error messages | `ErrorSvcRequestNotInDraftStatus`, `ErrorSvcRequestNotInValidatedStatus`, `ErrorNoSerialForSwap`, `ErrorNoWarehouseSelected`, `ErrorWarrantySerialRequired` |
| Admin/setup | `SvcSetup`, `SetupSaved`, `SvcWarehouseRefurbDesc`, `SvcWarehouseReturnDesc`, `ReturnGraceDays`, `ReturnInvoiceDays`, `ReplacementStrategy`, `AutoWarrantyCheck`, `AutoWarrantyOnShipment`, `WarrantyTriggerEvent`, `DefaultCoverageDays`, `NotifyWarrantyCreated`, `WarrantyRequiresLots`, `UseCustomerReturns`, `NumberingModule` |
| Product card warranty defaults | `WarrantyDefaultType`, `WarrantyDefaultDays`, `WarrantyNoDefault`, `WarrantyInheritParent`, `WarrantyBlankInherits`, `WarrantyInheritedFromParent`, `WarrantyVariantOverride` |
| Tooltips | `Tooltip*` (30+ tooltip keys for form field help icons) |
| Movement tracker | `MovementTracker`, `OutboundToCustomer`, `ReturnFromCustomer`, `ShipComponents`, `ShipReplacementUnit`, `CreateReplacementOrder`, `CreateShipment`, `ValidateShipment`, `CreateReturnReception` |

---

## Configuration Constants

Saved in `llx_const` via `admin/setup.php`:

| Constant | Type | Default | Description |
|----------|------|---------|-------------|
| `WARRANTYSVC_WAREHOUSE_REFURB` | int | -- | Warehouse ID for refurbished replacement stock |
| `WARRANTYSVC_WAREHOUSE_RETURN` | int | -- | Warehouse ID for returned/in-repair units |
| `WARRANTYSVC_RETURN_GRACE_DAYS` | int | 7 | Days after expected return before first reminder |
| `WARRANTYSVC_RETURN_INVOICE_DAYS` | int | 30 | Days after expected return before auto-invoice |
| `WARRANTYSVC_REPLACEMENT_STRATEGY` | string | fifo | Default serial selection: fifo, least_serviced, best_condition, manual |
| `WARRANTYSVC_AUTO_WARRANTY_CHECK` | bool | 0 | Auto-lookup warranty on SR creation |
| `WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT` | bool | 0 | Auto-create warranty when shipment is validated/closed |
| `WARRANTYSVC_WARRANTY_TRIGGER_EVENT` | string | close | Which shipment event triggers warranty creation: validate, close, both |
| `WARRANTYSVC_AUTO_WARRANTY_ON_ORDER_CLOSE` | bool | 0 | Auto-create warranty when order is classified as delivered |
| `WARRANTYSVC_DEFAULT_COVERAGE_DAYS` | int | 365 | Fallback coverage when no product/type default |
| `WARRANTYSVC_NOTIFY_WARRANTY_CREATED` | bool | 0 | Email customer on warranty creation |
| `WARRANTYSVC_WARRANTY_REQUIRES_LOTS` | bool | 0 | Restrict SR product selector to lot-tracked products |
| `WARRANTYSVC_USE_CUSTOMERRETURN` | bool | 0 | Use Customer Returns module for inbound returns |
| `WARRANTYSVC_DEBUG_MODE` | bool | 0 | Enable admin-only debug endpoints |

---

## PDF Models

### Abstract Parent: `ModelePDFWarrantySvc`

File: `core/modules/warrantysvc/modules_warrantysvc.php`
Extends `CommonDocGenerator`.

- Static method `liste_modeles($db)` returns available PDF templates.
- Abstract method `write_file($object, $outputlangs, ...)` must be implemented by concrete models.

### Standard Model: `pdf_svcrequest_standard`

File: `core/modules/warrantysvc/pdf_svcrequest_standard.php`
Extends `ModelePDFWarrantySvc`.

Generates a "Service Request Authorization" slip PDF containing:
- Company header and SR reference
- Customer info, serial number, issue description
- Resolution type and notes
- Component lines table (product, qty, line type, shipped/received status)
- Outbound/return tracking information
- Signature blocks (technician + customer)

Uses standard Dolibarr PDF margins from `MAIN_PDF_MARGIN_*` globals.

### Numbering Models

#### Abstract Parent: `ModeleNumRefWarrantySvc`

File: `core/modules/warrantysvc/modules_warrantysvc.php`
Extends `CommonNumRefGenerator`.

Abstract methods: `getNextValue($objsoc, $object)`, `getExample()`.

#### Standard Numbering: `mod_warrantysvc_standard`

File: `core/modules/warrantysvc/mod_warrantysvc_standard.php`

- **SR format:** `SRQ-YYYYMMDD-NNNN` (daily counter, zero-padded to 4 digits)
- **Warranty format:** `WTY-YYYYMM-NNNN` (monthly counter, implemented directly in `SvcWarranty::getNextNumRef()`)

---

## Extrafields

Both `SvcRequest` and `SvcWarranty` support Dolibarr's standard extrafields mechanism:

- **SR extrafields table:** `llx_svc_request_extrafields` (managed via `admin/svcrequest_extrafields.php`, element type `svc_request`)
- **Warranty extrafields table:** `llx_svc_warranty_extrafields` (managed via `admin/svcwarranty_extrafields.php`, element type `svc_warranty`)

Both classes call `insertExtraFields()` during create/update and `fetch_optionals()` during fetch. Delete operations call `deleteExtraFields()`.

The admin setup page exposes three tabs: Settings, SR Extra Fields, and Warranty Extra Fields.

---

## Module Descriptor Summary

File: `core/modules/modWarrantySvc.class.php`

| Setting | Value |
|---------|-------|
| Module ID | 510000 |
| Family | crm |
| Position | 50 |
| Version | 1.27.2 |
| Picto | technic |
| Config page | setup.php@warrantysvc |
| Dependencies | modSociete, modProduct, modStock |
| Min PHP | 7.0 |
| Min Dolibarr | 16.0 |
| Data dir | /warrantysvc/temp |
| APIs enabled | Yes (Luracast) |
| Triggers enabled | Yes |

### Tabs on Existing Objects

| Target Object | Tab Key | Tab Label | URL |
|--------------|---------|-----------|-----|
| Project | warrantysvc_svcrequest | ServiceRequests | /warrantysvc/list.php?projectid=__ID__ |
| Third Party | warrantysvc_warranties | Warranties | /warrantysvc/warranty_list.php?socid=__ID__ |

### Menu Structure

| Level | Menu Key | Label | URL |
|-------|---------|-------|-----|
| Top | warrantysvc | WarrantySvc | /warrantysvc/list.php |
| Left | warrantysvc_list | SvcRequests | /warrantysvc/list.php |
| Left (child) | warrantysvc_new | NewSvcRequest | /warrantysvc/card.php?action=create |
| Left | warrantysvc_warranty_list | Warranties | /warrantysvc/warranty_list.php |
| Left (child) | warrantysvc_warranty_new | NewWarranty | /warrantysvc/warranty_card.php?action=create |
| Left (child) | warrantysvc_warranty_types | WarrantyTypes | /warrantysvc/warranty_type_list.php |
