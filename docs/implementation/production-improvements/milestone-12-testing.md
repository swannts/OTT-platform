# Milestone 12 Verify isolated regressions and prepare full integration

## Existing implementation and gaps

Existing Laravel feature suites, Go tests, and Cypress/Playwright customer suites were identified.

## Changes

Added playback, correlation, localization, branding, maintenance, media-concurrency, homepage-cache, and backup-publish regressions.

## Files, database, API, and compatibility

Changes live in the owning apps and scripts. Fixtures are confined to tests. No production data or external provider account was used.

## Verification and measurements

Homepage recovery exercised a production Next.js server against a local controlled API. Backup concurrency exercised the real shell publisher with Docker and age fixture commands. PHP files passed PHP WASM syntax checks. Go files were formatted. Laravel, provider, full browser and broker suites are pending.

## Remaining work

Build an isolated end-to-end environment for ingest, processing and QC, approvals, sandbox checkout, playback and watch history. Cover expiry, duplicate webhooks, refunds, device revocation, regional controls, replacement, and retry. Record hardware, dataset, workload and latency before making sizing claims.

## Rollback

Test-only additions can be reverted separately from runtime behavior where useful.

## Commit references

`a0a9704`, `dabdb61`, `3c04099`, `dffb43a`, `59f4652`, `a299e58`, `2978fdf`, `bd680b2`

All five existing customer Playwright guest journeys passed against a production Next.js server backed by a local API fixture. The marketing fixture also exercised record selection, cancel and save against a stubbed API.
