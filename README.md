# WarrantySvc for Dolibarr

**RMA and Warranty Management for serialized equipment.**

Track warranties by serial number, manage service requests from intake to resolution, and automate return logistics -- all inside Dolibarr.

**Version:** 1.27.2
**License:** GPL-3.0

---

## Features

- **Service Requests** -- Create and track customer complaints and RMA cases through a full lifecycle: Draft, Validated, In Progress, Awaiting Return, Resolved, and Closed.
- **Warranty Tracking** -- Register warranties per serial number with configurable warranty types and coverage durations. Warranty status (Active, Expiring Soon, Expired, Voided) is computed automatically.
- **Automatic Warranty Creation** -- Optionally create warranties when a shipment is validated or an order is closed. Duplicate warranties for the same serial and shipment are prevented automatically.
- **Auto-Void on Resale** -- When a unit is resold to a new customer, the original warranty is voided automatically.
- **Resolution Workflows** -- Choose from Component Shipment, Full Unit Swap (cross-ship or wait-for-return), On-Site Service, Guidance Only, and more. Each type shows only the panels you need.
- **Movement Tracker** -- Track outbound shipments, expected returns, carrier info, and tracking numbers. Overdue returns are flagged automatically.
- **PDF Authorization Slips** -- Generate a printable service authorization document for each service request.
- **Linked Objects** -- Service requests and warranties appear in the Related Objects block on Orders, Shipments, Invoices, and Projects.
- **Warranties Tab on Customer Card** -- View all warranties for a customer directly from their third-party record.
- **Extrafields Support** -- Add custom fields to both warranties and service requests through the standard Dolibarr extrafields system.

---

## Screenshots

### Service Requests List

Filter and manage all open and closed service requests.

![Service Requests List](docs/screenshots/service-requests-list.png)

### Warranty List

View warranty records with status, serial number, customer, and coverage dates.

![Warranty List](docs/screenshots/warranty-list.png)

### New Warranty Form

Create warranties in Standard mode (from a shipment) or Override mode (manual entry).

![New Warranty Form](docs/screenshots/new-warranty-form.png)

### Admin Setup

Configure warehouses, automation rules, and default values.

![Admin Setup](docs/screenshots/admin-setup.png)

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| Dolibarr | 16.0 or later |
| PHP | 7.0 or later |

**Required Dolibarr modules** (must be enabled before activating WarrantySvc):

- Third Parties
- Products
- Stock / Warehouses

**Optional modules** (enable for additional functionality):

- Shipments -- needed for outbound shipment creation and auto-warranty on shipment
- Orders -- enables linking warranties to their origin order
- Projects -- enables project linkage on service requests
- Customer Returns -- enables return receipt workflows

---

## Installation

WarrantySvc is installed using the standard Dolibarr module installer. No command-line access is needed.

