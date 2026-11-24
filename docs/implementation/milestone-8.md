# Milestone 8 — Finance and Payment Operations

## Delivered

- Added an admin finance dashboard with searchable and filterable payment records, payment details, invoice/receipt downloads, refund requests and approvals, provider disputes, failed webhook events, reconciliation, and revenue reporting.
- Added `finance.*` authorization at route and controller boundaries. Finance managers receive the required finance permissions. The Super Admin default grant deliberately excludes `finance.refunds.approve`.
- Added refund request records separate from verified payment history. Requests reserve refundable balance, use an idempotency key, and require a different staff member to approve. Approved refunds execute against Stripe, Razorpay, or Paystack using provider request identifiers for safe retries. Partial amounts are supported.
- Added signed webhook handling and an idempotent provider-event ledger for Stripe, Razorpay, and Paystack. Repeated processed events do not repeat fulfillment. Failed events retain status and attempt counts for finance investigation.
- Added payment reconciliation against provider status, amount, currency, and available refund totals. Each run records the comparison, operator, outcome, and timestamp, and appears in the finance dashboard history.
- Added daily verified-payment and refund ledger reporting and finance-authorized CSV exports.
- Existing invoice and receipt templates are rendered on demand behind `finance.invoices.view`; no public invoice files or card details are stored.

## Validation

- Docker Laravel migrations through `2026_11_24_000014` applied successfully.
- Focused finance, subscription, and playback PHPUnit suites: 27 tests and 100 assertions passed. PHPUnit reported one existing deprecation.
- Admin UI `npm run build` passed on Next.js 15.5.29. The existing build reports dynamic-export warnings in unrelated pages and a Node `url.parse()` deprecation.
- `admin-ui` has no ESLint configuration, so its declared lint command cannot run until that project configuration is supplied.

## Operational limits

- Provider credentials and webhook secrets must be configured in the Laravel deployment before live payment or refund operations can run. Live provider calls were not exercised in this local validation.
- The revenue view is an internal verified-payment/refund ledger grouped by fulfillment date, gateway, and currency. It is not a provider payout/settlement statement and does not include fees or bank deposits.
- Stripe and Razorpay reconciliation compare provider refund totals when available. Paystack transaction verification does not currently return a refund total, so its refund comparison is explicitly unavailable.
- Webhook event records store a payload hash and failure metadata, not raw provider payloads. Unresolved provider events may require investigation in the provider dashboard.
