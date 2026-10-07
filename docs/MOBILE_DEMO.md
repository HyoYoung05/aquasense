# AQUASENSE+ Mobile Capstone Demo

Version: `0.8.0+14` (Mobile Phase 7 release candidate)

## Before the presentation

- Use a deployed HTTPS API or clearly identify a local/staging environment.
- Install the correctly configured, signed APK on the demonstration phone.
- Confirm the Owner, establishment, grease trap, device, and Barangay reviewer
  accounts are prepared.
- Confirm the ESP32 is sending current readings and keep one valid evidence
  image available.
- Never describe simulated or seeded records as physical sensor readings.

## Primary demonstration

1. Open **AQUASENSE+ Owner** and show the visible startup/login screen.
2. Sign in as the Carinderia Owner.
3. Show Dashboard owner, establishment, trap, device, latest level, alerts,
   surrender, and incentive summaries.
4. Change the safe physical sensor distance and show the ESP32 request reaching
   the API.
5. Refresh Dashboard and show the updated backend reading and device status.
6. Open Monitoring and show current measurements.
7. Open history, select a range and sensor, and explain chart gaps for missing
   readings.
8. Show an active alert, alert history, and read-only detail.
9. Open Oil Surrender, enter `5 L`, select the authorized trap if needed, attach
   a valid evidence photo, and submit once.
10. Show the resulting `PENDING` record.
11. From the Barangay website, move the submission through review and approve
    it.
12. Refresh mobile and show `APPROVED` with the Barangay-visible remarks.
13. From the Barangay website, process the incentive.
14. Open Incentives and show the backend-calculated reward and pending status.
15. Mark the reward distributed on the Barangay website.
16. Refresh mobile and show `DISTRIBUTED` and its date.
17. Open Profile, identify the app version, and log out.
18. Confirm Android Back cannot return to authenticated content.

For completeness, repeat the surrender review once with `REJECTED`, and use a
second Owner account to demonstrate that changing record IDs cannot reveal the
first Owner's records or photos.

## Failure demonstrations

- Temporarily stop the API and show a visible unavailable/retry state with no
  black screen and no forced logout.
- Restore the API and retry successfully.
- Use an expired token and show the return to Login.
- Stop the ESP32 long enough for backend offline detection, show the backend
  offline state/alert, reconnect it, and show recovery.

## Clearly labeled fallback plan

If physical hardware or venue networking fails, use previously captured
screenshots/video plus development records explicitly labeled **DEMO DATA** or
**SIMULATED**. Explain where the live step would occur in the same API flow.
Do not alter the UI or narration to present fallback data as a current physical
reading. Continue with the website review, surrender, incentive, and logout
workflow against the available staging environment.
