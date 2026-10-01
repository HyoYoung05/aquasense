# Sana Oil Incentive Processing

Phase 6 processes rice rewards through one server-controlled workflow:

```text
APPROVED oil surrender
  -> active rule lookup for the same oil unit and effective date
  -> backend calculation
  -> immutable rule/result snapshot
  -> CALCULATED incentive transaction
  -> administrator confirms physical distribution
  -> DISTRIBUTED transaction and Compliance Ledger event
```

No production conversion rate is built in. A Barangay administrator must create
the approved policy in **Incentives > Manage rules**. Any development example,
such as 5 L to 2 kg, must be marked **DEVELOPMENT / TEST DATA**.

## Rules and units

Rules use the surrender units currently supported by the project: `L` and `kg`.
Volume and mass are never converted. Rice rewards may use `kg` or `g`, and totals
remain grouped by unit.

- `FIXED_PER_THRESHOLD` uses complete blocks only. For a test rule of 5 L to
  2 kg, 12 L produces `floor(12 / 5) = 2` blocks and 4 kg. It never rounds up.
- `FIXED_TRANSACTION` grants the configured reward once when the surrender meets
  the minimum quantity.

Active rules for the same oil unit cannot overlap in date. Editing a rule does
not recalculate old transactions because every transaction stores the rule name,
threshold, reward, calculation type, unit, qualifying block count, and result.

## Eligibility and duplicate protection

Only `APPROVED` surrenders are eligible. `PENDING`, `UNDER_REVIEW`, and `REJECTED`
records are rejected by the service. Processing locks the surrender row, checks
for an existing transaction, and writes the incentive, compliance event, and
audit event in one database transaction. A unique database constraint on
`incentive_transactions.oil_surrender_id` protects against simultaneous requests.

## Distribution

Administrators can transition `CALCULATED` or `APPROVED_FOR_DISTRIBUTION` to
`DISTRIBUTED` after an explicit confirmation. The system records the distributor,
UTC time, notes, audit event, and compliance event in one transaction. Phase 6
does not provide reversal. A future correction must append a dedicated correction
record instead of rewriting or deleting history.

The owner app reads server totals and history through
`GET /api/mobile/incentives.php`. It cannot select an owner, calculate a reward,
or update distribution state.
