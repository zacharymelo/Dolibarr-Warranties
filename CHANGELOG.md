# Changelog

## [1.37.1] - 2026-11-22

### Fixed
- Service requests no longer save a product of -1. Core's product dropdown posts -1 for "nothing selected", and both create and edit stored it as-is. In production this left 6 of 13 requests with no product, and the -1 spread to the service log and to svcrecord sessions. On save, a missing product is now filled in from the serial's lot when exactly one product owns that serial; otherwise it is stored as 0. The existing -1 requests self-heal the next time they are saved.

## [1.37.0] - 2026-11-11

### Added
- Cross-ship and component-with-return cases now move to Await Return when the replacement shipment linked to them (directly, or through its sales order) is validated. The expected-return date defaults to today + `WARRANTYSVC_RETURN_GRACE_DAYS` when empty. This unblocks the return reminders, the Await Return list preset and the Customer Return validate trigger, none of which could fire before because nothing set the status.
- "Mark Awaiting Return" button on In Progress cases whose resolution expects a return (covers customer-returns-first). `setAwaitingReturn()` now refuses from any status other than In Progress.
- Documents tab (`document.php`, core upload/list template). Files are stored in `<dir_output>/svcrequest/<ref>/`, the layout core's download check grants through `svcrequest->read`.

### Fixed
- Saving the service request edit form no longer blanks the 14 fields it doesn't render (order link, serial in/out, warehouses, carriers and tracking, expected-return date, notes); only posted fields are written.
- Warranty list: the count query was built by a regex that stopped at the claim-count sub-select, so paging never rendered and records past the first page were unreachable. Sorting by Claims raised an SQL error. Expiry sorted on the stored date instead of the displayed effective expiry. The last page also no longer shows a "next" arrow.
- Saving a warranty that has a type no longer wipes its coverage days (the input was `disabled`, so it was never posted).
- "Link to..." add/remove now works on the service request and warranty cards (`actions_dellink.inc.php` was never included).
- `ajax/debug.php` no longer runs free-form SQL (the keyword blocklist let `INTO OUTFILE`, `LOAD_FILE()` and cross-entity reads through). It is replaced by fixed, entity-scoped `?mode=sql&preset=` queries. `ajax/debug_links.php` now also requires `WARRANTYSVC_DEBUG_MODE`.

### Notes
- Static-checked with doli-check: the new `document.php` is fully clean, and the edited files add no new phpcs or phan findings. Not yet exercised on a running Dolibarr; see the staging checklist in `TODO.md`.
- PDFs from `pdf_svcrequest_standard` still target `<dir_output>/<ref>/` and will not show in the new Documents tab (tracked in `TODO.md`).

## [1.36.1] - 2026-11-11

### Fixed
- "Create warranty from shipment" was refused by CSRF protection level 3: the link and the shipment selector GET form now carry a token.

## [1.36.0] - 2026-09-01

### Added
- Reopening a linked Customer Return now updates the service request — the inverse of the validate handler, closing the asymmetry documented in `doli-returns/docs/ARCHITECTURE.md`. The reversal voids the recorded receipt, so the trigger clears `date_return_received` and moves an In Progress case back to Await Return. A case already Resolved or Closed is never yanked backward automatically: its receipt date is cleared and a warning is logged for manual review. Gated by `WARRANTYSVC_USE_CUSTOMERRETURN`, like the validate handler.

