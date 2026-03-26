# Warranty & Service Management — Test Plan

**Modules:** warrantysvc v1.21.1 + customerreturn v2.0.7
**Environment:** Dolibarr 22.0.4 on digitalproperties.works/staging

---

## Prerequisites

- [ ] Both modules installed and enabled
- [ ] At least one warehouse configured
- [ ] At least one customer (third party, type=customer)
- [ ] At least one product with serial/lot tracking enabled
- [ ] Stock module enabled
- [ ] Shipments module enabled
- [ ] Orders module enabled

---

## 1. WARRANTY LIFECYCLE

### 1.1 Create Warranty Manually
- [ ] Navigate to Warranties > New Warranty
- [ ] Select customer, product, serial number, warranty type
- [ ] Verify coverage dates auto-calculate from default coverage days
- [ ] Save — verify warranty card shows all fields
- [ ] Verify warranty appears in Warranty List with correct filters

### 1.2 Auto-Create Warranty on Shipment (if enabled)
- [ ] Settings > enable "Auto-create warranty on shipment validation"
- [ ] Create a Sales Order with a serialized product
- [ ] Create and validate a shipment from that SO
- [ ] Verify a warranty record was auto-created for each serialized line
- [ ] Verify warranty links to the shipment and order

### 1.3 Void Warranty
- [ ] Open a warranty card > click "Void"
- [ ] Confirm void — verify status changes to Voided
- [ ] Verify voided warranty cannot be used when creating a new SR

### 1.4 Warranty on Product Card
- [ ] Open a product card (serialized product)
- [ ] Verify "Warranty Default Type" and "Default Coverage Days" rows appear
- [ ] Set a default type and days > save
- [ ] Create a new warranty for this product — verify defaults pre-fill

---

## 2. SERVICE REQUEST LIFECYCLE

### 2.1 Create Service Request
- [ ] Navigate to Service Requests > New Service Request
- [ ] Select customer, product, serial number
- [ ] Verify warranty auto-lookup occurs (if serial has active warranty)
- [ ] Select resolution type (try each: component, component_return, swap_cross, swap_wait, intervention, guidance, informational)
- [ ] Save — verify SR created in Draft status

### 2.2 Warranty Claim Count
- [ ] Note the claim_count on the linked warranty BEFORE creating the SR
- [ ] Create an SR linked to that warranty
- [ ] Verify warranty claim_count incremented by 1
- [ ] Create a second SR for the same warranty — verify count is now +2

### 2.3 Status Transitions
- [ ] **Draft → Validated:** Click Validate > confirm. Verify status badge changes. If assigned technician set, verify notification email sent.
- [ ] **Validated → In Progress:** Click "Set In Progress" > confirm. Verify status changes.
- [ ] **In Progress → Awaiting Return:** (only for return-type resolutions) Click "Set Awaiting Return" > confirm.
- [ ] **Awaiting Return → In Progress:** (via return reception — see section 4)
- [ ] **In Progress → Resolved:** Click "Mark Resolved" > confirm.
- [ ] **Resolved → Closed:** Click "Close" > confirm.
- [ ] **Draft → Cancelled:** Click "Cancel" > confirm. Verify SR shows as Cancelled.
- [ ] **Cancelled → Draft:** Click "Re-open" > verify SR returns to Draft.

### 2.4 Edit Service Request
- [ ] In Draft or Validated status, click Edit
- [ ] Modify label, description, resolution type, assigned user, notes
- [ ] Save — verify changes persisted
- [ ] Verify editing is blocked when Closed

### 2.5 Component Lines
- [ ] In edit mode, add a component line (product, qty, description)
- [ ] Save — verify line appears in the lines table
- [ ] Add a second line, delete the first — verify correct behavior
- [ ] Verify lines are read-only when not in edit mode

### 2.6 Notes Tab
- [ ] Click Notes tab on an SR
- [ ] Add a public note and a private note
- [ ] Save — verify both persist
- [ ] Verify public/private visibility labels are correct

---

## 3. OUTBOUND REPLACEMENT (Sales Order)

### 3.1 Create Replacement Order
- [ ] On an SR with resolution type `component` or `swap_cross`
- [ ] Verify "Replacement Order" row appears in RMA Actions panel
- [ ] Click "Create Replacement Order"
- [ ] Verify Dolibarr's native SO creation page opens
- [ ] Verify customer is pre-filled
- [ ] Create the SO with a product line
- [ ] Return to the SR card — verify SO link now appears in the Replacement Order row
- [ ] Verify the SO also shows the SR in its Linked Objects section

### 3.2 Resolution Types Without Outbound
- [ ] Create an SR with resolution type `guidance` or `informational`
- [ ] Verify the Replacement Order row does NOT appear in RMA Actions
- [ ] Verify the Return Reception row does NOT appear

---

## 4. INBOUND RETURN — Customer Returns Integration

### 4.1 Enable Integration
- [ ] Navigate to Warranty/RMA > Setup
- [ ] Verify "Use Customer Returns module" checkbox is visible
- [ ] If customerreturn module is not enabled, verify the checkbox is disabled with warning message
- [ ] Enable the customerreturn module, return to setup page
- [ ] Check the "Use Customer Returns module" checkbox > Save
- [ ] Verify setting persisted on page reload

### 4.2 Create Return from SR Card
- [ ] Open an SR with resolution type `component_return` or `swap_cross` (must be Validated or In Progress)
- [ ] Verify the Return Reception row shows "Create Customer Return" button (not the old Reception form)
- [ ] Click "Create Customer Return"
- [ ] Verify customerreturn creation page opens with:
  - Customer pre-filled
  - Shipment pre-filled (if SR has a linked shipment)
  - `from_svcrequest` param in URL
