# Milestone 11 — Analytics and Business Intelligence

## Delivered

- Replaced the template dashboard’s mocked charts with the permission-aware OTT analytics page at `/dashboard/analytics`.
- Added one shared analytics service for audience, subscription, content, revenue, and playback metrics. Dashboard responses only include groups the signed-in staff member may view and are cached for 60 seconds. Customer account metrics exclude staff accounts.
- Added date filters capped at 366 days. Every metric uses the same inclusive `from` and `to` dates.
- Added audience counts, distinct active accounts from playback/catalog activity, new paid subscribers, country-code trends with a five-account minimum, and aggregated popular search terms. Search groups under five, email-like terms, digit-containing terms, and long terms are omitted.
- Added subscription acquisitions, non-trial churn, active subscriptions, trial starts and trial-to-paid conversion, plus MRR/ARR grouped by currency. Subscription amounts are normalized using plan duration; unknown currencies remain a separate group.
- Added watch hours, an 80% completion measure, a clearly labeled completion-based retention proxy, and popular catalog content.
- Added paid revenue by day/currency, playback startup failure counts, rebuffer events, average reported startup time, selected quality distribution, and devices/accounts by platform.
- Extended the session-bound playback diagnostics endpoint to accept allowlisted `startup_failed`, `started`, `rebuffered`, and `quality_changed` event types, with bounded duration and quality values. It still rejects arbitrary player messages and is tied to the caller’s active playback session.
- Added CSV exports protected by both `analytics.export` and the relevant group permission. Exports contain aggregates only, escape spreadsheet formulas, and record an audit event.
- Added scheduled report runs. The scheduler processes up to 100 due reports per minute, rechecks the requester’s export and group permissions, stores aggregate JSON, and makes each result available only to its creator while that group permission remains active.

## Metric definitions and limits

- Active accounts are distinct registered accounts with a catalog view or playback heartbeat in the selected period; logins alone are not counted.
- Watch hours sum `continue_watch.watched_time`, supporting seconds and `HH:MM:SS` values. Completion rate counts rows reaching at least 80% of `total_watched_time`.
- Audience retention is currently a completion-based proxy, not event-level cohort retention.
- MRR and ARR use active subscription amount snapshots normalized to a monthly equivalent by plan duration. Currency is taken from the latest paid subscription transaction; subscriptions without one are reported as `UNKNOWN`.
- PPV transactions do not currently record a currency field, so revenue from those rows is grouped under `UNKNOWN`.
- Detailed startup time, rebuffering, and quality distributions depend on customer playback clients reporting the standardized diagnostics events. Unsupported/absent telemetry is shown as unavailable or zero rather than fabricated.

## Validation

- Migrations `2026_11_24_000017` through `2026_11_24_000019` applied locally. They add bounded playback quality fields, scheduled report-run storage, and date/filter indexes; data-bearing analytics tables are preserved on rollback.
- Focused feature tests cover range filters, metric aggregation, anonymized search/geography, currency-aware MRR, completion and playback metrics, CSV authorization, scheduled report generation, permission revocation, and report ownership.
- The admin UI production build includes the live `/dashboard/analytics` dashboard.
