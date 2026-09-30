# Hybrid Verification

Phase 5 combines three evidence sources for a human Barangay decision:

```text
Owner surrender details
        +
Protected photo evidence
        +
Nearby IoT sensor telemetry
        |
        v
Barangay human review
        |
        +--> APPROVED
        `--> REJECTED
```

An owner submission starts as `PENDING`. An administrator may claim it as
`UNDER_REVIEW`, compare the submitted quantity and photo with readings around the
submission time, then approve or reject it. Rejection requires a reason. All
major events remain in the audit log and compliance ledger.

The initial evidence window is six hours before and six hours after submission.
Administrators can change it in System Settings; `oil_surrender_telemetry_window_hours`
is validated between 1 and 168 hours. The review page displays the nearest level
before and after submission, observed percentage-point change, distance,
temperature, device, status, and the detailed readings in that window.

Telemetry is supporting evidence. AQUASENSE+ does not claim that sensor readings
mathematically prove the submitted oil quantity because no validated volume or
weight conversion model exists. Missing telemetry is shown plainly and does not
automatically reject a submission. Photo authenticity is also assessed manually;
Phase 5 contains no AI image verification.

Approving a surrender marks it as eligible input for Phase 6. It does not create
an incentive transaction, calculate rice, update a reward balance, or distribute
anything.
