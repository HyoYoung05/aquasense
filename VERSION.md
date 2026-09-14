# AQUASENSE+ Version History

## Current Version

0.1.9

## 0.1.9

Date: 2026-09-14

Development Phase: Phase 1 — GitHub source repository

### Added

- Git repository preparation for the private `HyoYoung05/aquasense` repository.
- `.gitattributes` for consistent text line endings and binary artwork handling.
- README repository and cloning instructions, including which local configuration and runtime data are excluded.

### Changed

- Versioned the current Phase 1 source, documentation, SQL seed, development credential reference, and artwork together.

### Fixed

- None.

### Validation

- PHP syntax checks and Git whitespace checks passed before upload.
- The previously passing integration suite could not be rerun for this upload because the configured local MariaDB connection on port 3307 was refused. The live database and its startup task are not part of the GitHub upload.

### Known Issues

- GitHub contains source files, not a hosted PHP/MySQL deployment or a backup of the live local database.
- Existing browser-verification and original XAMPP database-recovery limitations remain unchanged.

## 0.1.8

Date: 2026-09-14

Development Phase: Phase 1 — Development credentials reference

### Added

- `credentials.txt` containing the seeded administrator and environmental staff login details and separate local phpMyAdmin credentials.
- Apache access denial for the credentials reference file.

### Changed

- README links to the local reference and explains that website sign-in uses email addresses rather than separate usernames.

### Fixed

- None.

### Known Issues

- The file documents development defaults only and does not synchronize with future password changes. Existing browser-verification and database-recovery limitations remain unchanged.

## 0.1.7

Date: 2026-09-14

Development Phase: Phase 1 — Screen-edge authentication branding

### Added

- Shared fixed header and footer independent of the centered authentication content.

### Changed

- Anchored the brand at the upper left, the administrative tag at the upper center, the Barangay information at the lower left, and the authorization text at the lower right.
- Kept the version centered in the footer and local-development label at the upper right.
- Narrow screens use additional header/footer rows and retain the Barangay information instead of hiding it.

### Fixed

- Branding and footer placement no longer follow the constrained story/form columns.
- Reserved page padding keeps the fixed header and footer clear of the form; translucent teal surfaces maintain readability while scrolling.

### Known Issues

- Browser visual verification remains outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.6

Date: 2026-09-14

Development Phase: Phase 1 — Compact responsive login spacing

### Added

- Fluid spacing and typography with a centered 1320px maximum content width for large monitors.

### Changed

- Brought the story inward from the left edge and reduced the gap between the story and landscape login card.
- Vertically centered story copy between the brand and community footer; removed the login story's fixed minimum height.
- Stacked outer columns at 1024px, inner card columns at 680px, and reduced padding on screens up to 480px wide.
- Kept the full-page teal palette, centered background artwork, and transparent card styling.

### Fixed

- Excessive horizontal spread on wide monitors and oversized spacing on shorter displays.

### Known Issues

- Rendered browser checks at the target monitor sizes remain outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.5

Date: 2026-09-14

Development Phase: Phase 1 — Transparent landscape login card

### Added

- Login-specific two-column layout with introduction on the left and sign-in fields on the right, up to 800px wide.

### Changed

- Reduced login card background opacity from approximately 91% to 38%, keeping text and controls fully opaque.
- Adjusted the surrounding layout to accommodate the wider card; narrow screens stack the card sections vertically.
- Preserved the teal/green page background and centered water artwork.

### Fixed

- Wider desktop login content no longer remains constrained to the previous 440px portrait card.

### Known Issues

- Browser visual verification remains outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.4

Date: 2026-09-14

Development Phase: Phase 1 — Full-page teal background

### Added

- A full-page gradient using the existing forest teal and emerald green palette.

### Changed

- The transparent water artwork is centered in the browser viewport rather than in the left panel.
- Both authentication columns share the same background. The form uses a translucent dark teal card, light text, and matching input colors.
- The artwork scales to fit desktop and mobile viewports and remains behind interactive content.

### Fixed

- Removed the white half-page background so the color scheme spans the entire authentication page.

### Known Issues

- Browser visual verification remains outstanding. Existing database-recovery limitations remain unchanged.

## 0.1.3

Date: 2026-09-14

Development Phase: Phase 1 — Centered water-art background

### Added

- A responsive background layer for the water-drop artwork on authentication screens.

### Changed

- Water artwork is centered horizontally and vertically within the teal panel, with 22% opacity on desktop and 18% on small screens.
- The original teal panel color remains visible through the transparent image. Branding, copy, and footer stay above the artwork.
- Removed the artwork's orbit rings and floating labels for a clear background treatment.

