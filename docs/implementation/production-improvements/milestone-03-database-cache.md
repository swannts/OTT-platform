# Milestone 03 Improve MySQL and Redis correctness and efficiency

## Existing implementation and gaps

Search uses versioned cached responses. The invalidation service combines version changes with Redis scans. No representative MySQL or Redis dataset was available.

## Changes

Catalog search now shares cache entries only for anonymous search. Its public cache key includes the resolved locale and excludes the legacy caller account field. Authenticated and child-filtered results use the request's own query result.

## Files, database, API, and compatibility

`apps/api/Modules/Entertainment/Http/Controllers/API/EntertainmentsController.php`. Existing Redis invalidation pattern still covers the new public search keys. No index, migration, or persistence changes.

## Verification and measurements

PHP syntax passed. No MySQL execution plans, Redis concurrency checks, timing measurements, or index claims.

## Remaining work

Trace the remaining personalized cache paths. Measure query counts and P95 using a representative isolated MySQL dataset, capture EXPLAIN ANALYZE, and check Redis namespace behavior during concurrent invalidation.

## Rollback

Revert the search change only after preserving its authorization controls through a forward fix. Anonymous cache keys expire under the existing five-minute TTL.

## Commit references

`786aaf2`
