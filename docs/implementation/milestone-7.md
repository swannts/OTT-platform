# Milestone 7: Subscription management

## Implemented

- Added permission-checked plan creation, editing, archival, subscriber listing, coupon management, and entitlement simulation APIs under `/api/admin/subscriptions`.
- Removed unauthenticated plan and plan-limitation mutation routes. The public `plan-list` catalog remains available for customer clients.
- Added a subscription management page with plan editing, trial configuration, plan comparison, subscriber overview, coupon management, and entitlement simulation.
- Added first-subscription-only trials. Trial activation locks the account, rechecks its full subscription history, and does not create a paid transaction.
- Added plan-level device, profile, simultaneous-stream, and maximum-quality limitations. Existing subscription snapshots remain unchanged when a plan is edited or archived; playback enforces the saved limits.
- Restricted trial configuration to `subscriptions.trials.manage`; subscriber directory access uses `subscriptions.subscribers.view`.
- Added plan performance reporting based on active subscriptions and records marked paid in `subscriptions_transactions`. New subscription payments record their currency; totals are grouped by currency, and legacy amounts without currency are labeled `UNRECORDED`.
- Hardened the existing coupon API so write operations require `subscriptions.coupons.manage`; coupon plan applicability and expiry are checked during pricing.
- Added coupon-wide and per-account redemption caps. Checkout reserves a use under a coupon row lock, places the reservation token in provider metadata, and records a redemption only when the server verifies payment. Reservations are released for failed gateway creation, Stripe/Razorpay failure or cancellation events, and expired reservations are swept hourly.
- Defined the plan-change policy: a plan change replaces the active plan immediately after the new payment is verified. An unverified checkout never changes access, and the admin UI explains this behavior.

## Verification

- Laravel subscription, trial, and playback suites: 23 tests / 85 assertions passed in the running backend container. PHPUnit reports one existing deprecation.
- Admin UI production build passed with `/dashboard/subscriptions`. Next.js reports existing dynamic-export warnings on unrelated routes and Node's `url.parse()` deprecation warning.
- Additive migrations through `2026_11_24_000011` were applied to the local Docker database.
- PHP syntax checks and `git diff --check` passed. Git reported only expected CRLF-to-LF normalization warnings for legacy files.

The current provider checkout path supports reservation metadata for Stripe Checkout, Stripe PaymentIntents, Razorpay, and Paystack. PayPal checkout is not implemented in this application; coupon reservations for that unavailable path are released immediately.

## External integration dependency

Regional pricing remains dependent on an external, trusted country signal and an approved country/currency price matrix. The application currently has one global primary currency and does not trust customer-supplied region values. Until deployment provides a trusted geo source and the business supplies regional prices, checkout uses the configured global currency.
