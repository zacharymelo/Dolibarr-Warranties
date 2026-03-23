# WarrantySvc — Dolibarr Warranty & RMA Module

A Dolibarr custom module for warranty registration and RMA (Return Merchandise Authorization) tracking, built for small manufacturing businesses that sell serialized equipment.

---

## Overview

WarrantySvc adds a full service lifecycle to Dolibarr, covering:

- **Service Requests** — intake, triage, and resolution tracking for customer complaints and RMA cases
- **Warranties** — per-serial-number coverage records tied to customers and products, with expiry tracking
- **Warranty Types** — admin-defined templates (standard, extended, etc.) with default coverage durations
- **Guided Troubleshooting** — a structured diagnostic checklist workflow attached to service requests

The module integrates with native Dolibarr objects: Societe, Product, Expedition (Shipment), Reception, FichInter (Intervention), Commande (Order), Facture (Invoice), and Project.

---

## Requirements

- Dolibarr 17.x or later
- PHP 8.0+
- The following Dolibarr modules should be enabled for full functionality:
  - **Stocks / Warehouses** — required for warehouse-based shipment and return tracking
  - **Shipments (Expedition)** — enables outbound shipment creation from service requests
  - **Interventions (FichInter)** — enables on-site service scheduling
  - **Invoices** — enables non-return invoicing
  - **Orders** — enables origin order linkage on warranties
  - **Projects** — enables project linkage on service requests (optional)

---

## Installation

1. Download the latest release zip (e.g. `module_warrantysvc-1.15.0.zip`).
2. Extract and copy the `warrantysvc/` folder into your Dolibarr `custom/` directory:
   ```
   htdocs/custom/warrantysvc/
   ```
3. In Dolibarr, go to **Setup → Modules/Applications** and enable **Warranty & Service Management**.
4. After activation, go to **Setup → Warranty & Service → Setup** to configure warehouses, coverage defaults, and automation settings.

> **Note:** The zip must contain a `warrantysvc/` root directory. Do not rename it — Dolibarr uses the folder name as the module identifier.

---

## Features

### Service Requests

Service requests represent a customer complaint or RMA case. Each request tracks:

| Field | Description |
|---|---|
| Customer | The Dolibarr Societe linked to this case |
| Product | The product with the reported fault |
| Serial Number | The customer's defective unit serial/lot number |
| Issue Date | When the customer reported the fault |
| Reported Via | Intake channel: phone, email, on-site, or other |
| Resolution Type | Determines the workflow (see below) |
| Issue Description | Customer-reported fault detail — appears on the service authorization PDF |
| Customer Site | Physical installation location |
| Assigned To | Internal technician responsible for the case |
| Warranty Status | Auto-matched from warranty records by serial number |
| Billable | Whether to invoice the customer for this case |
| Resolution Notes | Internal notes on how the case was closed |

#### Resolution Types

The resolution type chosen at intake determines which workflow panels appear:

| Type | Description | Panels |
|---|---|---|
| Component Shipment | Ship a part to the customer — no return required | Outbound |
| Component Shipment + Return | Ship a part and request the old part back for analysis | Outbound + Return |
| Full Unit Swap — Cross Ship | Send a refurbished unit now; customer returns faulty unit after | Outbound + Return |
| Full Unit Swap — Wait for Return | Customer ships faulty unit first; replacement sent after receipt | Return + Outbound |
| On-Site Service | Schedule a technician visit; optionally creates a Dolibarr Intervention | Intervention |
| Guidance Only | Provide troubleshooting steps by phone/email — no physical action | None |
| Informational | No fault found — logged for audit purposes | None |

#### Service Request Lifecycle

```
Draft → Validated → In Progress → Awaiting Return → Resolved → Closed
                                                   ↗
                                          (swap/component types)
```

Additional transitions: **Cancel** (from any non-closed state), **Re-open** (from Closed or Cancelled).

#### Movement Tracker

