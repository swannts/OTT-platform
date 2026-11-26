# Flutter application architecture

The Flutter client lives in `apps/mobile`. Its current `lib/screens/` tree is
already organized around user-facing capabilities such as authentication,
catalog, playback, profiles, subscriptions, live TV, downloads, and watchlist.
Shared networking, routing, models, state controllers, player components,
Firebase integrations, localization, and reusable UI live in sibling folders
under `lib/`.

This is a legacy screen-oriented feature structure rather than the proposed
`lib/features/` layout. A complete move would touch 337 Dart files, and 276
currently use package imports. The installed Dart 3.11.3 cannot resolve the
locked `flutter_custom_tabs 2.6.0` dependency, which requires Dart 3.12 or newer;
therefore analysis and tests cannot validate bulk path changes in this
environment. Keep this structure stable until a compatible SDK is available,
then migrate one capability at a time with import updates and tests.

The package name `swann_ott` and Android/iOS identifiers `com.swann.ott` are
application and store identities, not engineering directory names. Preserve
them to protect existing installations, Firebase configuration, deep links,
signing, and push notification integration. Do not rename these identifiers
as part of monorepo housekeeping.
