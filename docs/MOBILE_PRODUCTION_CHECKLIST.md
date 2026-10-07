# AQUASENSE+ Mobile Production Checklist

Version: `0.8.0+14` (Mobile Phase 7 release candidate)

Checked items were verified in source or automated tests on 2026-10-07.
Deployment-dependent items remain unchecked until performed against the final
public environment and production-signed build.

- [ ] `API_BASE_URL` points to the final public HTTPS Owner API.
- [x] Release HTTP URLs are rejected.
- [x] Production code has no Tailscale dependency.
- [x] Production code has no XAMPP dependency.
- [x] Production code has no localhost or LAN fallback.
- [x] Missing/invalid API configuration renders a visible error screen.
- [x] Authentication behavior is covered by automated tests.
- [x] Session restoration, expiry, and transport-failure behavior are covered.
- [x] Local logout succeeds when the backend is unavailable.
- [x] Dashboard integration is implemented and tested.
- [x] Monitoring/current telemetry is implemented and tested.
- [x] Telemetry history and charts are implemented and tested.
- [x] Alerts list/history/detail are implemented and tested.
- [x] Oil Surrender submission/history/detail are implemented and tested.
- [x] Photo selection, validation, multipart upload, and protected retrieval are implemented.
- [x] Incentive summary/history/detail are implemented and tested.
- [x] No official incentive formula exists in Flutter.
- [x] Flutter has no direct MySQL access or embedded database credentials.
- [ ] Owner isolation is manually verified with two accounts on production.
- [x] Server-unavailable and invalid-response states have automated coverage.
- [x] Polling uses one lifecycle-aware timer per active polling screen.
- [x] Android permissions are limited to Internet and optional Camera.
- [x] Release manifest does not broadly allow cleartext networking.
- [x] Launcher icon and splash use AQUASENSE+ artwork.
- [x] `flutter analyze` passes.
- [x] Entire `flutter test` suite passes.
- [x] Release-mode validation APK builds with an HTTPS URL.
- [x] Release-mode validation AAB builds with an HTTPS URL.
- [x] No-URL release-mode validation APK builds.
- [ ] Production signing keystore and ignored `key.properties` are configured.
- [ ] Android application ID is approved for final distribution.
- [ ] APK is installed and accepted on small, standard, and large Android devices.
- [ ] Keyboard, Back navigation, touch targets, and screen-reader basics are manually accepted.
- [ ] Complete Owner workflow passes against the public deployment.
- [ ] Physical ESP32-to-Mobile telemetry/history path passes.
- [ ] Alert creation, resolution, and device-offline recovery pass end to end.
- [ ] Oil Surrender approval/rejection and Incentive distribution pass end to end.
- [x] App version is set to release candidate `0.8.0+14`.
- [x] README, VERSION, architecture, deployment, security, demo, and checklist documents exist.

The unchecked final-domain, signing, identity, installed-device, and public
end-to-end items are release blockers. Do not distribute the validation APK or
AAB as a production build.
