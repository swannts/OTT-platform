<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use RuntimeException;

/**
 * Populates a repeatable local sample workspace without clearing user data.
 * Run explicitly with `php artisan db:seed --class=Database\\Seeders\\LocalDemoDataSeeder`.
 */
class LocalDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('LocalDemoDataSeeder may only run in local or testing environments.');
        }

        $password = (string) config('app.demo_seed_password');
        if ($password === '') {
            throw new RuntimeException('Set DEMO_SEED_PASSWORD before creating local demo accounts.');
        }

        $this->call(RbacFoundationSeeder::class);
        $this->call(LocalAdminBootstrapSeeder::class);

        $users = $this->seedAccounts($password);
        $this->call(LocalDemoCatalogSeeder::class);
        $content = DB::table('entertainments')->where('slug', 'demo-sacred-sanctuary')->first();

        if (! $content) {
            throw new RuntimeException('The local demo catalog did not create its expected sample title.');
        }

        DB::transaction(function () use ($users, $content): void {
            $this->seedPlansAndSubscriptions($users);
            $this->seedFinance($users);
            $campaignId = $this->seedMarketing($users);
            $this->seedSupport($users);
            $this->seedAdminOperations($users, $content);
            $this->seedCustomerNotifications($users, $content, $campaignId);
            $this->seedCustomerExperience($users, $content);
        });
    }

    /** @return array{admin: User, staff: User, customer: User, second_customer: User} */
    private function seedAccounts(string $password): array
    {
        $admin = User::query()->where('email', 'admin@swann.com')->firstOrFail();
        $staff = $this->account('demo.content.manager@example.test', 'Demo', 'Content Manager', $password, 'content_manager');
        $customer = $this->account('demo.subscriber@example.test', 'Amina', 'Rahman', $password, null, true);
        $secondCustomer = $this->account('demo.customer@example.test', 'Yusuf', 'Ahmed', $password, null);

        return [
            'admin' => $admin,
            'staff' => $staff,
            'customer' => $customer,
            'second_customer' => $secondCustomer,
        ];
    }

    private function account(string $email, string $firstName, string $lastName, string $password, ?string $role, bool $subscribed = false): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make($password),
                'user_type' => $role ?? 'user',
                'status' => 1,
                'is_banned' => 0,
                'is_subscribe' => $subscribed ? 1 : 0,
                'email_verified_at' => now(),
            ],
        );

        if ($role !== null) {
            $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
            $user->roles()->syncWithoutDetaching([$roleModel->getKey()]);
        }

        return $user;
    }

    private function seedPlansAndSubscriptions(array $users): void
    {
        $now = now();
        $plans = [
            ['identifier' => 'demo-monthly', 'name' => 'Demo Monthly', 'price' => 599, 'duration' => 'month', 'duration_value' => 1, 'level' => 1],
            ['identifier' => 'demo-family', 'name' => 'Demo Family', 'price' => 1299, 'duration' => 'month', 'duration_value' => 1, 'level' => 2],
            ['identifier' => 'demo-annual', 'name' => 'Demo Annual', 'price' => 5999, 'duration' => 'year', 'duration_value' => 1, 'level' => 3],
        ];

        foreach ($plans as $plan) {
            DB::table('plan')->updateOrInsert(
                ['identifier' => $plan['identifier']],
                $plan + [
                    'status' => 1,
                    'description' => 'Demo plan for local development only. No real payment is collected.',
                    'created_by' => $users['admin']->getKey(),
                    'updated_by' => $users['admin']->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $monthlyPlanId = DB::table('plan')->where('identifier', 'demo-monthly')->value('id');
        $familyPlanId = DB::table('plan')->where('identifier', 'demo-family')->value('id');
        $limitations = [
            ['slug' => 'ads', 'title' => 'Advertisements', 'description' => 'Whether advertisements are shown.'],
            ['slug' => 'device-limit', 'title' => 'Simultaneous streams', 'description' => 'Maximum concurrent playback sessions.'],
            ['slug' => 'profile-limit', 'title' => 'Profiles', 'description' => 'Maximum household profiles.'],
            ['slug' => 'max-video-quality', 'title' => 'Maximum video quality', 'description' => 'Highest permitted playback quality.'],
        ];
        foreach ($limitations as $limitation) {
            DB::table('planlimitation')->updateOrInsert(
                ['slug' => $limitation['slug']],
                $limitation + ['status' => 1, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $limitationValues = [
            (int) $monthlyPlanId => ['ads' => ['value' => 0, 'limit' => null], 'device-limit' => ['value' => 1, 'limit' => '2'], 'profile-limit' => ['value' => 1, 'limit' => '2'], 'max-video-quality' => ['value' => 1, 'limit' => '1080p']],
            (int) $familyPlanId => ['ads' => ['value' => 0, 'limit' => null], 'device-limit' => ['value' => 1, 'limit' => '4'], 'profile-limit' => ['value' => 1, 'limit' => '5'], 'max-video-quality' => ['value' => 1, 'limit' => '4K']],
        ];
        foreach ($limitationValues as $planId => $values) {
            foreach ($values as $slug => $value) {
                $limitationId = DB::table('planlimitation')->where('slug', $slug)->value('id');
                DB::table('planlimitation_mapping')->updateOrInsert(
                    ['plan_id' => $planId, 'planlimitation_id' => $limitationId, 'limitation_slug' => $slug],
                    [
                        'limitation_value' => $value['value'],
                        'limit' => $value['limit'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        $records = [
            [$users['customer'], $monthlyPlanId, 'demo-subscriber-active', 'active', -10, 20],
            [$users['second_customer'], $familyPlanId, 'demo-customer-expired', 'expired', -70, -40],
        ];

        foreach ($records as [$user, $planId, $identifier, $status, $startDays, $endDays]) {
            DB::table('subscriptions')->updateOrInsert(
                ['identifier' => $identifier, 'user_id' => $user->getKey()],
                [
                    'plan_id' => $planId,
                    'start_date' => $now->copy()->addDays($startDays),
                    'end_date' => $now->copy()->addDays($endDays),
                    'status' => $status,
                    'amount' => $identifier === 'demo-subscriber-active' ? 599 : 1299,
                    'total_amount' => $identifier === 'demo-subscriber-active' ? 599 : 1299,
                    'tax_amount' => 0,
                    'name' => $identifier === 'demo-subscriber-active' ? 'Demo Monthly' : 'Demo Family',
                    'identifier' => $identifier,
                    'type' => 'subscription',
                    'duration' => 1,
                    'level' => $identifier === 'demo-subscriber-active' ? 1 : 2,
                    'plan_type' => json_encode(['demo' => true, 'plan_id' => $planId], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function seedFinance(array $users): int
    {
        $now = now();
        $subscriptionId = DB::table('subscriptions')
            ->where('identifier', 'demo-subscriber-active')
            ->where('user_id', $users['customer']->getKey())
            ->value('id');

        DB::table('subscriptions_transactions')->updateOrInsert(
            ['transaction_id' => 'DEMO-SUBSCRIPTION-001'],
            [
                'subscriptions_id' => $subscriptionId,
                'user_id' => $users['customer']->getKey(),
                'amount' => 599,
                'payment_type' => 'demo',
                'payment_status' => 'paid',
                'transaction_id' => 'DEMO-SUBSCRIPTION-001',
                'other_transactions_details' => 'Local demo transaction. No real payment was processed.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('verified_payments')->updateOrInsert(
            ['gateway' => 'demo', 'provider_reference' => 'DEMO-PAYMENT-001'],
            [
                'user_id' => $users['customer']->getKey(),
                'purchase_type' => 'subscription',
                'purchase_id' => $subscriptionId,
                'amount_minor' => 59900,
                'currency' => 'BDT',
                'fulfillment_type' => 'subscription',
                'fulfillment_id' => $subscriptionId,
                'fulfilled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $paymentId = (int) DB::table('verified_payments')
            ->where('gateway', 'demo')
            ->where('provider_reference', 'DEMO-PAYMENT-001')
            ->value('id');

        DB::table('payment_provider_events')->updateOrInsert(
            ['gateway' => 'demo', 'event_id' => 'DEMO-EVENT-001'],
            [
                'event_type' => 'payment.succeeded',
                'provider_reference' => 'DEMO-PAYMENT-001',
                'payload_sha256' => hash('sha256', 'local-demo-payment-event'),
                'status' => 'processed',
                'attempts' => 1,
                'processed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('refund_requests')->updateOrInsert(
            ['idempotency_key' => '00000000-0000-4000-8000-000000000301'],
            [
                'verified_payment_id' => $paymentId,
                'amount_minor' => 59900,
                'currency' => 'BDT',
                'reason' => 'Demo refund request for finance workflow testing.',
                'status' => 'pending',
                'requested_by' => $users['customer']->getKey(),
                'reviewed_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('payment_disputes')->updateOrInsert(
            ['gateway' => 'demo', 'provider_dispute_id' => 'DEMO-DISPUTE-001'],
            [
                'provider_reference' => 'DEMO-PAYMENT-001',
                'verified_payment_id' => $paymentId,
                'amount_minor' => 59900,
                'currency' => 'BDT',
                'status' => 'under_review',
                'reason' => 'sample_dispute',
                'evidence_due_at' => $now->copy()->addDays(7),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('payment_reconciliation_runs')->updateOrInsert(
            ['verified_payment_id' => $paymentId, 'result' => 'matched'],
            [
                'performed_by' => $users['admin']->getKey(),
                'provider_status' => 'paid',
                'provider_amount_minor' => 59900,
                'provider_currency' => 'BDT',
                'amount_matches' => true,
                'currency_matches' => true,
                'status_matches' => true,
                'result' => 'matched',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return $paymentId;
    }

    private function seedMarketing(array $users): int
    {
        $now = now();
        foreach (['email', 'push'] as $channel) {
            foreach ([$users['customer'], $users['second_customer']] as $user) {
                DB::table('marketing_consents')->updateOrInsert(
                    ['user_id' => $user->getKey(), 'channel' => $channel],
                    [
                        'consented' => true,
                        'consented_at' => $now->copy()->subDays(14),
                        'source' => 'demo_seed',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        DB::table('coupons')->updateOrInsert(
            ['code' => 'DEMO-WELCOME'],
            [
                'description' => 'Demo coupon for local UI testing only; it has no payment value.',
                'start_date' => $now->toDateString(),
                'expire_date' => $now->copy()->addYear()->toDateString(),
                'discount_type' => 'percentage',
                'discount' => 10,
                'status' => true,
                'created_by' => $users['admin']->getKey(),
                'updated_by' => $users['admin']->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $couponId = DB::table('coupons')->where('code', 'DEMO-WELCOME')->value('id');
        DB::table('marketing_campaigns')->updateOrInsert(
            ['slug' => 'demo-welcome-campaign'],
            [
                'name' => 'Demo welcome campaign',
                'kind' => 'promotion',
                'channel' => 'email',
                'status' => 'draft',
                'audience_segment' => 'all_consented',
                'subject' => 'Welcome to NOOR Demo',
                'body' => 'Sample campaign content for local review. It is not sent to real customers.',
                'call_to_action' => 'Explore the demo catalog',
                'destination_url' => 'http://localhost:3000/',
                'coupon_id' => $couponId,
                'created_by' => $users['admin']->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $campaignId = (int) DB::table('marketing_campaigns')->where('slug', 'demo-welcome-campaign')->value('id');
        $token = hash('sha256', 'ott-demo-campaign-'.$campaignId);
        DB::table('marketing_campaign_deliveries')->updateOrInsert(
            ['campaign_id' => $campaignId, 'user_id' => $users['customer']->getKey(), 'channel' => 'email'],
            [
                'variant' => 'A',
                'status' => 'queued',
                'tracking_token_hash' => $token,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return $campaignId;
    }

    private function seedSupport(array $users): void
    {
        $now = now();
        DB::table('support_tickets')->updateOrInsert(
            ['public_id' => '00000000-0000-4000-8000-000000000101'],
            [
                'customer_id' => $users['customer']->getKey(),
                'created_by' => $users['customer']->getKey(),
                'assigned_to' => $users['staff']->getKey(),
                'subject' => 'Demo: help with subtitles',
                'description' => 'Sample support ticket for local testing. No customer reported this issue.',
                'category' => 'playback',
                'priority' => 'normal',
                'status' => 'open',
                'first_response_at' => null,
                'resolved_at' => null,
                'created_at' => $now->copy()->subHours(4),
                'updated_at' => $now,
            ],
        );
        $ticketId = (int) DB::table('support_tickets')->where('public_id', '00000000-0000-4000-8000-000000000101')->value('id');
        DB::table('support_ticket_notes')->updateOrInsert(
            ['ticket_id' => $ticketId, 'author_id' => $users['staff']->getKey(), 'note' => 'Demo note: verify the selected subtitle track.'],
            ['is_internal' => true, 'created_at' => $now, 'updated_at' => $now],
        );
    }

    private function seedAdminOperations(array $users, object $content): void
    {
        $now = now();
        $snapshot = ['name' => $content->name, 'status' => 'in_review', 'demo' => true];
        DB::table('admin_content_review_submissions')->updateOrInsert(
            ['content_type' => 'entertainment', 'content_id' => (string) $content->id, 'status' => 'in_review'],
            [
                'revision' => 1,
                'submitted_by' => $users['staff']->getKey(),
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                'submitted_at' => $now->copy()->subHours(2),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('admin_audit_logs')->updateOrInsert(
            ['actor_id' => $users['admin']->getKey(), 'action' => 'demo.seeded', 'resource_type' => 'demo_workspace', 'resource_id' => 'noor-local-demo'],
            [
                'request_id' => 'demo-seed-noor-local',
                'after_values' => json_encode(['fixture' => 'local-demo'], JSON_THROW_ON_ERROR),
                'outcome' => 'success',
                'created_at' => $now,
            ],
        );

        DB::table('analytics_report_runs')->updateOrInsert(
            ['requested_by' => $users['admin']->getKey(), 'metric_group' => 'audience', 'date_from' => $now->toDateString(), 'date_to' => $now->toDateString()],
            [
                'scheduled_at' => $now,
                'status' => 'completed',
                'result' => json_encode(['demo' => true, 'active_viewers' => 2, 'new_registrations' => 2], JSON_THROW_ON_ERROR),
                'generated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('operations_incidents')->updateOrInsert(
            ['title' => 'Demo: playback retry alert'],
            [
                'summary' => 'Sample incident for operations dashboard testing.',
                'severity' => 'low',
                'status' => 'investigating',
                'affected_services' => json_encode(['playback', 'transcoding'], JSON_THROW_ON_ERROR),
                'opened_by' => $users['admin']->getKey(),
                'assigned_to' => $users['staff']->getKey(),
                'created_at' => $now->copy()->subHours(1),
                'updated_at' => $now,
            ],
        );

        $eventTime = $now->copy()->startOfHour();
        DB::table('operations_events')->updateOrInsert(
            ['event_code' => 'demo.playback.retry', 'window_started_at' => $eventTime],
            [
                'service' => 'playback',
                'severity' => 'warning',
                'summary' => 'Sample playback retries',
                'occurrence_count' => 3,
                'created_at' => $eventTime,
            ],
        );

        DB::table('playback_diagnostic_events')->updateOrInsert(
            ['user_id' => $users['customer']->getKey(), 'event_code' => 'demo.buffering'],
            [
                'occurred_at' => $now->copy()->subMinutes(20),
                'device_id' => null,
                'session_id' => null,
                'content_type' => 'movie',
                'content_id' => (string) $content->id,
                'duration_ms' => 1800,
                'quality' => '1080p',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('content_license_windows')->updateOrInsert(
            ['content_type' => 'entertainment', 'content_id' => (string) $content->id],
            [
                'starts_at' => $now->copy()->subDay(),
                'ends_at' => $now->copy()->addYear(),
                'created_by' => $users['admin']->getKey(),
                'updated_by' => $users['admin']->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('content_license_territories')->updateOrInsert(
            ['content_type' => 'entertainment', 'content_id' => (string) $content->id, 'country_code' => 'BD'],
            ['created_by' => $users['admin']->getKey(), 'updated_by' => $users['admin']->getKey(), 'created_at' => $now, 'updated_at' => $now],
        );
    }

    private function seedCustomerNotifications(array $users, object $content, int $campaignId): void
    {
        $now = now();
        $items = [
            ['id' => '00000000-0000-4000-8000-000000000201', 'type' => 'App\\Notifications\\DemoContentAvailable', 'data' => ['title' => $content->name, 'message' => 'Demo title added to your local catalog.', 'url' => '/title/'.$content->slug]],
            ['id' => '00000000-0000-4000-8000-000000000202', 'type' => 'App\\Notifications\\DemoSubscriptionRenewal', 'data' => ['title' => 'Demo subscription active', 'message' => 'Your local demo subscription is active.', 'url' => '/account/subscription']],
        ];

        foreach ($items as $item) {
            DB::table('notifications')->updateOrInsert(
                ['id' => $item['id']],
                [
                    'type' => $item['type'],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $users['customer']->getKey(),
                    'data' => json_encode($item['data'] + ['demo' => true, 'campaign_id' => $campaignId], JSON_THROW_ON_ERROR),
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'archived_at' => null,
                ],
            );
        }
    }

    private function seedCustomerExperience(array $users, object $content): void
    {
        $now = now();
        $customer = $users['customer'];
        DB::table('user_multi_profiles')->updateOrInsert(
            ['user_id' => $customer->getKey(), 'name' => 'Amina'],
            [
                'avatar' => null,
                'is_child_profile' => false,
                'created_by' => $customer->getKey(),
                'updated_by' => $customer->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );
        $adultProfileId = DB::table('user_multi_profiles')
            ->where('user_id', $customer->getKey())
            ->where('name', 'Amina')
            ->value('id');

        DB::table('user_multi_profiles')->updateOrInsert(
            ['user_id' => $customer->getKey(), 'name' => 'Family'],
            [
                'avatar' => null,
                'is_child_profile' => true,
                'created_by' => $customer->getKey(),
                'updated_by' => $customer->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        DB::table('user_profiles')->updateOrInsert(
            ['user_id' => $customer->getKey()],
            [
                'about_self' => 'Demo account for local product testing.',
                'expert' => null,
                'facebook_link' => null,
                'instagram_link' => null,
                'twitter_link' => null,
                'dribbble_link' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        DB::table('watchlists')->updateOrInsert(
            ['user_id' => $customer->getKey(), 'profile_id' => $adultProfileId, 'entertainment_id' => $content->id, 'type' => 'movie'],
            [
                'created_by' => $customer->getKey(),
                'updated_by' => $customer->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        DB::table('user_watch_histories')->updateOrInsert(
            ['user_id' => $customer->getKey(), 'profile_id' => $adultProfileId, 'entertainment_id' => $content->id, 'entertainment_type' => 'movie'],
            [
                'created_by' => $customer->getKey(),
                'updated_by' => $customer->getKey(),
                'deleted_by' => null,
                'deleted_at' => null,
                'created_at' => $now->copy()->subMinutes(18),
                'updated_at' => $now,
            ],
        );

        $search = DB::table('user_search_histories')
            ->where('user_id', $customer->getKey())
            ->where('profile_id', $adultProfileId)
            ->where('search_query', 'Quran reflection')
            ->where('search_id', $content->id)
            ->where('type', 'movie')
            ->first();
        if (! $search) {
            DB::table('user_search_histories')->insert([
                'user_id' => $customer->getKey(),
                'profile_id' => $adultProfileId,
                'search_query' => 'Quran reflection',
                'search_id' => $content->id,
                'type' => 'movie',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('devices')->updateOrInsert(
            ['device_id' => 'demo-device-amina-web'],
            [
                'user_id' => $customer->getKey(),
                'device_name' => 'Demo browser session',
                'active_profile' => (string) $adultProfileId,
                'platform' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );
    }
}
