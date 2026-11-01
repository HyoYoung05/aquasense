# AQUASENSE+ Mobile Deployment

The app contains no runtime fallback server hostname. Every build receives the
one central API URL through `API_BASE_URL`; all services use that value.

Create the ignored local development configuration from its example:

```powershell
Copy-Item config/api.development.example.json config/api.local.json
```

Development may use an emulator alias or LAN address. The public, non-secret
production URL is tracked in `config/api.production.json`; credentials never
belong in this file.

Development:

```powershell
flutter run --dart-define-from-file=config/api.local.json
```

Production:

```powershell
.\build-production.ps1 -Target apk -Mode release
```

Release builds reject HTTP. A missing or malformed value stops authentication
with a configuration message instead of silently contacting a local machine.
Changing environments requires a rebuild because the API address is compile-time
configuration. Database credentials never belong in Flutter.

## Startup configuration failures (0.2.0+8)

Missing, malformed, or release-HTTP API configuration opens a visible Server
configuration error screen and instructs the operator to rebuild the APK. It no
longer prevents Flutter from painting its first frame. Offline or unavailable
servers continue through the normal Retry/Sign out screen.

The base URL validator also rejects embedded credentials, query strings,
fragments, and unsafe endpoint paths. Debug Android permits explicitly
configured local HTTP through its debug-only manifest. The main/release
manifest does not enable cleartext traffic.

## Release prerequisites

- Replace the placeholder `com.example.aquasense_mobile` application ID
  through an explicit package migration before store distribution.
- Replace debug-key release signing with a protected production signing key.
- Put the real public HTTPS API root in the ignored production configuration.
- Verify login, restoration, interruption/retry, and logout on a physical
  Android device.
- Keep MySQL, phpMyAdmin, upload storage, and backend credentials off the
  public mobile client.
## Flutter Web development CORS (0.1.5+6)

A local Flutter Web debug server chooses a port at runtime. With the backend's
local-preview option enabled, exact `http://localhost:<port>` and
`http://127.0.0.1:<port>` origins are accepted for valid ports. The browser API
URL may point to the development computer's reachable Apache address.

The backend must answer OPTIONS with HTTP 204 before authentication or database
work. Debug web connection failures mention `API_BASE_URL` and CORS/OPTIONS so a
developer can distinguish browser policy setup from a normal offline failure.
Release builds retain a generic message. Production must configure exact HTTPS
web origins and must leave local preview disabled.
