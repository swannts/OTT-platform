# Milestone 02 Document and stabilize API contracts

## Existing implementation and gaps

Laravel registers core and module routes under the API middleware group. Scramble generates OpenAPI 3.1 and the restricted Swagger UI reads `/docs/api.json`.

## Changes

Added a server-generated UUID to `X-Request-Id`, Laravel logging context, and request attributes. Updated the API CORS exposure and added header, request isolation, and process-health tests.

## Files, database, API, and compatibility

`apps/api/app/Http/Kernel.php`, `apps/api/app/Http/Middleware/AssignApiRequestId.php`, `apps/api/config/cors.php`, `apps/api/tests/Feature/ApiRequestIdTest.php`. API bodies stay compatible. The branding endpoint uses the same request ID in its new envelope.

## Verification and measurements

PHP syntax passed. Contract tests and Scramble export have yet to run in Laravel.

## Remaining work

Export runtime routes and OpenAPI from the same revision. Classify public routes, authenticated client routes, admin routes with explicit permissions, and provider callbacks using signatures. Keep provider callbacks stable. Use additive changes for existing API versions and publish a migration window before retiring a contract. Verify success and error headers against middleware and proxy behavior.

## Rollback

Revert the correlation commit for existing endpoints. If reverting correlation while keeping branding, update its request ID field or roll back branding in the same deployment.

## Commit references

`dabdb61`, `2978fdf`
