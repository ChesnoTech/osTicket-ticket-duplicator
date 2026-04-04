# Changelog

All notable changes to the **Ticket Duplicator** plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-04-04

### Added
- **Auto-updater**: one-click update from the Admin > Plugins page with automatic file and database backup before install.
- **i18n support**: all user-facing strings marked with `/* trans */` for translation extraction; JS strings injected via `window.TDi18n` from PHP.
- **Manual entry fields**: configurable multi-select of custom form fields that agents can fill in per-copy during duplication.

### Changed
- Version check uses GitHub API (`api.github.com`) instead of raw CDN to avoid edge-cache staleness.
- Local version read uses `file_get_contents()` + regex instead of `include` to bypass PHP opcode cache after live update.

### Fixed
- Modal title now extracts ticket number correctly from `h2` text.
- Config page crash caused by undefined `FORM_TABLE` constant (replaced with `FORM_SEC_TABLE`).

## [1.0.0] - 2026-03-25

### Added
- Initial release.
- Duplicate button on ticket view toolbar.
- Bulk duplication with configurable copy count (up to 200).
- Copies subject, department, help topic, user, organization, priority, SLA, and all custom form fields.
- Copies first message / internal note body into each duplicate.
- Internal note on each duplicate linking back to the original ticket.
- Summary note on the original ticket listing all duplicates.
- Opens first new ticket in a new browser tab.
- Configurable subject prefix, priority/SLA/assignment copy toggles.
- Access control: department and help topic filters.
- Real-time progress counter for bulk operations.
- PJAX and osTicketAwesome theme compatibility.

[1.1.0]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/releases/tag/v1.0.0