### Fixed

- Artwork no longer depends on negative margins, a 48% vertical offset, or a separate lower content block for placement.

### Known Issues

- Browser visual verification remains outstanding; existing database-recovery limitations are unchanged.

## 0.1.2

Date: 2026-09-14

Development Phase: Phase 1 — Water-drop artwork update

### Added

- Transparent teal and mint water-drop splash artwork in `assets/images/water-drop-splash.png`, generated with the built-in ImageGen tool from the supplied visual reference.
- Generation prompt recorded in `assets/images/water-drop-splash.prompt.txt`.

### Changed

- Login illustration, dashboard droplet, and both AQUASENSE+ logo marks use the new shared artwork.
- Existing teal/green page colors are preserved; decorative rounded droplet backgrounds are replaced by the transparent splash silhouette.

### Fixed

- Stylesheet URLs now include the application version so updated artwork sizing is loaded after a release.

### Known Issues

- Existing database-recovery and browser-verification limitations remain. No later-phase modules were added.

## 0.1.1

Date: 2026-09-14

Development Phase: Phase 1 — Local database reliability fix

### Added

- A current-user Windows startup task, `AQUASENSE Local Database`, to keep the existing isolated MariaDB instance available independently of the assistant session and start it at sign-in.

### Changed

- This machine's phpMyAdmin entries 1 and 2 both select the working database on port 3307. The original port 3306 configuration is preserved as entry 3.
- README instructions now use the startup task and explain the two compatible working links.

### Fixed

- Temporary database processes stopped between sessions, leaving the working database unavailable.
- Older phpMyAdmin links selected the damaged original database and repeatedly returned error 1932.

### Known Issues

- Original XAMPP MariaDB on port 3306 still requires separate recovery; its database files were not repaired or removed.
- The startup task and phpMyAdmin entries are machine-specific. New installations on healthy XAMPP use the standard SQL import workflow.
- Phase 1's previously documented browser verification and future-module limitations remain.

## 0.1.0

Date: 2026-09-14

Development Phase: Phase 1 — Core Website Foundation

### Added

- Plain PHP project running under XAMPP Apache at `/aquasense/`.
- Separated configuration, local override template, and prepared PDO connection.
- Importable 17-table MySQL/MariaDB schema covering the eventual website modules.
- Fictional development administrator/staff accounts and linked establishment/trap/device seed.
- Role and active-account checks, session expiry/regeneration, login throttling, CSRF-protected login/logout, and password hashing/verification.
- Login, account-help interface, and explicitly unavailable password reset scaffold.
- Reusable responsive sidebar, top bar, authentication layout, and version footer.
- Protected Phase 1 dashboard with account information and authentication activity.
- Authentication audit events, protected error logging, output escaping, security headers, and private-directory restrictions.
- 54 HTTP/database integration checks and a separate inactivity expiry check.
- README covering setup, schema relationships, security, test procedures, later phases, and local troubleshooting.

### Changed

- None; initial release.

### Fixed

- During initial verification, replaced a nonstandard expired-form status with HTTP 403 for XAMPP Apache compatibility.
- Preserved explicit private/no-store response headers instead of letting PHP's session cache limiter replace them.

### Validation

- `schema.sql` and `sample-data.sql` imported successfully with XAMPP MariaDB 10.4.32 in a fresh isolated data directory.
- 54 functional/integrity checks passed through the requested localhost Apache URL.
- Inactivity expiry check and PHP syntax validation passed.

### Known Issues

- Existing default XAMPP MariaDB on port 3306 has pre-existing InnoDB corruption. This machine uses an isolated XAMPP MariaDB instance on loopback port 3307 via ignored local configuration; see README for restart instructions. The original database has not been repaired.
- Browser connection unavailable during implementation; rendered desktop/mobile and keyboard usability review remain outstanding.
- Password recovery has no email delivery, token issuance, or redemption yet.
- No operational monitoring, telemetry API, simulation utility, alerts, management CRUD, photo uploads, surrender approval, incentive calculation, ledger interface, or reporting yet; schema support only.
- ESP32 hardware is not connected. Future telemetry will be simulated during website development; no readings are currently seeded.
- This is a local foundation prototype, not a production deployment or complete capstone release.

## Versioning Policy

Use MAJOR.MINOR.PATCH. Major: architectural or production-level changes. Minor: features or completed development phases. Patch: bug fixes, security fixes, and small improvements. Versions below 1.0.0 indicate prototype development. Update the application constant, README, and this history together for each release.
