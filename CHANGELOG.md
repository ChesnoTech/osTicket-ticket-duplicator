# Changelog

All notable changes to the **Ticket Duplicator** plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.1] - 2026-04-08

### Fixed
- **Plugin ID namespace**: changed from `osticket:ticket-duplicator` to `chesnotech:ticket-duplicator` — the `osticket:` prefix is reserved for official plugins.

## [1.3.0] - 2026-04-08

### Added
- **Minor / Major update selection**: admins can now see available updates categorized as "Minor / Patch" or "Major", each with its own install button and visual styling.
- **Release notes viewer**: expandable release notes for each available update, pulled from GitHub Releases.
- **Tag-specific downloads**: updates install a specific tagged version from GitHub instead of the master branch, ensuring reproducible installs.

### Changed
- **GitHub Releases API**: update checking now uses the Releases API (`/repos/.../releases`) instead of reading `plugin.php` from the repo contents. Returns all available versions in one call.
- **Semver parsing**: versions are parsed into major.minor.patch components to categorize updates correctly.
- **ZIP prefix auto-detection**: `extractAndOverwrite()` dynamically detects the root folder inside GitHub archive ZIPs instead of hardcoding `repo-branch/`.
- **Redesigned update panel**: replaced the single-line banner with a card-based panel showing separate minor and major update cards, version jumps, warning for major updates, and collapsible release notes.
- **Dedicated updater CSS**: update panel styles moved from `ticket-duplicator.css` to `td-updater.css`, loaded only on the plugins page.

## [1.2.0] - 2026-04-04

### Changed
- **DRY bootstrap**: `bootstrap()` now delegates to `bootstrapStatic()` instead of duplicating the registration code.
- **Cached update checks**: GitHub API results are cached for 15 minutes in a temp file, avoiding HTTP calls on every admin page load. Cache is cleared after a successful install.
- **Auto-rollback on failure**: If file extraction fails mid-update, the plugin automatically restores files from the backup that was created before the install attempt.
- **`pre_upgrade()` hook**: Integrates with osTicket's native plugin upgrade lifecycle — backs up DB config before osTicket applies version changes.

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

[1.3.1]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ChesnoTech/osTicket-ticket-duplicator/releases/tag/v1.0.0
