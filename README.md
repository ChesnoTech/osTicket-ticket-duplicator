# Ticket Duplicator — osTicket Plugin

Adds a **Duplicate** button to the staff ticket-view toolbar. Bulk-create identical copies of any ticket with a single click.

## Features

- **Bulk duplication** — enter a total count (e.g. `10`) and the plugin creates 9 new tickets (the original counts as 1). Maximum 200 per operation.
- Copies subject, department, help topic, user, organization, priority, SLA, and **all custom form fields**.
- Copies the first message / internal note body into each duplicate.
- Internal note on **each duplicate** links back to the original ticket.
- Summary note on the **original** ticket lists all duplicates created.
- Opens the first new ticket in a new browser tab.
- Configurable subject prefix, priority/SLA/assignment copy toggles.
- **Manual entry fields** — optionally select custom form fields that agents fill in per-copy during duplication.
- **Access control** — restrict the button by department and/or help topic.
- **Auto-updater** — one-click update from Admin > Plugins with automatic file and database backup.
- **i18n ready** — all strings marked for translation extraction.
- Real-time progress counter for bulk operations.
- Works with PJAX navigation and the **osTicketAwesome** theme.

## Requirements

| Component | Version |
|-----------|---------|
| osTicket  | 1.18+   |
| PHP       | 8.0+    |
| PHP ext   | `curl`, `zip` (for auto-updater) |

## Installation

1. Download or clone this repository into your osTicket plugins directory:

   ```bash
   cd /path/to/osticket/include/plugins
   git clone https://github.com/ChesnoTech/osTicket-ticket-duplicator.git ticket-duplicator
   ```

2. In the **Admin Panel** go to **Manage > Plugins > Add New Plugin**.
3. Select **Ticket Duplicator** and click **Install**.
4. Enable the plugin instance.

## Updating

### Automatic (recommended)

1. Go to **Admin Panel > Manage > Plugins**.
2. If a new version is available, an update banner appears at the top of the page.
3. Click **Install Update** — the plugin will:
   - Back up all current plugin files to `include/plugins/td-backups/`
   - Back up plugin database config to a `.sql` file
   - Download the latest release from GitHub
   - Extract and overwrite the plugin files
4. Click **Reload page** to load the new version.

### Manual

1. Back up the `ticket-duplicator/` directory.
2. Download the latest release from the [Releases](https://github.com/ChesnoTech/osTicket-ticket-duplicator/releases) page.
3. Extract and overwrite the plugin directory.
4. Clear your browser cache and reload the admin panel.

## Configuration

**Admin Panel > Manage > Plugins > Ticket Duplicator > Instances > (your instance)**

| Setting              | Default        | Description                                            |
|----------------------|----------------|--------------------------------------------------------|
| Subject Prefix       | `[Duplicate] ` | Text prepended to the subject of each duplicate.       |
| Copy Priority        | Yes            | Copy the priority level from the original ticket.      |
| Copy SLA             | Yes            | Copy the SLA plan from the original ticket.            |
| Copy Assignment      | No             | Copy the staff/team assignment from the original.      |
| Manual Entry Fields  | None           | Custom text fields agents can fill per-copy.           |
| Allowed Departments  | All            | Only agents in these departments can duplicate.        |
| Allowed Help Topics  | All            | Only show the button on tickets with these topics.     |

## Plugin Structure

```
ticket-duplicator/
├── plugin.php                        # Manifest (id, version, entry point)
├── config.php                        # PluginConfig — admin settings
├── class.TicketDuplicatorPlugin.php  # Bootstrap, AJAX routes, asset injection
├── class.TicketDuplicatorAjax.php    # AJAX controller (duplicate, config, assets)
├── class.TicketDuplicatorUpdater.php # Auto-update engine (GitHub API, backup, install)
└── assets/
    ├── ticket-duplicator.js          # Client-side duplication UI
    ├── ticket-duplicator.css         # Button and modal styling
    └── td-updater.js                 # Update banner UI (plugins page only)
```

## Contributing

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/my-change`).
3. Commit your changes with a clear message.
4. Open a pull request against `master`.

Please use the provided [issue templates](.github/ISSUE_TEMPLATE/) for bug reports and feature requests.

## License

[MIT](LICENSE)

## Author

**ChesnoTech** — [chesnotech.com](https://chesnotech.com)