For resolution types involving physical movement, a Movement Tracker panel shows:
- **Outbound** — carrier, tracking number, replacement serial, link to created Dolibarr shipment
- **Return** — expected return date, carrier, tracking, received serial, overdue indicator
- **Intervention** — link to created Dolibarr Intervention record
- Quick actions: **Ship Components**, **Ship Replacement Unit**, **Log Return Received**, **Send Reminder**, **Invoice for Non-Return**

---

### Warranties

Warranties cover a specific serial number for a specific customer and product. Each warranty tracks:

| Field | Description |
|---|---|
| Serial Number | The unit covered — must be unique per active warranty |
| Customer | The Dolibarr Societe that owns the warranty |
| Product | The product type covered |
| Warranty Type | Optional template that auto-fills coverage duration |
| Start Date | When coverage begins |
| Coverage Days | Duration of coverage in days |
| Expiry Date | Auto-computed from start date + coverage days, or set manually |
| Coverage Terms | What is covered — printed on warranty documents |
| Exclusions | What is explicitly not covered — printed on warranty documents |
| Origin Order | Optional link to the Dolibarr order this warranty came from |
| Origin Shipment | Auto-linked when created from a validated shipment |
| Note Public | Customer-visible notes — included in warranty notification emails |
| Status | Active / Expiring Soon / Expired / Voided |
| Claims | Count and total value of all service requests filed against this warranty |

#### Creating Warranties

Two methods are available:

1. **From Shipment** — Select a validated shipment; the module shows all serialized product lines that don't yet have warranty coverage. Choose a serial, a warranty type, and a start date.
2. **Manually** — Fill in all fields directly. Useful for warranties issued outside of the normal shipment flow.

Warranties can also be **auto-created on shipment validation** if the `WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT` setting is enabled.

---

### Warranty Types

Warranty Types are admin-defined templates that appear in warranty form dropdowns. Selecting a type auto-fills the coverage duration and locks the field.

Each type has:
- **Code** — short machine-readable identifier (e.g. `standard`, `extended_2yr`)
- **Label** — human-readable name shown in dropdowns
- **Description** — optional description visible to staff
- **Default Coverage Days** — auto-fills the warranty coverage field when this type is selected
- **Position** — controls display order in dropdowns

Warranty types are managed at **Warranty & Service → Warranty Types**.

---

### Guided Troubleshooting

Each service request has a **Troubleshoot** tab that presents a product-aware diagnostic checklist. Technicians can:
- Check off each diagnostic step as completed
- Record a finding or measurement per step
- Write a session summary
- Record an outcome (resolved, no fault, escalate, parts needed, on-site required)

Findings are appended to the service request's internal notes as a structured block. Multiple sessions are preserved and displayed chronologically. Selecting **No fault found** automatically updates the resolution type to *Informational*.

Checklist steps are product-aware:
- **All products**: Safety inspection, power supply verification, startup observation
- **Oxygen compressors** (product ref contains `compressor` or `oc-`): Intake filter, output pressure, flow rate, moisture trap, outlet purity, vibration/noise, heat/temperature
- **All products (wrap-up)**: Customer steps to reproduce, attempt to reproduce, firmware/software version

---

## Admin Configuration

Go to **Setup → Warranty & Service → Setup** to configure:

| Setting | Description |
|---|---|
| Replacement From Warehouse | Warehouse from which refurbished/replacement units are shipped |
| Return To Warehouse | Warehouse where returned or in-repair units are received |
| Return reminder delay | Days after the expected return date before the first reminder email is sent |
| Auto-invoice after | Days after the expected return date before auto-invoicing unreturned units |
| Default replacement strategy | How the system suggests a replacement unit: FIFO, Least Serviced, Best Condition, or Manual |
| Auto-check warranty on creation | Look up warranty coverage automatically when a service request is created |
| Auto-create warranty on shipment | Register a warranty for each serialized line when a shipment is validated |
| Default warranty coverage (days) | Coverage period used when auto-creating warranties from shipments |
| Email customer on warranty creation | Send a notification email when a new warranty is registered |
| Restrict service requests to serialized products | Only show lot/serial-tracked products in service request forms |

