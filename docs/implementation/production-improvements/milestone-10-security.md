# Milestone 10 Harden maintenance and search authorization

## Existing implementation and gaps

Database optimization was reachable without an administrative permission. Catalog search trusted a caller account attribute for personalization.

## Changes

Maintenance requires Sanctum, admin access, the existing `settings.security.manage` permission, and a personal access token. It is rate-limited. Search uses verified Sanctum identity and does not share person-specific responses.

## Files, database, API, and compatibility

`apps/api/routes/api.php`, `apps/api/Modules/Frontend/Http/Controllers/QueryOptimizeController.php`, `apps/api/Modules/Entertainment/Http/Controllers/API/EntertainmentsController.php`, `apps/api/tests/Feature/DatabaseMaintenanceAuthorizationTest.php`. Existing URLs, roles, canonical permission identifiers, public search, and media/payment contracts remain in place. Any legitimate maintenance caller now needs an authorized admin token.

## Verification and measurements

PHP syntax passed. Runtime authorization tests and full security audit remain outstanding. No maintenance operation was executed.

## Remaining work

Run the guest and customer denial tests and an isolated authorized maintenance smoke test only on a disposable dataset. Audit remaining legacy routing, search personalization, dependencies, payment signatures and replay handling, and production secret handling.

## Rollback

Use a forward fix if access needs correction. Preserve denial of public maintenance requests at the gateway or application during rollback.

## Commit references

`a299e58`, `786aaf2`
