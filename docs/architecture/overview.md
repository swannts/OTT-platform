# Architecture overview

OTT Platform is an existing multi-client streaming product organized as a
responsibility-based monorepo. `apps/api` is the Laravel modular monolith and
system of record; `apps/web`, `apps/admin`, `apps/mobile`, and `apps/worker`
are separate application runtimes. `compose.yaml` provides local development
infrastructure and `compose.production.yaml` describes production topology.

Laravel owns authentication and authorization, accounts and profiles,
catalogue, subscriptions and payments, playback entitlement and session
authorization, notifications, settings, and persistence. The customer web
and mobile clients call Laravel APIs. The admin web uses its server-side BFF
to protect the backend credential boundary. The Go worker consumes the
existing RabbitMQ contracts for asynchronous jobs and media processing.

The system remains a modular monolith plus independently deployed clients and
worker. Do not create new microservices or shared runtime packages without a
concrete ownership and compatibility need. Existing routes, response
formats, persisted data, roles, permissions, payment callbacks, queue names,
and installed client identifiers remain compatibility contracts.

The current public streaming brand is NOOR Islamic Cinema. Engineering
configuration uses OTT Platform defaults where safe; the existing Laravel
Settings module remains the configured branding source. See the per-app and
compatibility documents for implementation details and known gaps.
