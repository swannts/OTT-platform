# Milestone 14 — Flutter compatibility

Status: **implemented; platform verification is partial**.

Flutter now follows the customer-facing Laravel contracts introduced by the
admin, subscription, media, and playback milestones. The mobile app uses the
existing customer API and does not call administrative endpoints or receive
administrative permission data.

## Changes

- Sanctum customer tokens are stored with `flutter_secure_storage` (Android
  encrypted preferences and iOS Keychain with this-device accessibility).
  Startup migrates any legacy token from local user preferences, removes it
  from the persisted profile, and clears login state if a signed-in profile has
  no secure token. Remember-me stores only the email; passwords are removed.
- Authorization headers read from secure storage. Password-based token
  regeneration was removed. Logout revokes the current server token and clears
  local secure storage; password rotation writes the replacement token to the
  secure store.
- Device-limit login responses carry a five-minute, `device-sessions:manage`
  token. Flutter sends it only to customer device-session revocation routes.
  Laravel registers Sanctum's `abilities` middleware alias, scopes each revoke
  to the authenticated account, ends playback for the revoked device, and
  consumes the temporary token after a successful single-device revoke.
  Revoke-all ends sessions, removes devices and tokens, and requires sign-in
  again. Normal current-device logout also ends that device's playback sessions
  before deleting its token.
- Online VOD requires a selected profile and a Laravel playback authorization
  response. Flutter plays only returned authorized qualities, refreshes the
  session every 45 seconds, replaces expiring signed media URLs while
  preserving playback position, and stops with an expired/revoked message on
  heartbeat failure. Closing the player ends its server session. Offline
  downloads retain their local playback path; live TV remains on its existing
  live playback flow because the protected VOD API does not accept live-channel
  content types.
- Content, episode, and coming-soon models retain Laravel's string/UUID
  identifiers alongside existing integer fields used by local flows. Detail,
  playback, reminder, watchlist, PPV, and progress requests send the preserved
  server identifier; legacy numeric-string IDs still parse as integers.
- Added focused playback-contract tests and localized the three base strings
  missing from Arabic, German, Greek, and French, which prevented Flutter test
  compilation.

Profile selection, parental PIN, subscription purchase/restore, and entitlement
display continue to use the existing customer endpoints; these endpoints were
not replaced by browser-cookie assumptions.

## Validation

- Flutter dependency resolution: passed with Flutter 3.50.0 / Dart 3.14.0.
- `flutter test --no-pub`: passed (5 cases: playback authorization parsing,
  malformed-response rejection, stale-token serialization, and UUID/numeric
  content and coming-soon ID compatibility).
- Focused analyzer over modified Dart files: no errors; existing lint/info
  findings remain in the touched files.
- Focused analysis of the changed Dart files reported no errors; existing
  info-level lint findings remain. Whole-app `flutter analyze --no-pub` reports
  383 existing issues (one warning in `TrailerWidget`, 382 info-level findings)
  and exits nonzero; it reports no Dart compile errors.
- Laravel `CustomerDeviceSessionRevocationTest`: passed (4 tests, 14
  assertions) inside the running backend container. It verifies the scoped
  ability, account isolation, playback termination, device/token revocation,
  one-use challenge token, and playback cleanup on current-device logout.
- Android `assembleDebug`: passed using JDK 17 and a temporary synthetic
  Firebase config in the Android debug source set. This verifies Dart, Kotlin,
  Java, resources, and APK packaging; the generated APK was removed because
  its Firebase values are intentionally non-production. The normal Flutter
  build remains blocked by the checked-in Firebase config, which has clients
  for `com.iqonic.streamitlaravel` and its TV app but not the current
  `com.swann.ott` application ID. No provider config was changed.
- Laravel `CustomerDeviceSessionRevocationTest` was rerun: 4 tests and 14
  assertions passed, with one PHPUnit deprecation.
- iOS build/device verification remains unavailable on this Linux host.

## Remaining platform verification

The authenticated mobile flows have not been smoke-tested against a physical
Android/iOS device and live backend. A valid Firebase Android client for
`com.swann.ott` must be registered and supplied before producing a usable
Firebase-enabled APK. The iOS build cannot be run on this Linux host. These
runtime/provider checks remain part of final production verification in
Milestone 15.
