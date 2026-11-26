# OTT database relationship inventory for staging data

This is the initial source-based inventory for the staging generator. The API contains 171 migration files across the Laravel application and modules, 69 seeder files, 40 application model files under `app/Models`, and two model factories. Module models add to that model count. This inventory follows the actual legacy names and key types; it does not assume conventional pluralized table names. Many relationships are application-level only, so generation must validate them explicitly.

| Tables | Keys and relationships | Constraints and generation notes |
| --- | --- | --- |
| `users` | Big integer primary key; staff/customer/profile/device/subscription actors | Unique email; nullable names/type; required password; status, ban, subscription flags; soft deletes. Existing email/password/role must not be overwritten by fixtures. |
| `roles`, `permissions`, `model_has_roles`, `role_has_permissions` | Spatie polymorphic user-role mapping; `RbacRegistry` is canonical | Role and permission names are unique by guard. The foundation seeder reconciles role permissions but does not assign user roles. |
| `genres` | Integer primary key; logical creator/updater user IDs | Name required; slug nullable; status; soft deletes; migration does not declare creator foreign keys. |
| `entertainments` | Integer key; `plan_id` logical link; genre/cast mappings in module pivots | Name/type/status and URLs nullable; soft deletes. Publication state and related pivots must be checked in application logic. |
| `seasons` | `entertainment_id` → series entertainment key; optional `plan_id` | Integer key, nullable logical parent, nullable season index, status and soft deletes; no FK in base migration. |
| `episodes` | `season_id` → season; `entertainment_id` → the same parent series; optional `plan_id` | Both parent IDs nullable in schema; generator must require valid matching series/season for generated episodes. No base foreign keys. |
| `plan`, `planlimitation`, `planlimitation_mapping` | Legacy singular plan table and mapping table | Prices and entitlement limits are stored using legacy fields; inspect actual migration types before adding any data. Avoid invented service events. |
| `subscriptions` | Logical `user_id`, `plan_id`; optional `payment_id` to legacy transaction | Nullable integer identifiers, date range, status, amounts and snapshot fields; no base foreign keys. Enforce customer/plan existence, chronology, and at most one active entitlement according to services. |
| `subscriptions_transactions` | Logical subscription/user links | Nullable integer foreign identifiers and status strings; no FK. Amount/currency/payment semantics must match the associated lifecycle. |
| `user_multi_profiles` | `user_id` → `users.id` with DB cascade | Child-profile flag added later; ownership is enforced by the FK. Profiles must respect entitlement limits and child restrictions. |
| `devices` | Logical `user_id`; device token links are separate | Legacy nullable owner/device identifiers. Do not generate usable credentials or access tokens. |
| `watchlists`, `reviews`, `user_watch_histories`, `continue_watch`, `user_search_histories` | Logical customer/profile/content keys; legacy content IDs and type fields | Several tables lack FKs and use integer content/user IDs. Avoid duplicate watchlist mappings and activity before registration/content release. |
| `playback_sessions` | UUID `session_id`; indexed user, device-token and profile IDs; logical content type/ID | Unique session UUID and lifecycle timestamps; no FKs declared. Historical sessions must be ended/expired and contain no usable token. |
| `verified_payments` | Unique gateway/provider reference; logical user/purchase/fulfillment IDs | Amount stored in minor units and currency code; no provider requests are allowed during generation. Use a synthetic gateway only. |
| `marketing_consents` | `user_id` FK; unique user/channel | Campaign has required creator and optional approver FKs to users; delivery has campaign and user FKs and unique campaign/user/channel. Synthetic deliveries must never be sent. |
| `support_tickets` and notes | Ticket UUID unique; customer/creator/assignee/note author are logical indexed IDs | These support tables do not declare FKs in the inspected migration. Ticket response/resolution timestamps must be ordered. |
| `admin_audit_logs`, `operations_incidents`, analytics runs | Actor/entity links primarily logical; report/incident states are indexed | Distinguish historical synthetic events from live operational state. Never seed current infrastructure health or real actor identities. |
| `staging_dataset_manifests`, `staging_dataset_records` | Unique dataset ID and dataset/entity/key ownership tuples | Additive staging-only registry. Ownership is currently registered for generated staff, customer, profile, and device records, so safe reset is not yet supported for the other business tables. |

## Schema caveats

The schema evolved over many migrations and module directories. Some columns are nullable even when the business process requires a value. Some identifier types differ between legacy tables and newer tables. For example, `users.id` is a big integer, while legacy subscription `user_id` is declared integer; newer `playback_sessions.user_id` is unsigned big integer. Additive schema changes require measured compatibility review. Do not disable FK checks or infer that a logical relationship is database-enforced.

This inventory is not a live-database inspection: the current MySQL test connection rejects the configured root login. It documents migration definitions only. No row counts, production database contents, or current relational integrity are asserted.
