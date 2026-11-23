# warrantysvc — Bugs & UX TODO

Audit of 1.36.1 (2026-11-11). Production: Dolibarr 22, `WARRANTYSVC_USE_CUSTOMERRETURN` on.
Priority: **P1** = data loss / broken flow, **P2** = wrong behaviour users hit, **P3** = polish.
Line numbers are as of 1.36.1.

**Suggested first pass (all small, local fixes):** SR edit wiping fields → Await Return never set → warranty list count/sort SQL → coverage days wiped on warranty save → Documents tab 404 → link-to-object handler → debug SQL console.

---

## 1. Cross-ship booking (swap_cross) — the reported pain point

The intended path is: Diagnosing → pick *Cross-ship* → In Progress → ship replacement → **Await Return** → customer return validated → In Progress → Resolved → Closed. Today it breaks or stalls at several points.

- [x] **P1 — Editing a service request wipes 14 fields.** The `update` handler (`card.php:126-151`) reads `fk_contact`, `fk_project`, `fk_commande`, `serial_in`, `serial_out`, `fk_warehouse_source`, `fk_warehouse_return`, `outbound_carrier`, `outbound_tracking`, `return_carrier`, `return_tracking`, `date_return_expected`, `note_private`, `note_public` from POST, but the edit form renders none of them. Any *Modify → Save* blanks them: the replacement order link (`fk_commande`), replacement serial, tracking numbers, expected-return date and both notes. Fix: only assign fields that were posted (`GETPOSTISSET`), and add the missing inputs to the edit form. **Fixed (unreleased):** the update handler now only assigns posted fields (`GETPOSTISSET`).
- [x] **P1 — Nothing ever moves a case into Await Return.** `SvcRequest::setAwaitingReturn()` is only called by the customer-return *reopen* trigger (`interface_99_…Trigger.class.php:344`). There is no button, action or trigger on shipment validate. Consequences: a shipped cross-ship sits In Progress forever; the *Await Return* list preset is empty; the overdue-return reminder (`svcrequest.class.php:1459`, filters on `STATUS_AWAIT_RETURN`) never fires; the customer-return validate trigger only advances from Await Return, so it does nothing. TECHNICAL.md even lists a `setawaitreturn` action that `card.php` doesn't handle. Fix: on `SHIPPING_VALIDATE` of a shipment linked to an SR whose type is `swap_cross` / `component_return`, call `setAwaitingReturn()` and default `date_return_expected` from `WARRANTYSVC_RETURN_GRACE_DAYS`; also add a manual *Mark awaiting return* button. **Fixed (unreleased):** `SHIPPING_VALIDATE` moves linked In Progress `swap_cross` / `component_return` cases to Await Return and sets the expected-return date from the grace days if empty; In Progress cases with a return type get a *Mark Awaiting Return* button; `setAwaitingReturn()` now refuses from any status other than In Progress.
- [ ] **P2 — "Create Replacement Order" drops the user on a blank sales order.** `card.php:1258` only passes `socid` and `rma_sr_id`. The user then has to add the product line, set price 0, validate, create the shipment, pick the serial and validate it, across four core pages with no way back to the SR except the browser. Fix: pre-fill the order (product = SR product, qty 1, price 0, `ref_client = RMA <ref>`) or reuse `createReplacementShipment()`, and return to the SR after each step (`backtopage`).
- [ ] **P2 — "Ship Replacement Unit" fails unless a refurb warehouse is configured.** `createReplacementShipment()` needs `fk_warehouse_source` or `WARRANTYSVC_WAREHOUSE_REFURB` (`svcrequest.class.php:1043-1047`). `fk_warehouse_source` has no input anywhere in the UI, so if setup is blank the button just errors with *ErrorNoWarehouseSelected*. Fix: prompt for a warehouse (and serial) inline before creating, defaulting from setup.
- [ ] **P2 — "Ship Replacement Unit" disappears after the first order.** Shown only while nothing exists yet (`card.php:1253`). If the first attempt half-worked or an order was linked by hand, the one-click path is gone. Explain the choice of buttons in the panel, or always offer both.
- [ ] **P2 — No guidance on what to do next.** The RMA actions panel shows rows but not which step the case is on. Add a simple step indicator for movement types (Order → Shipped → Return booked → Resolved) with the next action highlighted.
- [ ] **P2 — Status buttons act immediately with no confirmation.** *Validate*, *Begin diagnosis*, *Set in progress*, *Mark resolved* and *Close* link straight to `confirm_*` actions (`card.php:1433-1470`). Close in particular can't be undone without a reopen. Use `formconfirm` like Cancel/Delete do.
- [ ] **P2 — Silent failures on status changes.** `confirm_resolve` (`card.php:207`), `confirm_reopen` (`:232`) and `set_resolution_type` (`:191`) ignore the return value. `resolve()` has no status guard (`svcrequest.class.php:675`), and `set_resolution_type` accepts any string. Check results, show errors, and validate the type against `svcrequest_resolution_types()`.
- [ ] **P3 — Return-reception prefill reads the wrong property.** `card.php:1169` uses `$_war->serial`; the warranty field is `serial_number`, so the serial is never pre-filled. (Only affects the native-Reception fallback; see next item.)
- [ ] **P3 — Native "Create Return Reception" button is offered but broken on v22.** CHANGELOG 1.32.x documents `createReturnReception()` as non-functional on Dolibarr 22, yet `card.php:1346-1366` still shows it whenever the customer-return toggle is off. Hide it (or show a "turn on Customer Returns" hint) until it's fixed.
- [ ] **P2 — Re-open from Cancelled skips the diagnosis gate.** Re-open is offered on Cancelled cases (`card.php:1474`), and `reopen()` jumps straight to In Progress (`svcrequest.class.php:732`). That bypasses Validated and Diagnosing and the resolution-type requirement. Re-open should return Cancelled to Draft or Validated, and Resolved or Closed to the right earlier status.
- [ ] **P3 — Hard-coded English errors** in `card.php:271` (*Shipment module is not enabled*, *Unknown error creating shipment*) and the `Order create failed: …` family in `createReplacementShipment()`.

