# Existing authorization system analysis

## Scope and method

This is the Milestone 1 source/runtime inventory for the OTT repository. Graphify was used to navigate the large repository graph; each material finding below was checked against the current source or the running local Laravel database. The Flutter client was included in the inventory, but customer-access implementation is out of this milestone.

## Architecture

- `swann-laravel-web` is the Laravel 12 backend and still contains a Blade-based administrative CMS. It exposes customer APIs and uses Laravel Sanctum and Spatie Laravel Permission 6.
- `nextjs-web` is the customer-facing Next.js application. It reads account/subscription/profile data from the Laravel API; UI subscription flags are convenience state, not an authorization boundary.
- `admin-ui` is the separate Next.js administrative interface connected to `/api/admin`. This milestone extends its backend access contract only; role-management screens and APIs are Milestone 2.
- `flutter-app` is the Flutter customer application. It uses the Laravel customer APIs and profile/subscription concepts. It is outside this milestone's code changes.
- `go-worker` and Docker Compose provide worker and local service runtime. No tenant-oriented service boundary was found in the inspected deployment or backend schema.

## Current authorization and data model

Laravel uses the standard Spatie tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, and `role_has_permissions`. Roles and permissions are global (Spatie teams are disabled). The app's `User` model uses `HasRoles`; app `Role` and `Permission` extend Spatie models, while the package config points at Spatie's base model classes. `roles` and `permissions` also carry the legacy `title`/`is_fixed` fields.

The live local database currently has roles `admin`, `channel_manager`, `demo_admin`, `user`, and `video_editor`; no `super-admin` role exists. These role names also appear in staff seeders and middleware. The old `PermissionRoleTableSeeder` truncates `permissions` and `role_has_permissions`, then re-creates legacy permissions and gives all of those legacy permissions to `admin` and `demo_admin`. It must not be reused for the new catalog. The general database seeder invokes broader seeders and performs unrelated storage cleanup, so it is not the safe entry point for this migration.

`AuthServiceProvider` defines a hardcoded short-drama role gate and a global `Gate::before` grant for `admin`, which makes that role pass every Laravel ability check. `CheckAdmin` only rejects `user`, so it admits any other role. `RoleBasedRouteAccess` and several route/controller/view call sites encode role names. The legacy role/permission CRUD controllers exist but are not currently policy-driven.

The `/api/admin` endpoints previously checked `admin|super-admin|demo_admin` by role name. Milestone 1 changes those endpoints to explicit `admin.access` permission middleware and checks the same permission at login. The dashboard additionally requires `admin.dashboard.view`. Existing CMS routes retain their current behavior during this foundation step.

## Customer access and ownership

The customer model has an account-level `is_subscribe` field and subscription records; payment code computes active-subscription responses. The web client uses response flags such as `has_active_subscription` to render UI. Multiple viewer profiles exist in `user_multi_profiles`, including `is_child_profile`; customer API/dashboard code has profile-aware filtering and child-profile branches. This source inventory does not establish complete server-side authorization for every playback endpoint, so no such guarantee is claimed here.

The account access categories should therefore be represented as guest (unauthenticated), registered customer (authenticated account), subscriber (verified entitlement), and child profile (an owned profile with server-enforced restrictions), rather than four peer Spatie roles. Entitlement and parental-control implementation is Milestone 4.

## Existing protections, gaps, and risks

- Sanctum authenticates supported API flows and Spatie provides the reusable permission storage/checks.
- The current admin login role allowlist cannot represent the requested staff roles and does not express least privilege.
- The global `admin` Gate bypass and role-name middleware remain broad legacy paths. Removing them now could silently break the CMS and has not been validated across all its routes.
- Legacy permission seeding is destructive. The new `RbacFoundationSeeder` does not truncate tables, does not create user-role assignments, and only reconciles permissions attached to its twelve canonical roles. Existing role assignments and unrelated permissions remain.
- Canonical role mappings are explicitly least-privilege. The compatibility bridge adds only admin entry/dashboard permissions to already-existing legacy staff roles; it does not infer or assign any canonical role to users.
- Existing role schema has no activation state, descriptions, access scopes, MFA enrollment requirement, approval workflow, or audit-history relation. No supporting migration is needed for this milestone because Spatie's existing schema supports the permission registry and mappings.
- Repository and migration scans did not identify a tenant/organization/content-owner/region authorization model. Geographic rights fields may exist as content metadata, but region-scoped authorization was not verified.
- Existing automated tests use PHPUnit and SQLite in-memory configuration. The new focused backend tests follow that convention.

## Integration and migration approach

1. Keep the existing Spatie tables and legacy roles. Run only `php artisan db:seed --class='Database\\Seeders\\RbacFoundationSeeder'` to add/reconcile the canonical catalog; do not run the legacy permission-role seeder as part of this rollout.
2. Preserve all user-role assignments. Review the 12 canonical role mappings, then assign staff roles only through an explicit, approved migration/invitation workflow in Milestone 2. No account is automatically made Super Admin.
3. Use permission checks and Laravel policies for new administrative routes. Existing CMS routes and broad legacy bypasses require a separate route-by-route migration with regression coverage.
4. Add audited role assignment, MFA, privileged reauthentication, high-risk approvals, and last-Super-Admin protection before exposing role management.
5. Implement account entitlements and child-profile authorization through backend services and endpoint tests before changing web or Flutter presentation.

## Milestone 1 implementation boundary

This milestone adds the registry, safe seeder, role policy, `/api/admin` permission boundary, and tests. It does not create role-management endpoints/UI, audit storage, MFA, account invitations, customer entitlement services, playback checks, or Flutter/web changes. Those items belong to later milestones and remain required before claiming full RBAC or customer authorization coverage.
