# Laravel backend architecture

`apps/api` is a Laravel modular monolith. Existing business modules live under
`Modules/`; cross-cutting application support, authentication, authorization,
operations, and newer feature slices also exist under `app/`. The current
layout is intentionally documented rather than mechanically flattened into
an idealized structure.

Routes are registered by Laravel and module route providers. Existing
versioned and legacy API endpoints remain supported for Next.js, Flutter/TV,
payment providers, and other integrations. `RbacRegistry` is the canonical
administrative role and permission catalog. Sensitive behavior such as
playback authorization, refund approval, account access, and admin writes is
enforced by Laravel regardless of what a client renders.

For new or changed code, use the existing validation, authorization,
transaction, resource, queue, and audit patterns of the owning domain. Keep
controllers focused and avoid repositories or interfaces where a simple
existing Eloquent operation is sufficient. Trace model morph values and
serialized jobs before renaming classes. Historical migrations are immutable;
schema evolution must be additive and backward compatible.

Current module inventory and safe boundaries are recorded in
[`domain-boundaries.md`](domain-boundaries.md), and compatibility requirements
are in [`api-database-rbac-compatibility.md`](api-database-rbac-compatibility.md).