## 2. Warranty list sorting & paging (`warranty_list.php`)

- [x] **P1 — Pagination is broken: only the first page ever shows.** The count query is made with `preg_replace('/SELECT.*?FROM/s', …)` (`:129`). The lazy match stops at the `FROM` inside the `claim_count` sub-select, so the count SQL is invalid, `$nbtotalofrecords` stays 0 and no page navigation renders. Records past `liste_limit` are unreachable. Fix: build the count query separately. **Fixed (unreleased):** the field list is kept separate (`$sqlfields`) and the count reuses FROM/WHERE; the nav gets rows-remaining, so the last page has no "next" arrow.
- [x] **P1 — Sorting by Claims crashes the list.** The header sorts on `t.claim_count` (`:269`), which is an alias, not a column. MySQL returns *Unknown column*, and the table shows the SQL error instead of rows. Sort on `claim_count`. **Fixed (unreleased).**
- [x] **P1 — Expiry sort doesn't match what's shown.** Default and header sort use `t.expiry_date` (`:66`, `:268`), but the column displays `effective_expiry` (stored date *or* start + type duration). Warranties with a calculated expiry have `expiry_date` NULL and sort to the top, out of order. Sort on `effective_expiry`. **Fixed (unreleased):** default and header sort use `effective_expiry`.
- [ ] **P2 — Status sort doesn't match what's shown.** It sorts on stored `t.status`; the badge is computed from the expiry date (active/expired). Expired warranties still stored as `active` sort among active ones. Sort on the same CASE expression used for display.
- [ ] **P2 — Sorting, paging and presets drop the filters.** `getTitleFieldOfList()` and `print_barre_liste()` get an empty `$param` (`:172`, `:261-270`), so clicking a column header or page loses every search field, `preset` and `socid`. From a third-party's Warranties tab this jumps to the global list. The search form also has no hidden `sortfield`/`sortorder`, so searching resets the sort. Build a `$param` string like core lists do.
- [ ] **P2 — "Expiring soon" in the status dropdown does nothing.** The option exists (`:235`) but the SQL only handles active/expired/voided (`:110-118`); only the preset button works.
- [ ] **P2 — Per-page limit selector is ignored.** `$limit` is hard-wired to `$conf->liste_limit` (`:68`) instead of `GETPOSTINT('limit')`.
- [ ] **P3 — Expiry filters are under the wrong columns.** The *From/To* date pickers sit under *Start date* and *Expiry date* (`:245-250`). Both should be in the Expiry column, or the start column should get its own filter.
- [ ] **P3 — Warranty type sorts by code, not label.**

## 3. Other pages

### Security / production hygiene

- [x] **P1 — `ajax/debug.php` SQL console.** The "starts with SELECT" check plus keyword blocklist (`:342-372`) still allows `SELECT … INTO OUTFILE`, `LOAD_FILE()` and cross-entity reads (for example `llx_user`). Remove it from release builds, or make it read-only and entity-scoped. **Fixed (unreleased):** free-form SQL removed; `?mode=sql&preset=requests|warranties|links|servicelog` runs fixed, entity-scoped queries.
- [x] **P2 — `ajax/debug_links.php` is always live.** It is admin-only (`:12`) but, unlike `debug.php`, not gated by `WARRANTYSVC_DEBUG_MODE`, and it has no entity filter. **Fixed (unreleased):** now also requires `WARRANTYSVC_DEBUG_MODE`.
- [ ] **P2 — Unescaped labels in selects.** `warranty_card.php:243,290` pass serial and product labels to `selectarray` with `nohtmlescape=true`, so they render as raw HTML (possible stored XSS, unverified). The selected value is escaped, so serials containing `&` don't re-select.
- [ ] **P3 — No external-user (`$user->socid`) restriction** in the pages or ajax endpoints (unverified whether external users can hold these rights).

