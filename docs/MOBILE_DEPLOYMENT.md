# AQUASENSE+ Owner App Deployment

Version: `0.8.0+14` (Mobile Phase 7 release candidate)

This guide contains no real password, signing secret, token, or private key.

## 1. Requirements

- Flutter 3.41.6 or a compatible stable Flutter release
- Dart 3.11 or compatible Dart 3 SDK
- Android SDK with the platform/build tools selected by Flutter
- Java 17
- An always-on AQUASENSE+ PHP/MySQL deployment
- A public HTTPS Owner API endpoint for production

Run `flutter doctor -v` and resolve Android toolchain errors before building.

## 2. Install dependencies

From `mobile/`:

```powershell
flutter pub get
flutter analyze
flutter test
```

## 3. Configure the API

`API_BASE_URL` must be the Owner API root and include `/api/mobile`. Do not add
an endpoint filename.

Development only, using an explicit local address:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.x.x/AQUASENSE+/aquasense-web/api/mobile
```

Production:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://YOUR-PRODUCTION-DOMAIN/api/mobile
flutter build appbundle --release --dart-define=API_BASE_URL=https://YOUR-PRODUCTION-DOMAIN/api/mobile
```

Release builds reject HTTP. A build with no URL deliberately opens a visible
Server Configuration Error screen.

## 4. Android permissions and transport

The main manifest requests Internet and Camera. Camera hardware is optional so
gallery upload remains available on devices without a camera. The release
manifest does not enable broad cleartext networking. A debug-only manifest
permits explicitly configured local HTTP development.

## 5. Configure release signing

Generate and protect a production upload keystore outside version control.
Copy `mobile/android/key.properties.example` to the ignored
`mobile/android/key.properties`, then set:

```properties
storeFile=C:/secure/path/aquasense-owner-upload.jks
storePassword=YOUR_SECRET
keyAlias=YOUR_ALIAS
keyPassword=YOUR_SECRET
```

Do not commit `key.properties`, the keystore, passwords, or recovery material.
Back up the keystore and credentials in a controlled secure location. Phase 7
validation artifacts were explicitly allowed to use debug signing and are not
production-distribution artifacts.

## 6. Application identity

The current Android application ID is `com.example.aquasense_mobile`. It was
preserved so existing test installations continue to upgrade. Before public or
managed distribution, the project owner must decide whether to keep it or make
a one-time migration to an organization-controlled ID. Changing it later
creates a different Android application.

## 7. Generate and locate artifacts

After production signing and the final domain are configured, run the two
production commands above. Flutter writes the outputs under:

```text
mobile/build/app/outputs/flutter-apk/app-release.apk
mobile/build/app/outputs/bundle/release/app-release.aab
```

Install the APK on a supported Android device for direct testing. Use the AAB
for Play-compatible distribution workflows.

## 8. Production verification

Before distributing:

1. Verify the certificate and hostname from the device network.
2. Confirm `API_BASE_URL` ends at the deployed Owner API root.
3. Confirm production sample accounts were not automatically provisioned.
4. Verify login, restoration, logout, session expiry, and server-down behavior.
5. Complete the owner workflow in `MOBILE_DEMO.md` with two Owner accounts for
   isolation checks.
6. Verify physical ESP32 telemetry reaches the same deployed database and then
   appears in Dashboard, Monitoring, history, and Alerts.
7. Submit valid and invalid evidence photos and verify protected retrieval.
8. Confirm Barangay review and incentive distribution update the Owner app.
9. Record APK/AAB checksums and securely retain the exact source revision.
10. Complete every item in `MOBILE_PRODUCTION_CHECKLIST.md`.

Tailscale, XAMPP, a LAN IP, and the developer computer must not be part of this
production verification path.
