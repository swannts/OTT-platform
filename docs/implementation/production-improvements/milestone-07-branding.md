# Milestone 07 Expose safe public identity from Laravel Settings

## Existing implementation and gaps

General Settings already stores brand text and assets. Some Next.js identity still comes from NOOR source defaults.

## Changes

Added `GET /api/v3/branding` with an explicit allowlist for platform name, business name, description, support email and phone, and copyright. The response strips markup, validates email, and falls back to NOOR for missing names.

## Files, database, API, and compatibility

`apps/api/app/Http/Controllers/Backend/API/SettingController.php`, `apps/api/routes/api.php`, `apps/api/tests/Feature/PublicBrandingTest.php`. The existing app-configuration and SEO contracts are unchanged. No settings store or database columns were added.

## Verification and measurements

PHP syntax passed. Allowlist and default regression tests require Laravel and the test DB.

## Remaining work

Wire the safe contract into the website, admin, and templates. Verify the current configured identity before rollout. Extend to approved logo assets, typography, color, links, metadata, and targeted invalidation when their consumers are ready.

## Rollback

Revert the additive endpoint and rebuild the API image if needed.

## Commit references

`2978fdf`