- [ ] Select shipment lines and quantities to return
- [ ] Select receiving warehouse
- [ ] Click Create
- [ ] Verify return created successfully with ref (RT-YYMM-NNNN format)

### 4.3 Verify Linking
- [ ] On the new customer return card, check Linked Objects section
- [ ] Verify the SR appears as a linked object
- [ ] Go back to the SR card
- [ ] Verify the Return Reception row now shows the customer return ref as a link
- [ ] Click the link — verify it opens the correct customer return

### 4.4 Validate Return (Stock Movement)
- [ ] On the customer return card, click "Validate"
- [ ] Confirm validation
- [ ] Verify status changes to Validated
- [ ] Navigate to Products > Stock > Movements
- [ ] Verify a stock movement entry exists for each returned line:
  - Type: reception (input)
  - Product: correct product
  - Warehouse: the one selected during creation
  - Qty: positive (items received)
  - Label references the return ref

### 4.5 Auto-Advance SR on Return Validation
- [ ] After validating the return (step 4.4), go back to the SR card
- [ ] If SR was in "Awaiting Return" status: verify it advanced to "In Progress"
- [ ] Verify `date_return_received` is now populated on the SR

### 4.6 Close Return & Credit Note
- [ ] On a Validated customer return, click "Close"
- [ ] Verify status changes to Closed
- [ ] If "Create Credit Note" button appears, click it
- [ ] Verify a draft credit note (Facture avoir) is created
- [ ] Verify the credit note appears in the return's Linked Objects

### 4.7 Fallback Without Integration
- [ ] Disable "Use Customer Returns module" in warrantysvc settings
- [ ] Open an SR with a return-type resolution
- [ ] Verify the Return Reception row shows the old inline Reception form (product/serial pre-filled from warranty, warehouse picker)
- [ ] Re-enable the setting for remaining tests

---

## 5. CUSTOMER RETURNS MODULE (Standalone)

### 5.1 Create Return from Shipment Card
- [ ] Open a validated shipment card
- [ ] Verify "Create Return" button appears (injected by customerreturn hooks)
- [ ] Click it — verify customerreturn creation page opens with shipment pre-selected
- [ ] Create the return — verify it links back to the shipment

### 5.2 Create Return from List
- [ ] Navigate to Products > Customer Returns > New Customer Return
- [ ] Select a customer
- [ ] Select a shipment from the AJAX-loaded list
- [ ] Select lines and quantities
- [ ] Create — verify successful

### 5.3 Customer Return List
- [ ] Navigate to Products > Customer Returns > List
- [ ] Verify list shows all returns with ref, customer, status, date
- [ ] Test filters: search by ref, filter by status
- [ ] Verify sort works on columns

### 5.4 Sidebar Navigation
- [ ] Navigate to Products section
- [ ] Verify "Customer Returns" heading appears in sidebar below Receptions
- [ ] Verify icon and text alignment matches Shipments/Receptions headings
- [ ] Click "New Customer Return" — verify creation page opens
- [ ] Click "List" — verify list page opens

### 5.5 Admin Settings
- [ ] Navigate to Customer Returns setup page
- [ ] Set default receiving warehouse
- [ ] Verify setting persists after save

---

## 6. CROSS-MODULE LINKED OBJECTS

### 6.1 "Link to" Dropdown
- [ ] On any third party card, click "Link to..."
- [ ] Verify "Service Request" and "Customer Return" appear in the dropdown
- [ ] Select one and link — verify it appears in Linked Objects

### 6.2 Bidirectional Display
- [ ] Create an SR linked to a warranty, a sales order, and a customer return
- [ ] On the SR card: verify warranty, SO, and return all show in linked objects or action panel
- [ ] On the warranty card: verify the SR appears in the claims history table
- [ ] On the SO card: verify the SR appears in linked objects
- [ ] On the customer return card: verify the SR appears in linked objects

---

## 7. SETTINGS VERIFICATION

| Setting | Test |
|---------|------|
| Default Coverage Days | Create a warranty — verify coverage end date = start + N days |
| Return Grace Days | Set to 3 days. Create SR, set to awaiting return. After 3 days, verify overdue indicators |
| Auto Warranty Check | Create SR with serial — verify warranty_status auto-populates |
| Warranty Requires Lots | Enable, then create SR — verify product picker only shows lot-tracked items |
| Numbering Model | Verify SR and warranty refs follow the configured pattern |

---

## 8. EDGE CASES

- [ ] Create SR without a warranty (product not under warranty) — verify it works, warranty_status = "not_covered"
- [ ] Create SR for an expired warranty — verify warranty_status = "expired"
- [ ] Create multiple returns for the same shipment — verify qty_already_returned accumulates correctly
- [ ] Attempt to validate a return when warehouse stock module is disabled — verify graceful error
- [ ] Create a customer return with `from_svcrequest` but warrantysvc module disabled — verify return creates normally without SR linking

---

## Test Results

| Section | Pass | Fail | Notes |
|---------|------|------|-------|
| 1. Warranty Lifecycle | | | |
| 2. Service Request Lifecycle | | | |
| 3. Outbound Replacement | | | |
| 4. Inbound Return Integration | | | |
| 5. Customer Returns Standalone | | | |
| 6. Cross-Module Linked Objects | | | |
| 7. Settings Verification | | | |
| 8. Edge Cases | | | |