### Service request card / list / troubleshoot

- [x] **P1 — Documents tab is a 404.** `lib/warrantysvc.lib.php:66` links to `document.php`, which doesn't exist. **Fixed (unreleased):** new `document.php` (core upload/list template). Files live in `<dir_output>/svcrequest/<ref>/` so core download access goes through `svcrequest->read`; the tab badge counts that folder.
- [x] **P1 — "Link to…" does nothing on either card.** `card.php` and `warranty_card.php` render core's link-to-object block but never include `core/actions_dellink.inc.php`, so linking and unlinking are silently ignored. **Fixed (unreleased):** both cards include `actions_dellink.inc.php` with `$permissiondellink = $permwrite`.
- [ ] **P2 — SR list paging and sort drop filters, the preset and `projectid`** (`list.php:148-225`, no `$param`). Searching from a project's tab lists every SR. The total count is passed as `$num` (`:157`), so a "next" arrow always shows and leads to an empty page. `limit` is never read (`:69`).
- [ ] **P2 — Troubleshoot: refresh duplicates the session.** There's no redirect after POST (`troubleshoot.php:135`), so F5 saves it again.
- [ ] **P2 — Troubleshoot inflates service counts.** Every save adds 1 to `service_count` (`:103-133`), on top of the `FICHINTER_CLOSE` trigger, which skews the condition scores used to pick replacement units.
- [ ] **P2 — Troubleshoot prev/next arrows break.** The banner arrows use `?ref=` but the page only reads `id` (`:33`, `:150`).
- [ ] **P2 — Troubleshoot saves in any status** (Draft, Closed, Cancelled) and can set `resolution_type` on a Draft (`:56`).
- [ ] **P3 — Compressor steps show for the wrong products.** `lib/troubleshoot.lib.php:74` shows them when the product ref is empty, and the `'oc-'` substring match hits unrelated refs (for example `proc-`).
- [ ] **P3 — One user fetch per row** in `list.php:252`, `warranty_card.php:1366` and `troubleshoot.php:249`.

### Warranty card

