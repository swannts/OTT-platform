# Production improvement plan

Baseline: `e2b4f71ad7f3abca818c2972b67856129a4084c1` on `main`, confirmed by GitHub on 2026-11-26. Work branch: `refactor/production-grade-improvements`.

## Repository and verification baseline

The monorepo already has a Laravel payment fulfillment service, provider event ledger, playback authorization action, protected HLS routes, admin RBAC and functional modules, bilingual fields and blog tests, a Go worker with lease-based transcoding, and two backup paths. Reuse the [architecture references](../architecture/overview.md), [admin plan](admin-ui-functional-plan.md), [migration report](../architecture/migration-report.md), and [previous operational verification](milestone-15-production-verification.md). Historical route and test counts are separate from this cycle.

The workspace started without a checkout. GitHub HTTPS checkout timed out and the alternate Git endpoint required credentials. GitHub API trees and files allow commit-pinned inspection and branch-only commits. Bash, Node 24, npm and FFmpeg/FFprobe are present. PHP, Composer, Docker, MySQL, Redis, k6 and age are unavailable in the workspace. The executable named `go` rejects the Go toolchain's version/help arguments, and no gofmt was found. No staging or provider sandbox credentials were supplied. Later, locked web and admin dependencies were installed, and isolated PHP WASM, Go formatting, and Chromium tools became available. See the progress report for exactly which checks ran.

## Work sequence and milestone gates

| Milestone | Starting point and next check | Acceptance evidence |
| --- | --- | --- |
| 01 Backend | Extract the three playback lifecycle endpoints from the 4,000-line entertainment controller. Review checkout/webhook boundaries around the existing fulfillment service. | Route and client contract comparison, Laravel regression suite |
| 02 API | Inspect the existing restricted Swagger UI and API route groups. Add correlation that preserves response bodies. | Versioned contract, API route inventory, response and log checks |
| 03 Database/cache | Review versioned API cache keys and localized list projections. Measure representative queries on isolated MySQL. | Before/after EXPLAIN and latency, Redis invalidation and profile/locale isolation |
| 04 Admin | Reconcile the existing admin checklist with screen actions, permissions and tests. | Action inventory and real browser-to-API workflows including maker-checker |
| 05 Web | Homepage cache admits partial upstream failures for up to one hour. Verify Next.js caching and preserve rendering during partial failure. | Recovery and cache tests, representative web performance and accessibility measurements |
| 06 Localization | Blog localization has an existing feature test; inspect catalog search, filters and fallback next. | English/Bengali browsing and translation-edit tests |
| 07 Branding | Reuse Laravel Settings and inventory public fields and consumers. | Permissioned updates, NOOR defaults and public payload allowlist tests |
| 08 Worker | Media and queue concurrency is hardcoded. Inspect readiness, leases, ACK behavior and recovery. | Go vet/race, duplicate job and restart scenarios with RabbitMQ and FFmpeg |
| 09 Media delivery | Existing signed application delivery and leases are a starting point. Review private-origin offload and revocation windows. | Private origin/CDN and entitlement checks, documented token and revocation limits |
| 10 Security | Build on existing RBAC, webhook signature tests and admin BFF controls. Review trust boundaries and sensitive logs. | Defensive fixes, regression tests, unresolved external checks |
| 11 Observability | Existing operations dashboard and health checks cover some service metrics. Establish request correlation and verify alert delivery. | Correlated requests and observable queue/media failures with recorded overhead |
| 12 Testing | Existing feature and browser suites provide starting points. | Integrated OTT lifecycle and isolated workload benchmarks |
| 13 Recovery/staging | The backup script's existence check and archive move are separate operations. Review atomic publication, restore rehearsal and Compose validation. | Collision regression, Compose validation, isolated restore with RPO/RTO |

Changes preserve routes, persisted identifiers, player authorization, payment contracts, queue topology, and current branding. Use additive migrations when required. Validate provider-specific behavior in authorized sandbox or staging environments.
