# Ticket Duplicator — osTicket Plugin

Adds a **Duplicate** button to the staff ticket-view toolbar. Bulk-create identical copies of any ticket with a single click.

## Features

- **Bulk duplication** — enter a total count (e.g. `10`) and the plugin creates 9 new tickets (the original counts as 1).
- Copies subject, department, help topic, user, organization, priority, SLA, and **all custom form fields**.
- Copies the first message / internal note body into each duplicate.
- Internal note on **each duplicate** links back to the original ticket.
- Summary note on the **original** ticket lists all duplicates created.
- Opens the first new ticket in a new browser tab.
- Configurable subject prefix, priority/SLA/assignment copy toggles.
- Respects staff permissions — agents can only duplicate tickets they have access to.
- Works with PJAX navigation and the **osTicketAwesome** theme.

## Requirements

| Component | Version |
|-----------|---------|
| osTicket  | 1.18+   |
| PHP       | 8.0+    |

## Installation

1. Download or clone this repository into your osTicket plugins directory:

   ```bash
   cd /path/to/osticket/include/plugins
   git clone https://github.com/ChesnoTech/osTicket-ticket-duplicator.git ticket-duplicator
   ```

2. In the **Admin Panel** go to **Manage > Plugins > Add New Plugin**.
3. Select **Ticket Duplicator** and click **Install**.
4. Enable the plugin instance.

## Configuration

**Admin Panel > Manage > Plugins > Ticket Duplicator > Instances > (your instance)**

| Setting         | Default        | Description                                            |
|-----------------|----------------|--------------------------------------------------------|
| Subject Prefix  | `[Duplicate] ` | Text prepended to the subject of each duplicate.       |
| Copy Priority   | Yes            | Copy the priority level from the original ticket.      |
| Copy SLA        | Yes            | Copy the SLA plan from the original ticket.            |
| Copy Assignment | No             | Copy the staff/team assignment from the original.      |

## Usage

1. Open any ticket in the **Staff Panel**.
2. Click the **copy** icon in the ticket action bar.
3. Enter the **total number of tickets** you want (including the original).
   - `10` → creates **9** duplicates.
   - `50` → creates **49** duplicates.
   - Maximum **200** duplicates per operation.
4. Confirm the prompt.
5. The first duplicate opens in a new tab; a success banner shows the ticket-number range.

## Plugin Structure

```
ticket-duplicator/
├── plugin.php                        # Manifest (id, version, entry point)
├── config.php                        # PluginConfig — admin settings
├── class.TicketDuplicatorPlugin.php  # Bootstrap, AJAX routes, asset injection
├── class.TicketDuplicatorAjax.php    # AJAX controller (duplicate + serve assets)
└── assets/
    ├── ticket-duplicator.js          # Client-side UI
    └── ticket-duplicator.css         # Button styling
```

## License

[GPL-2.0](LICENSE) — same as osTicket.

## Author

**ChesnoTech** — [chesnotech.com](https://chesnotech.com)