- [x] **P1 — Saving a typed warranty wipes its coverage days.** In edit mode the coverage input is `disabled` when a type is set (`warranty_card.php:1106`). Disabled inputs aren't posted, so `update()` writes NULL. Use `readonly` like the create form does. **Fixed (unreleased):** the input is `readonly`, not `disabled` (server-side and in the JS toggle).
- [ ] **P2 — Expiry never recomputes on edit.** The edit form pre-fills the expiry date (`:1119`), so it always counts as a manual override (`:125-130`). Changing start date, type or days leaves expiry stale, despite the "auto-computed" hint.
- [ ] **P2 — Claims count and Claim History disagree.** The count uses `fk_warranty` (`:1072`); the history lists every SR with the same serial (`:1347`), for any customer or product. After a resale the previous owner's claims show on the new owner's warranty.
- [ ] **P2 — Create form loses input after an error.** The start date is re-read as `'int'` from a formatted string and resets to today (`:809`). The expiry override isn't re-filled. JS `sync()` overwrites the posted terms and exclusions with the type defaults.
- [ ] **P2 — A failed save from "create from shipment" drops the user on the manual form** (`:104-107` sets `action=create`), losing the shipment context.
- [ ] **P2 — Deleting a warranty leaves dangling `fk_warranty` on service requests** (`:156`; `SvcWarranty::delete()` doesn't check `svc_request`).
- [ ] **P2 — Void ignores failure.** It always shows *WarrantyVoided* (`:149`). Voiding is also permanent: no un-void, no reason captured, and Modify is hidden afterwards.
- [ ] **P3 — Duplicate serials in the create dropdown.** `ajax/warranty_serials.php:46-49` joins invoices without dedup, so a serial repeats once per invoice.

### Admin / setup / menus

- [ ] **P2 — Numbering-model selector does nothing.** `WARRANTYSVC_ADDON` is never read outside setup, and the `setmod` action runs after page output, so its redirect fails (`admin/setup.php:316-323`).
- [ ] **P2 — Auto Warranty Check looks on but is off.** The box defaults to checked (`setup.php:164`), but `svcrequest.class.php:1675` treats unset as off until an admin saves.
- [ ] **P2 — Renaming a warranty type code orphans warranties.** The code is editable (`warranty_type_list.php:67`) but warranties and product defaults store it, so their cards show a blank type. Delete also has no in-use check and no `confirm=='yes'` (`:100-110`).
- [ ] **P2 — Warranty-only users get no top menu.** It requires service-request read permission (`modWarrantySvc.class.php:200`).
- [ ] **P3 — `warranty_type_list.php:122` uses `$form` before it is created (`:133`).** It only works through a global the top menu sets.
- [ ] **P3 — Product warranty default can be cleared by any product update.** `actions_warrantysvc.class.php:363-379` deletes it on every product `action=update` without `GETPOSTISSET` (unverified that another update path triggers it).

### Missing translations (raw keys on screen)

- [ ] `list.php`: MyOpenRequests, Unassigned. `troubleshoot.php`: ServiceRequest. `warranty_card.php`: ConfirmVoidWarranty, ConfirmDeleteWarranty, DeleteWarranty, ClaimHistory, NoClaims, ExpiringSoon, OriginShipment, SelectThird, SelectProduct. `card.php`: RMAActions, ComponentIn/Out, InterventionCreated, InvoiceCreated, NoReturnReceptionYet, ReminderSent, ReplacementOrder, ReturnReception, ViewWarranty, CustomerReturn. `warranty_type_list.php`: Inactive. Cron: CheckOverdueRMAReturns.
- [ ] Hard-coded English: "Loading..." (`warranty_card.php:589,632`), " hrs" (`:1251`), placeholders in `warranty_type_list.php:223,229`.

### UX — consistency and navigation

- [ ] **P2 — Pick one name.** The top menu says "Warranty", the objects are "Warranty Claims", the code says "Service Request" or "RMA", refs are `SRQ`, and the PDF says "Service Request Authorization". Lifecycle buttons read just "Validate", "Close" and "Cancel"; "Cancel" is easy to confuse with a form's Cancel.
- [ ] **P2 — Project tab is a dead end.** It opens `list.php` with no project banner or tabs, and "New" doesn't pass `fk_project` (`modWarrantySvc.class.php:85`). The third-party Warranties tab does show its header, so the two tabs behave differently.
- [ ] **P2 — No way to start a claim from where users are.** No claims tab on the third party, no warranty or claim tab on products, and no "New claim" button on orders, shipments or the customer card.
- [ ] **P2 — Troubleshoot ends in a dead end.** After saving the user sees a blank checklist with no next step (back to the card, or Set In Progress). The card only links to Troubleshoot while Diagnosing. A "Resolved" outcome only *suggests* a type and doesn't explain that Set In Progress and then Mark Resolved are still needed. Read-only users see an editable form with no Save button.
- [ ] **P2 — SR list is thin.** No Product, Issue date or Project columns. The Assigned and Date filter cells are empty although `search_assigned`, `search_date_*` and `search_warranty` are wired up. No mass actions, export or column selector. Presets aren't sticky and aren't in the left menu, and there's no "Diagnosing" preset.
- [ ] **P3 — Raw numeric IDs.** Warranty edit asks for order and shipment as numbers (`warranty_card.php:1143,1163`); override-mode create asks for a raw order ID (`:889`).
- [ ] **P3 — Create-from-shipment picker.** A plain, non-searchable select of the last 500 shipments, including ones without serials. Save is disabled until a serial is picked, with no message saying why. The start date defaults to today instead of the ship date.
- [ ] **P3 — Inconsistent card layout.** Status badge placement varies by page. Claim History renders below the action buttons, outside the card. Cancel and Re-open use the red delete style. The notes pages are custom instead of core's notes template, and their tab badges are inconsistent.
- [ ] **P3 — Warranty types page belongs in admin** with the other settings. A server-side error loses input, and inline edit uses a plain textarea while create uses the rich editor.
- [ ] **P3 — No module home page.** The top menu lands on the SR list; there's no overview of counts by status, overdue returns or expiring warranties.

## Follow-ups from the P1 fixes

- [ ] **P2 — Generated PDFs land outside the Documents tab.** `pdf_svcrequest_standard.php:113` writes to `<dir_output>/<ref>/`, but documents now live in `<dir_output>/svcrequest/<ref>/`, the only path core lets non-admin users download from. PDF generation isn't wired into the card yet; move it to the new path when it is. Any files already in `<dir_output>/<ref>/` on the server need moving by hand.
- [ ] **P3 — Check the cross-ship flow end to end on staging.** Cross-ship case → Create Replacement Order → ship and validate. The case should go to Await Return; validating the linked Customer Return should bring it back to In Progress.

## 4. Code-quality backlog (tracked, not urgent)

- [ ] `warranty_card.php` has 147 phpcs/phpstan/phan findings (mostly undeclared page globals; add the `@var` / `@phan-var-force` header block). Other files not yet counted.
- [ ] `need_dolibarr_version = 16` (`modWarrantySvc.class.php:76`) is not true: the code uses v20+ module names (`shipping`, `order`) and v22 table columns. Raise to 22 or add fallbacks.