### Notes
- Verified on Dolibarr 22.0.4 against customerreturn 2.4.0: 15/15 assertions, including the full validate -> reopen -> re-validate round-trip (permitted by the returns module's zero-balance invariant) and toggle-off inertness.

## [1.35.0] - 2026-08-31

### Changed
- Reconciles the field-deployed 1.34.0 line (diagnosis-first lifecycle, structured troubleshoot sessions, multi-order claims) with the fixes that landed on main after it branched: 1.32.3 (prefixed element-type keys in `showLinkToObjectBlock`), 1.32.4 ("Link to Call" on warranty and service-request cards), and 1.32.5 (trigger no longer swallows a failed status advance). Production had been running the 1.34.0 build, which lacked those three; main had been missing everything from 1.33.0 up.
- `setInProgress()` follows the field-tested diagnosis-first model: allowed from **Diagnosing** (forward path) or **Await Return** (return-received auto-advance), and requires a chosen resolution type. 1.32.5's interim widening (accepting Validated) is superseded — the forward path goes through `setDiagnosing()`.

### Notes
- Behavior consequence of the resolution-type gate: validating a Customer Return against a service request in Await Return with **no resolution type chosen** no longer advances the case — the trigger logs a warning (1.32.5) and the case stays in Await Return with `date_return_received` set. Choose a resolution type, then advance manually.

## [1.34.0] - 2026-05-21

### Added
- Multiple replacement orders per warranty claim. When more parts are needed to finish fixing the same machine, click **Add Replacement Order** on the service request card to create another sales order — independent of the first, but hierarchically tied to the claim through Dolibarr's native Related Objects (`element_element`). The RMA Action panel now lists every linked order on its own row with a native status badge (`Commande::getNomUrl(1)` + `getLibStatut(5)`); the **Add Replacement Order** button remains available while the claim is active.
- New `SvcRequest::getLinkedCommandeIds()` / `getLinkedCommandes()` resolve linked orders robustly across both source-type spellings the module has produced over time (`'svcrequest'` and `'warrantysvc_svcrequest'`), in both directions of `element_element`, with the primary `fk_commande` retained as a fallback.

## [1.33.2] - 2026-05-12

### Changed
- Troubleshoot sessions are now a first-class structured record instead of free text appended to `resolution_notes`. New `llx_svc_troubleshoot` table and `SvcTroubleshoot` class store each session (checklist state as JSON, summary, outcome, author, date) linked to its service request. The Troubleshoot tab "Previous Sessions" panel renders them with native Dolibarr table markup (`load_fiche_titre`, `liste_titre`/`oddeven` rows, `img_picto('','tick')`, `dolGetStatus` outcome badge, `dol_print_date`) — no hand-rolled HTML/CSS or text parsing. The tab shows a session-count badge. `resolution_notes` returns to being a plain human-written field; the `svcservicelog` unit-history mirror is unchanged.

## [1.33.1] - 2026-05-12

### Fixed
- Notification emails rendered raw `%1$s` / `%2$s` placeholders instead of substituted values. Dolibarr's `Translate::trans()` does not support positional placeholders — all five notification bodies (tech validate/in-progress, customer await-return, warranty-created, return reminder) now use sequential `%s` with correctly ordered arguments.
- The assigned-technician email no longer prints an empty "Resolution Type:" line; that field is now populated only after diagnosis, so the summary omits it until a type is chosen.

## [1.33.0] - 2026-05-12

### Changed
- Service request lifecycle is now diagnosis-first. Resolution Type is no longer chosen at intake; it is selected after diagnosis. A new **Diagnosing** stage sits between Validated and In Progress: Validate → Begin Diagnosis → (troubleshoot) → Set In Progress. A request cannot advance to In Progress until a resolution type is chosen.
- Troubleshoot is now a first-class step of the Diagnosing stage (see 1.33.2 for the structured session record). The unit service-history mirror (`svcservicelog`) is unchanged.
- Troubleshoot outcomes now only *suggest* a resolution type when none is set; a user-chosen type is never overwritten.

### Fixed
- Troubleshoot `no_fault` outcome referenced an undefined `SvcRequest::RESOLUTION_INFORMATIONAL` constant (fatal on save); now uses the valid `informational` resolution type.

## [1.32.5] - 2026-08-31

### Fixed
- Service request now actually advances Await Return -> In Progress when its linked Customer Return is validated. `setInProgress()` guarded on `STATUS_VALIDATED` only, so the call the CustomerReturn trigger makes from `STATUS_AWAIT_RETURN` always returned -1. The trigger discarded that return value, so the case silently stayed in Await Return while `date_return_received` was set — visible in production on SRQ-20260327-0001. Await Return is now an accepted entry point, and the trigger logs a warning instead of swallowing a failure.

### Notes
- Verified end-to-end on Dolibarr 22.0.4 against the returns module (customerreturn 2.4.0), mirroring production config: 17/17 assertions covering the link, the trigger, the lot-named stock movement and the reversal.
- `createReturnReception()` / `validateReception()` (the non-CustomerReturn inbound path) are **non-functional on Dolibarr 22** and left unchanged. The INSERT names `fk_commandefourndet`, renamed to `fk_elementdet` in v22; and every stock path in core `Reception` inner-joins `commande_fournisseurdet`, so the deliberately PO-less lines this method builds are skipped by all of them. Production has never used it (`fk_reception` is NULL on every service request) and `WARRANTYSVC_USE_CUSTOMERRETURN` is enabled. Keep it enabled. See `doli-returns/docs/ARCHITECTURE.md`.
- Changelog gap: versions 1.32.3 and 1.32.4 shipped without entries here. Their commits are `3c357aa` (prefixed element-type keys in `showLinkToObjectBlock`) and `2844a02` ("Link to Call" on warranty and service-request cards).

## [1.32.2] - 2026-05-12

### Fixed
- Data migration in upgrade SQL: corrects stale `expedition` element-type rows in `llx_element_element` to `shipping`. Rows written by old trigger code were invisible to Dolibarr's Related Objects renderer because `Expedition::$element = 'shipping'`; existing warranty→shipment links now surface correctly on shipment cards.

## [1.32.1] - 2026-05-12

### Fixed
- `getElementProperties` hook now returns `custom/warrantysvc/class` (with `custom/` prefix) so Dolibarr can resolve module class files when rendering Related Objects on external cards.
- Warranty auto-create trigger: changed `add_object_linked('expedition', ...)` to `add_object_linked('shipping', ...)` — `Expedition::$element` is `'shipping'`, not `'expedition'`.
- `syncLinkedObjects()` in SvcRequest: removed a DELETE-all statement that ran inside the per-link foreach loop, wiping all previously added links on every iteration. Fixed the existence-check to compare against `$this->element` (`'svcrequest'`) rather than `getElementType()` (`'warrantysvc_svcrequest'`) to match what `add_object_linked` actually stores.

## [1.32.0] - 2026-05-11

### Added
- Security Seal # field on service requests — records the unique number from the physical security sticker shipped with repaired parts. Visible and editable once a ticket is In Progress or beyond; displays read-only with a lock icon on closed records. An intact seal is evidence that authorized warranty service was performed; a missing or broken seal voids coverage.

## [1.31.3] - 2026-04-21

### Fixed
- Audit of remaining lang key collisions with Dolibarr core:
  - Removed duplicate definitions for `AssignedTo`, `Billable`, `ExpiryDate`, `ValidateReception` (Dolibarr core values are functionally equivalent — callers now resolve via core)
  - Renamed `SetupSaved` → `SvcSetupSaved` to preserve our "Settings saved." message without overriding core's "Setup saved"

## [1.31.2] - 2026-04-21

### Fixed
- Rename `ReceptionValidated` lang key to `SvcReceptionValidated` — the generic key was overriding Dolibarr core's own `ReceptionValidated` (`receptions.lang`), causing every standard reception's auto-agenda entry to be mislabeled as "Return reception validated."
- Also namespaces the unused `CreateShipment` key to `SvcCreateShipment` to prevent future collisions

## [1.28.15] - 2026-04-05

### Added
- Ship Replacement Unit button on service request card — creates a $0 warranty replacement order, validates it, and creates a Draft shipment linked to the SR
- Redirects to Dolibarr's native Shipment Distribution page for serial/lot selection
- Supports both batch-tracked and non-batch products
- Orphan detection for deleted shipments and orders on SR card with "Remove Link" button

### Fixed
- Use `isModEnabled('shipping')` for Dolibarr 22 compatibility (not `expedition`)
- Handle batch-tracked products via direct expeditiondet insert with fk_elementdet (Dolibarr 22 API limitation; TODO: use addlinefree on Dolibarr 23+)

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
