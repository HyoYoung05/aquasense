# Reporting, exports, and audit review

Phase 7 provides authenticated Barangay staff reports at `admin/reports.php`.
Administrator and environmental-staff accounts use the same read-only reporting
permissions already applied to operational pages. Owner accounts cannot sign in to
the administrative website, and the owner mobile API exposes no global report route.

## Report periods and filters

The presets are Today, Last 7 Days, This Week, Last 30 Days, This Month, and
Custom. A week runs Monday through Sunday. Dates are interpreted in the configured
application timezone (`Asia/Manila` by default) and converted to UTC query bounds.
Custom ranges are inclusive by calendar date and limited to 367 days.

Reports cover compliance overview, establishment detail, grease traps, telemetry,
alerts, oil surrenders and hybrid evidence indicators, incentives, rice
distribution, rule history, device status, Compliance Ledger events, and technical
audit actions. Common filters include date, establishment, trap, device, status or
event, user, search, page size, and whitelisted sorting. Selected filters are copied
to pagination and export URLs.

Missing measurements are displayed as `No data` or `Not available`. Oil and rice
totals remain grouped by their stored units. The system does not infer oil quantity
from telemetry or treat missing readings as zero.

## CSV and PDF

`admin/report-export.php` repeats staff authorization and filter validation before
querying. CSV output uses PHP's standard CSV writer and prefixes cells whose first
meaningful character is `=`, `+`, `-`, or `@` to prevent spreadsheet formula
execution. Detailed CSV export is capped at 10,000 rows.

PDF output uses Dompdf 3.1.6 through the isolated `includes/report-export.php`
adapter. Remote resources and embedded PHP are disabled. A PDF contains the report
name, period, generator, filter summary, calculated summary, table, and page number.
PDF detail is capped at 500 rows; use CSV or narrower filters for larger raw
telemetry ranges. Files are streamed to the authorized user and are not retained on
the server.

Each download is a snapshot of database data at generation time. Later database
changes do not update an already downloaded file. Successful exports create
`REPORT_CSV_EXPORTED` or `REPORT_PDF_EXPORTED` technical audit entries. Report
contents are not copied into the audit log.

## Printing and production

The formal `admin/compliance-report.php` view and report tables use print CSS that
hides navigation, filters, buttons, and other controls. Production installation must
run `composer install --no-dev --classmap-authoritative` after deployment. The
application uses no XAMPP path, localhost address, Windows command, permanent export
directory, or production secret in reporting code.

The formal report supports administrative review. Its automated counts depend on
device availability, assignments, configured thresholds, and received data; they do
not replace physical inspection or an authorized Barangay decision.