1. Download the latest release zip from the [Releases page](https://github.com/zacharymelo/Dolibarr-Warranties/releases) (the file is named `module_warrantysvc-1.27.2.zip`).
2. In Dolibarr, go to **Home > Setup > Modules/Applications**.
3. Click **Deploy an external module** (at the top of the page).
4. Upload the zip file and click **Send**.
5. After the upload completes, find **Warranty & Service Management** in the module list and click the toggle to enable it.
6. Proceed to **Configuration** below.

> **Important:** Do not rename or re-zip the file. Dolibarr expects the archive to contain a `warrantysvc/` directory at the root.

---

## Configuration

After enabling the module, go to **Warranty & Service > Setup** from the left menu to configure it for your business.

### Warehouse Settings

| Setting | What It Does |
|---|---|
| Replacement From Warehouse | The warehouse that replacement and refurbished units are shipped from. |
| Return To Warehouse | The warehouse where returned units are received. |

### Automation Settings

| Setting | What It Does |
|---|---|
| Return reminder delay (days) | How many days after the expected return date to send a reminder to the customer. |
| Auto-invoice after (days) | How many days after the expected return date to automatically create an invoice for unreturned units. |
| Default replacement selection strategy | How the system suggests a replacement unit. Options: FIFO (oldest stock first), Least Serviced, Best Condition, or Manual. |

### Warranty Automation

| Setting | What It Does |
|---|---|
| Auto-check warranty on creation | When a service request is created, the system automatically looks up whether the serial number has active warranty coverage. |
| Auto-create warranty on shipment | Automatically register a warranty for each serialized product line when a shipment is validated. |
| Warranty creation trigger | Controls when auto-created warranties are generated (for example, on shipment close). |

All settings have sensible defaults. You can adjust them at any time without affecting existing records.

---

## Usage Guide

### Creating a Warranty

1. Go to **Warranty & Service > New Warranty**.
2. **Standard mode:** Select a validated shipment. The form will show all serialized product lines from that shipment that do not already have warranty coverage. Choose the serial number, a warranty type, and a start date.
3. **Override mode:** Fill in all fields manually (customer, product, serial number, start date, coverage days). Use this for warranties issued outside of the normal shipment flow.
4. Click **Create** to save.

The warranty status is computed automatically based on the start date and coverage duration.

### Managing Warranty Types

Go to **Warranty & Service > Warranty Types** to create templates such as "Standard 1-Year" or "Extended 2-Year." Each type defines a default coverage duration in days. When staff select a warranty type during warranty creation, the coverage duration fills in automatically.

### Creating a Service Request

1. Go to **Warranty & Service > New Service Request**.
2. Select the customer, product, and serial number.
3. Choose a **Resolution Type** to determine the workflow:
   - **Component Shipment** -- Ship a replacement part (no return needed).
   - **Component Shipment + Return** -- Ship a part and request the old one back.
   - **Full Unit Swap (Cross Ship)** -- Send a replacement now; customer returns the faulty unit later.
   - **Full Unit Swap (Wait for Return)** -- Customer ships the faulty unit first; replacement sent after receipt.
   - **On-Site Service** -- Schedule a technician visit.
   - **Guidance Only** -- Resolve by phone or email with no physical action.
   - **Informational** -- No fault found; logged for records.
4. Fill in the issue description and any other relevant details.
5. Click **Create** to save the request in Draft status.

### Service Request Lifecycle

Move the request through its stages using the status buttons on the request card:

1. **Draft** -- Initial entry. Edit freely.
2. **Validated** -- Request is confirmed and ready for action.
3. **In Progress** -- Work has begun (shipment sent, technician dispatched, etc.).
4. **Awaiting Return** -- Applies to swap and return workflows. The system tracks the expected return date and flags overdue returns.
5. **Resolved** -- The issue has been addressed.
6. **Closed** -- Final state. The case is complete.

You can also **Cancel** a request from any non-closed state, or **Re-open** a closed or cancelled request.

### Generating a PDF Authorization Slip

From any service request card, click the **Generate PDF** button to create a printable service authorization document. This includes the customer details, product info, issue description, and tracking information.

### Viewing Warranties for a Customer

Open any third-party (customer) card and click the **Warranties** tab to see all warranty records for that customer in one place.

---

## Optional Integrations

WarrantySvc works on its own with the required modules listed above. Enabling these optional Dolibarr modules adds extra capabilities:

| Module | What It Enables |
|---|---|
| Shipments | Create outbound shipments directly from service requests. Required for auto-warranty on shipment validation. |
| Orders | Link warranties to their origin sales order. |
| Projects | Attach service requests to a Dolibarr project for grouped tracking. |
| Customer Returns | Manage inbound return receipts within the return workflow. |

Linked objects (orders, shipments, invoices, projects) appear automatically in the Related Objects block on service request and warranty cards.

---

## Support

Report issues or request features on GitHub: [https://github.com/zacharymelo/Dolibarr-Warranties/issues](https://github.com/zacharymelo/Dolibarr-Warranties/issues)

---

## License

This module is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).