---

## Reference Numbering

Service request reference numbers follow the pattern `SVC-YYMM-XXXX` by default. The numbering module can be changed on the Setup page under **Reference Numbering Module**.

---

## DoliStore Publication Checklist

Before submitting to [DoliStore](https://www.dolistore.com):

- [ ] **Register a module ID** in the `100000–499999` range on the [Dolibarr module ID wiki page](https://wiki.dolibarr.org/index.php?title=List_of_modules_id) and update `$this->numero` in `core/modules/modWarrantySvc.class.php`
  - Current value `510000` is valid for private use only (`>500000` range)
  - DoliStore requires a registered ID in `100000–499999`
- [x] Zip named `module_warrantysvc-VERSION.zip` (DoliStore file validator requirement)
- [x] `docs/LICENSE` present (GPL v3)
- [x] `langs/en_US/warrantysvc.lang` present and complete
- [x] `main.inc.php` loaded via multi-depth fallback (htdocs/custom + htdocs compatibility)
- [ ] Test "Deploy an external module" via Dolibarr **Home → Setup → Modules** before submitting
- [ ] English product description ready for DoliStore listing
- [ ] Support contact email ready (required for paid modules)

---

## Changelog

### v1.16.0
- Standards compliance audit and fixes (Dolibarr coding standards + DoliStore qualification)
  - `svcrequest.class.php`: added entity filter to fetch-by-ID (was missing, fetch-by-ref was correct)
  - `card.php`: added entity filter to PBX call display-mode query (edit-mode query was already correct)
  - `svcservicelog.class.php`: migrated `CONDITION_*` constants from strings to integers (`SMALLINT`); updated SQL write points accordingly
  - `sql/llx_svc_service_log.sql`: changed `condition_status` from `VARCHAR(50)` to `SMALLINT DEFAULT 0`; added `import_key VARCHAR(14)`
  - `sql/llx_svc_service_log_upgrade.sql`: added migration for existing deployments
  - `docs/LICENSE`: added GPL v3 license file (required for DoliStore)
  - Build: zip naming changed to `module_warrantysvc-VERSION.zip` (required by DoliStore file validator)

### v1.15.0
- Cross-module linked objects integration: Warranty and Service Request records now appear in the native "Related objects" block on Order, Shipment, Invoice, Intervention, and Project cards, with the native "Link to..." hyperlink for manual linking
- Added linked objects block to the Service Request card (`card.php`)
- Fixed `SvcRequest::$element` type mismatch (`warrantysvc` → `svcrequest`) so links stored in `llx_element_element` resolve correctly; `warrantysvc` retained as backward-compat alias

### v1.3.x
- Auto-create warranty from validated shipment (trigger-based)
- Warranty card now shows claim history with value totals
- Movement tracker — overdue indicator on return tracking
- PDF: service authorization slip with component lines and tracking info

### v1.2.x
- Fixed layout errors on warranty card page
- Renamed `coverage_months` → `coverage_days` in database schema
- Migration script included in `sql/llx_svc_warranty_upgrade.sql`

### v1.1.x
- Guided troubleshooting workflow (Troubleshoot tab)
- Oxygen compressor-specific diagnostic steps

### v1.0.0
- Initial release: Service Requests, Warranties, Warranty Types, Movement Tracker

---

## Development

Local development uses Docker Compose:

```sh
docker compose up
```

To build an installable zip:

```sh
# The archive root must be warrantysvc/ — not module/
# The module_ prefix is required by DoliStore's file validator
zip -r module_warrantysvc-x.y.z.zip warrantysvc/
```

See `project_build.md` in the memory store for full build instructions.
