# Digital Compliance Ledger

The Digital Compliance Ledger preserves significant environmental and Sana Oil
program events for review. It differs from `audit_logs`: the audit log records
security and administrative actions, while the ledger records material program
events tied to establishments, equipment, surrenders, and incentives.

The application appends `ESTABLISHMENT_REGISTERED`, `DEVICE_REGISTERED`,
`GREASE_TRAP_REGISTERED`, `ALERT_TRIGGERED`, `ALERT_ACKNOWLEDGED`,
`ALERT_RESOLVED`, `OIL_SURRENDER_SUBMITTED`, `OIL_SURRENDER_REVIEW_STARTED`,
`OIL_SURRENDER_APPROVED`, `OIL_SURRENDER_REJECTED`, `INCENTIVE_CALCULATED`, and
`INCENTIVE_DISTRIBUTED` from their corresponding workflows.

Each event has a visible code, UTC event timestamp, description, optional actor,
and typed links to an establishment, grease trap, device, and related record.
Indexed fields support future reporting by date, event type, establishment,
equipment, and related transaction.

The staff ledger page provides filters and detail views. It contains no edit or
delete controls. Application workflows only append events; corrections must add
a new event. Unique dedupe keys such as `INCENTIVE_CALCULATED:<transaction-id>`
prevent retries from duplicating an event. Incentive and distribution ledger
writes share the same database transaction as the business record and audit log,
so a failed operation leaves no false compliance event.

Phase 7 queries and exports this data through authenticated HTML, CSV, and PDF
reports. Reporting remains read-only and does not merge ledger events with the
separate technical Audit Log.
