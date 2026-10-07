# AQUASENSE+ Owner App Security

Version: `0.8.0+14` (Mobile Phase 7 release candidate)

## Authentication and token storage

Owners authenticate with email and password through the PHP API. Passwords are
sent only for login and are never stored by the app. The returned bearer token
is stored with `flutter_secure_storage` under a key namespaced by the validated
API root, preventing accidental reuse against a different backend.

On startup, a stored token is validated through the profile endpoint. `401`
clears the token and returns to Login. Network or server failure preserves the
token and offers retry or local sign-out. Logout clears local authenticated
state even if remote token revocation fails.

## Transport and configuration

Production builds require a valid HTTPS `API_BASE_URL` and reject HTTP. There
is no production fallback to localhost, LAN, Tailscale, or XAMPP. Android
cleartext permission is confined to the debug manifest for explicit local
testing.

## Authorization and owner isolation

The bearer token selects the Owner. The backend enforces access to profiles,
establishments, grease traps, devices, telemetry, alerts, surrender records,
protected photos, and incentives. Identifiers in request paths are always
checked against that authenticated scope. Flutter treats `403` and `404` as
safe authorization/not-found outcomes and exposes no admin controls.

Two-account manual IDOR verification is still required on the final public
deployment before production release.

## Backend authority

The app does not calculate official thresholds, severity, online/offline
state, surrender decisions, incentive eligibility, reward quantities, reward
units, or distribution state. It does not connect to MySQL and embeds no
database or ESP32 credentials.

## Upload behavior

Oil Surrender accepts JPEG, PNG, or WEBP evidence and applies a 5 MiB client
limit as user feedback. Native upload uses the selected file path; web upload
uses bounded bytes. The picker requests a maximum 2048 by 2048 result where
supported. The app authenticates multipart requests, preserves one idempotency
UUID for retries, and retrieves evidence through an authenticated endpoint.

The PHP backend remains responsible for authorization, MIME inspection, size
limits, executable rejection, randomized storage names, and protected storage.

## Error privacy

The shared API layer categorizes configuration, network, timeout, validation,
authentication, authorization, not-found, conflict, rate-limit, and server
errors. Release UI filters raw HTML, PHP warnings, SQL errors, stack traces,
filesystem paths, `SocketException`, and `FormatException`. Unknown or unsafe
responses become generic user-facing messages.

## Logging rules

Debug diagnostics may include endpoint path, status code, timing, and error
category. They must never include passwords, bearer tokens, database secrets,
device keys, full sensitive payloads, or uploaded bytes. Release builds do not
enable verbose API diagnostics.

## Local secrets and source control

`android/key.properties`, keystores, local API configuration, private Wi-Fi
material, uploads, logs, and generated build output are ignored. Use the
committed examples as templates and keep real values outside Git. Never copy
production secrets into Dart, Gradle source, documentation, screenshots, or
completion reports.
