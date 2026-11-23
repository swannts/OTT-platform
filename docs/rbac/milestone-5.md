# Milestone 5: Security hardening and verification

## Changes

- Browser API calls now use a same-origin Next.js Route Handler. Laravel Sanctum credentials are set as an `HttpOnly`, `SameSite=Lax` cookie, attached server-side to fixed-backend requests, removed from login/register JSON, and cleared on logout or unauthorized responses. The proxy rejects cross-origin/cross-site requests, invalid path segments, and caches no authenticated response.
- Laravel profile, watchlist, continue-watching, search-history, device logout, and user-detail APIs derive the account from the authenticated principal. Profile-scoped history/playback uses the active device profile; request-supplied `user_id` and alternate `profile_id` values cannot select another account or profile.
- Public device logout routes were removed. User detail and logout require Sanctum authentication. Single-device logout scopes the device lookup to the signed-in owner, and logout revokes only the current token/device.
- Parental PINs are stored in a new `users.pin_hash` column. Existing integer PINs are upgraded after successful verification; PIN and OTP values are hidden from model serialization and removed from account/login/profile resources. OTP verification consumes the OTP. Playback denials are logged with account/content/profile identifiers and a policy reason, never the stream URL.
- The arbitrary encrypted-URL decryption endpoint was removed in milestone 4. Playback grants are rate-limited and re-evaluate entitlement and active profile on each request.

## Migration and deployment

Migration `2026_11_23_000001_add_pin_hash_to_users_table.php` is additive and was applied locally. It does not rewrite existing customer rows; legacy PINs move to the hash column when verified or changed. Deploy and migrate Laravel before deploying the Next.js web app, then verify login, profile selection, watchlist/history, free playback, and entitled playback through the web origin.

The migration refuses to drop `pin_hash` once hashes exist because doing so would discard customer PINs. After users begin migrating PINs, do not roll back to a version that only reads the old integer PIN column. Restore a compatible application version or provide a forward-compatible PIN reset path. No role, permission, subscription, purchase, or playback records are deleted by this milestone.

## Verification

- Laravel focused tests: `PlaybackEntitlementTest`, `CustomerAccountIsolationTest`, and `AdminContentWorkflowTest`.
- PHP syntax checks for changed controllers, services, routes, resources, migration, and tests.
- Next.js focused ESLint and TypeScript checks, plus production build. The installed build may stop after compilation with Next.js `SecurityFatalError`; see the final report and follow the recommended security upgrade before deployment.
- Full repository ESLint currently reports existing errors in unrelated screens and Cypress support; focused lint for the changed web files is run separately.

## Reviewed limitation

The API authorizes the viewer before returning source URLs, but the generic media URL fields do not establish private storage, expiring provider signatures, or DRM. A viewer who is entitled may still copy a reusable origin URL. Provider-level signed playback and DRM need provider-specific configuration and remain a production hardening item. Flutter was explicitly excluded from these milestones; Laravel API changes may require a later Flutter playback migration.

## Rollback

Deploy the matching Next.js and Laravel revisions together. For rollback, restore the compatible application code while retaining the additive `pin_hash` column after hashes exist. Do not run the migration rollback once it contains hashes. The migration is safe to reverse only before any customer PIN hash has been written.
