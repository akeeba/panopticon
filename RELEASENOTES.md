## Highlights

**Security.** This release fixes a critical vulnerability in API token management, along with several lower-severity authorisation and CSRF hardening issues across the sites batch processing, log viewer, self-update tasks, extension/plugin update scheduling, the PHP information page, logout / consent decline / connection doctor actions, and the reports view. We strongly recommend updating as soon as possible; see the CHANGELOG for the full, itemised list.

**New: optional MCP server.** Panopticon can now expose an MCP (Model Context Protocol) server, letting AI agents and chatbots query and manage your monitored sites through a standard tool-calling interface. This is opt-in and disabled by default.

**Core File Integrity Check now supports WordPress.** Previously Joomla-only, the checksum-based core file integrity check now also works against WordPress sites.

**Configurable checksums source.** The base URL used to fetch Core File Integrity checksums is now configurable, for environments that need to point at a custom or mirrored source.

**Other changes.** The main software update source has moved to `https://getpanopticon.com/update.json`. The Template Overrides view now consistently requires the per-site admin privilege for both the view and its entry button. German (de-DE) machine translation has been added.

Also fixes a fatal error when reporting extension/plugin update results, and tightens authorisation checks on site refresh/update scheduling that had previously been too permissive.
