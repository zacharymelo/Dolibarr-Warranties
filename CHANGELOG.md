# Changelog

## [1.27.2] - 2026-04-03

### Fixed
- Fix phpcs violations — docblocks, string concats, underscore-prefixed method rename

## [1.27.1] - 2026-04-03

### Added
- Warranties tab on third party card

## [1.27.0] - 2026-04-02

### Added
- Warranty dedup by serial+shipment, auto-void on resale

## [1.26.5] - 2026-04-02

### Fixed
- Populate linked objects block and auto-link invoices on warranty creation

## [1.26.4] - 2026-04-02

### Added
- Auto-create warranties on order close/delivered

## [1.26.3] - 2026-04-02

### Fixed
- Trigger class, auto-warranty on shipment close, lang key collisions

## [1.25.1] - 2026-03-27

### Fixed
- Live query for warranty claim count
- Debug mode settings layout

## [1.25.0] - 2026-03-26

### Added
- Extrafields (complementary attributes) support for warranties and service requests

## [1.24.1] - 2026-03-26

### Fixed
- syncLinkedObjects cleans up stale unprefixed element_element rows

## [1.24.0] - 2026-03-26

### Added
- Comprehensive debug diagnostic endpoint with settings toggle

## [1.23.4] - 2026-03-26

### Fixed
- Use prefixed element type warrantysvc_svcwarranty in syncLinkedObjects

## [1.23.3] - 2026-03-26

### Fixed
- Add linkedobjectblock.tpl.php templates for SR and Warranty

## [1.23.2] - 2026-03-26

### Fixed
- Add getNomUrl() to SvcRequest and SvcWarranty

## [1.23.1] - 2026-03-26

### Fixed
- Add $module='warrantysvc' to SvcRequest and SvcWarranty

## [1.23.0] - 2026-03-26

### Added
- syncLinkedObjects() to auto-create element_element links for all SR FK fields

## [1.22.2] - 2026-03-26

### Fixed
- Wrap warranty edit form around card content so fields POST on save

## [1.22.1] - 2026-03-26

### Fixed
- Shorten top menu label to 'Warranty' to prevent layout overflow

## [1.22.0] - 2026-03-26

### Added
- Fix trigger event names, wire condition score into serial picker
- Service history on warranty card

### Fixed
- Cancel crash

## [1.21.4] - 2026-03-26

### Fixed
- Complete element_element linking across all creation flows

## [1.21.3] - 2026-03-26

### Fixed
- Cancel on warranty create form crashed — redirect to list

## [1.21.2] - 2026-03-26

### Fixed
- Warranty AJAX joins used nonexistent fk_lot column

## [1.21.1] - 2026-03-26

### Fixed
- Increment warranty claim_count when service request is created

## [1.21.0] - 2026-03-26

### Added
- Optional Customer Returns module integration for SR inbound returns

## [1.20.3] - 2026-03-24

### Fixed
- Add warrantysvc_svcrequest alias to elementproperties hook

## [1.20.2] - 2026-03-23

### Fixed
- Pass origin via hidden POST fields

## [1.20.1] - 2026-03-23

### Fixed
- Use warrantysvc_svcrequest origin format

## [1.20.0] - 2026-03-23

### Added
- Replace inline SO form with native Dolibarr SO creation via origin/trigger auto-link

## [1.19.0] - 2026-03-23

### Added
- Replace movement tracker with two-button replacement order + return reception panel

## [1.18.2] - 2026-03-23

### Fixed
- Remove non-functional SR mode radio buttons

## [1.18.1] - 2026-03-23

### Fixed
- Add missing note.php and warranty_note.php for Notes tab

## [1.18.0] - 2026-03-23

### Added
- RMA outbound/inbound workflow — replacement SO, gated shipment, return reception

## [1.17.3] - 2026-03-23

### Fixed
- Remove globalcard hook context to prevent duplicate linked items on invoice card

## [1.17.2] - 2026-03-23

### Fixed
- coverage_days not submitted when warranty type selected

## [1.17.1] - 2026-03-23

### Fixed
- Add CSRF token to delete and edit links on warranty type list

## [1.12.22] - 2026-03-22

### Fixed
- Remove invalid origin/origin_id from outbound shipment creation

## [1.12.21] - 2026-03-22

### Fixed
- Remove stale $warehouse_id param from createOutboundShipment

## [1.12.20] - 2026-03-22

### Fixed
- Clear floated half-columns before movement panel on SR card

## [1.12.19] - 2026-03-22

### Fixed
- Move dol_get_fiche_end before tabsAction on SR card view

## [1.12.18] - 2026-03-22

### Fixed
- Move fk_commande/fk_expedition hidden inputs before script block

## [1.12.17] - 2026-03-22

### Added
- Warranty and serial mutual auto-fill on SR create form

## [1.12.16] - 2026-03-22

### Fixed
- Source SR serial options from svc_warranty not expeditiondet_batch

## [1.12.15] - 2026-03-22

### Added
- Quick SR action and linked claims to warranty list

## [1.12.11] - 2026-03-22

### Added
- Highlight expired warranty rows in red in list view

## [1.12.10] - 2026-03-22

### Added
- Compute effective expiry from start_date + type duration in warranty list

## [1.12.6] - 2026-03-22

### Changed
- Remove manual warranty creation mode

## [1.12.0] - 2026-03-22

### Added
- Register svcwarranty/svcrequest in linked objects dropdown

## [1.11.0] - 2026-03-22

### Added
- PBX to SR integration, project tab, warranty SR button

## [1.10.0] - 2026-03-22

### Added
- Customer-scoped Standard warranty creation mode

## [1.9.0] - 2026-03-22

### Added
- Lock project select until customer chosen, filter by company

## [1.8.0] - 2026-03-22

### Added
- Restrict company dropdowns to customers only

## [1.7.0] - 2026-03-22

### Added
- Three-mode warranty create form — standard, override, manual

## [1.6.0] - 2026-03-22

### Added
- Restrict warranty create product select to serials-available products

## [1.5.0] - 2026-03-22

### Added
- Serial number select on manual warranty create

## [1.4.1] - 2026-03-22

### Added
- Warranty pairing and serial select on new service request

## [1.4.0] - 2026-03-22

### Added
- UI tooltips and help text across all forms

## [1.3.0] - 2026-03-22

### Added
- Create warranty from shipment + auto-warranty trigger

## [1.2.2] - 2026-03-22

### Fixed
- Layout and form errors on warranty card page

## [1.1.0] - 2026-03-22

### Added
- Initial module scaffold with UI pages, PDF module, cron, email notifications
- PBX integration and REST API
- Auto-create warranty on shipment validation
- Guided troubleshooting workflow
