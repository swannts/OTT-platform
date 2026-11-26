<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingSubscriptionGenerator
{
    private const LIMITATIONS = ['simultaneous-streams', 'device-limit', 'profile-limit', 'max-video-quality'];

    /** @var list<array{name: string, price: float, duration: string, duration_value: int, level: int, trial_days: int, streams: int, devices: int, profiles: int, quality: string, discount: int}> */
    private const PLAN_TEMPLATES = [
        ['name' => 'Staging Essential (প্রাথমিক)', 'price' => 99.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 1, 'trial_days' => 7, 'streams' => 1, 'devices' => 2, 'profiles' => 2, 'quality' => '720p', 'discount' => 0],
        ['name' => 'Staging Family (পরিবার)', 'price' => 249.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 2, 'trial_days' => 0, 'streams' => 2, 'devices' => 4, 'profiles' => 4, 'quality' => '1080p', 'discount' => 0],
        ['name' => 'Staging Plus (প্লাস)', 'price' => 399.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 3, 'trial_days' => 0, 'streams' => 3, 'devices' => 5, 'profiles' => 5, 'quality' => '4K', 'discount' => 0],
        ['name' => 'Staging Annual Essential (বার্ষিক)', 'price' => 999.0, 'duration' => 'year', 'duration_value' => 1, 'level' => 4, 'trial_days' => 0, 'streams' => 1, 'devices' => 2, 'profiles' => 2, 'quality' => '1080p', 'discount' => 16],
        ['name' => 'Staging Annual Family (বার্ষিক পরিবার)', 'price' => 2499.0, 'duration' => 'year', 'duration_value' => 1, 'level' => 5, 'trial_days' => 0, 'streams' => 3, 'devices' => 5, 'profiles' => 5, 'quality' => '4K', 'discount' => 16],
        ['name' => 'Staging Monthly 2 (মাসিক)', 'price' => 129.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 6, 'trial_days' => 0, 'streams' => 1, 'devices' => 3, 'profiles' => 3, 'quality' => '1080p', 'discount' => 0],
        ['name' => 'Staging Monthly 3 (মাসিক)', 'price' => 179.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 7, 'trial_days' => 0, 'streams' => 2, 'devices' => 4, 'profiles' => 4, 'quality' => '1080p', 'discount' => 5],
        ['name' => 'Staging Annual Plus (বার্ষিক প্লাস)', 'price' => 3599.0, 'duration' => 'year', 'duration_value' => 1, 'level' => 8, 'trial_days' => 0, 'streams' => 4, 'devices' => 5, 'profiles' => 5, 'quality' => '4K', 'discount' => 20],
        ['name' => 'Staging Monthly 4 (মাসিক)', 'price' => 299.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 9, 'trial_days' => 0, 'streams' => 3, 'devices' => 5, 'profiles' => 5, 'quality' => '4K', 'discount' => 0],
        ['name' => 'Staging Annual 2 (বার্ষিক)', 'price' => 1499.0, 'duration' => 'year', 'duration_value' => 1, 'level' => 10, 'trial_days' => 0, 'streams' => 2, 'devices' => 4, 'profiles' => 4, 'quality' => '1080p', 'discount' => 12],
        ['name' => 'Staging Monthly 5 (মাসিক)', 'price' => 499.0, 'duration' => 'month', 'duration_value' => 1, 'level' => 11, 'trial_days' => 0, 'streams' => 4, 'devices' => 5, 'profiles' => 5, 'quality' => '4K', 'discount' => 0],
        ['name' => 'Staging Annual 3 (বার্ষিক)', 'price' => 4499.0, 'duration' => 'year', 'duration_value' => 1, 'level' => 12, 'trial_days' => 0, 'streams' => 4, 'devices' => 5, 'profiles' => 5, 'quality' => '4K', 'discount' => 20],
    ];

    /** @return array{plans: int, subscriptions: int, payments: int, active: int, trials: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $planCount = $context->profile->counts['plans'];
        $subscriptionTarget = $context->profile->counts['subscriptions'];
        $paymentTarget = $context->profile->counts['payments'];
        if ($planCount > count(self::PLAN_TEMPLATES)) {
            throw new RuntimeException('The staging plan templates do not cover the configured plan target.');
        }

        $references = DB::table('planlimitation')->whereIn('slug', self::LIMITATIONS)
            ->where('status', 1)->whereNull('deleted_at')->get(['id', 'slug'])->keyBy('slug');
        if ($references->count() !== count(self::LIMITATIONS)) {
            throw new RuntimeException('Required active plan limitation reference data is missing; run the platform reference-data migrations first.');
        }

        $plans = $this->seedPlans($context, $planCount, $references);
        $customers = DB::table('staging_dataset_records as owners')
            ->join('users', 'users.email', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)
            ->where('owners.entity_type', 'customer')
            ->whereNull('users.deleted_at')
            ->orderBy('users.email')
            ->get(['users.id', 'users.email', 'users.status', 'users.is_subscribe', 'users.created_at']);

        $monthlyPlanCount = count(array_filter($plans, static fn (array $plan): bool => $plan['duration'] === 'month'));
        $events = $this->buildSubscriptionEvents($context, $customers, $subscriptionTarget, $monthlyPlanCount);
        $nonTrialEvents = count(array_filter($events, static fn (array $event): bool => ! $event['is_trial']));
        if ($paymentTarget < $nonTrialEvents) {
            throw new RuntimeException("The configured payment target {$paymentTarget} cannot cover {$nonTrialEvents} non-trial subscription purchases.");
        }
        if ($paymentTarget > 0 && $planCount === 0) {
            throw new RuntimeException('Payment generation requires at least one staging plan.');
        }

        $attempts = $this->distributePaymentAttempts($events, $paymentTarget);
        [$createdSubscriptions, $createdPayments, $activeUserIds] = $this->persistSubscriptionEvents($context, $events, $attempts, $plans);
        $this->synchronizeCustomerEntitlements($context, $customers, $activeUserIds);

        return [
            'plans' => count($plans),
            'subscriptions' => count($events),
            'payments' => $paymentTarget,
            'active' => count($activeUserIds),
            'trials' => count(array_filter($events, static fn (array $event): bool => $event['is_trial'])),
            'created_subscriptions' => $createdSubscriptions,
            'created_payments' => $createdPayments,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function seedPlans(StagingGenerationContext $context, int $planCount, object $references): array
    {
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $plans = [];
        foreach (array_slice(self::PLAN_TEMPLATES, 0, $planCount) as $index => $template) {
            $identifier = sprintf('stg-%s-plan-%02d', $datasetPart, $index + 1);
            $limitValues = [
                'simultaneous-streams' => (string) $template['streams'],
                'device-limit' => (string) $template['devices'],
                'profile-limit' => (string) $template['profiles'],
                'max-video-quality' => $template['quality'],
            ];
            $snapshot = [];
            foreach (self::LIMITATIONS as $slug) {
                $snapshot[] = [
                    'slug' => $slug,
                    'limitation_slug' => $slug,
                    'limitation_value' => 1,
                    'limit' => $limitValues[$slug],
                ];
            }
            $discountAmount = round($template['price'] * $template['discount'] / 100, 2);
            $planData = [
                'name' => $template['name'],
                'identifier' => $identifier,
                'price' => $template['price'],
                'discount' => $template['discount'] > 0 ? 1 : 0,
                'discount_percentage' => $template['discount'],
                'total_price' => round($template['price'] - $discountAmount, 2),
                'level' => $template['level'],
                'duration' => $template['duration'],
                'duration_value' => $template['duration_value'],
                'trial_days' => $template['trial_days'],
                'status' => 1,
                'description' => 'Synthetic staging-only plan priced in BDT. It is not connected to a payment provider.',
                'created_at' => $context->historyStart(),
                'updated_at' => $context->historyStart(),
            ];

            $plan = DB::transaction(function () use ($context, $identifier, $index, $planData, $template, $references, $limitValues, $snapshot): array {
                $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'plan')->where('entity_key', $identifier)->first();
                $existing = $owner?->record_id ? DB::table('plan')->where('id', $owner->record_id)->first() : null;
                if ($existing) {
                    if ($existing->identifier !== $identifier || $existing->name !== $template['name']
                        || (int) $existing->status !== 1 || (string) $existing->duration !== $template['duration']
                        || (int) $existing->duration_value !== $template['duration_value']
                        || (int) $existing->trial_days !== $template['trial_days']
                        || (float) $existing->price !== $template['price']
                        || (float) $existing->total_price !== (float) $planData['total_price']) {
                        throw new RuntimeException("Refusing to alter existing staging plan {$identifier}.");
                    }
                    $planId = (int) $existing->id;
                } elseif ($owner) {
                    throw new RuntimeException("Plan ownership for {$identifier} exists but its record is missing.");
                } else {
                    if (DB::table('plan')->where('identifier', $identifier)->exists()) {
                        throw new RuntimeException("An unowned plan already uses staging identifier {$identifier}; refusing to adopt it.");
                    }
                    $planId = (int) DB::table('plan')->insertGetId($planData);
                    $this->register($context->datasetId, 'plan', $identifier, (string) $planId);
                }

                $mappingKeys = array_map(static fn (string $slug): string => $identifier.':'.$slug, self::LIMITATIONS);
                $ownedMappings = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'planlimitation_mapping')->whereIn('entity_key', $mappingKeys)
                    ->get(['entity_key', 'record_id'])->keyBy('entity_key');
                foreach (self::LIMITATIONS as $slug) {
                    $mappingKey = $identifier.':'.$slug;
                    $mappingOwner = $ownedMappings->get($mappingKey);
                    $mapping = $mappingOwner?->record_id
                        ? DB::table('planlimitation_mapping')->where('id', $mappingOwner->record_id)->first()
                        : null;
                    if ($mapping) {
                        if ((int) $mapping->plan_id !== $planId || $mapping->limitation_slug !== $slug
                            || (int) $mapping->planlimitation_id !== (int) $references[$slug]->id
                            || (int) $mapping->limitation_value !== 1 || (string) $mapping->limit !== $limitValues[$slug]) {
                            throw new RuntimeException("Staging plan mapping {$mappingKey} no longer matches its owned plan.");
                        }
                        continue;
                    }
                    if ($mappingOwner || DB::table('planlimitation_mapping')->where('plan_id', $planId)->where('limitation_slug', $slug)->exists()) {
                        throw new RuntimeException("Staging plan mapping {$mappingKey} is incomplete or unowned; refusing to adopt it.");
                    }
                    $mappingId = (int) DB::table('planlimitation_mapping')->insertGetId([
                        'plan_id' => $planId,
                        'planlimitation_id' => $references[$slug]->id,
                        'limitation_slug' => $slug,
                        'limitation_value' => 1,
                        'limit' => $limitValues[$slug],
                        'created_at' => $context->historyStart(),
                        'updated_at' => $context->historyStart(),
                    ]);
                    $this->register($context->datasetId, 'planlimitation_mapping', $mappingKey, (string) $mappingId);
                }

                return [
                    'id' => $planId,
                    'index' => $index,
                    ...$planData,
                    'plan_type' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                ];
            });
            $plans[] = $plan;
        }

        return $plans;
    }

    /** @param \Illuminate\Support\Collection<int, object> $customers
     *  @return list<array<string, mixed>>
     */
    private function buildSubscriptionEvents(StagingGenerationContext $context, $customers, int $target, int $monthlyPlanCount): array
    {
        if ($target === 0) {
            return [];
        }
        $customerCount = $customers->count();
        if ($customerCount === 0) {
            throw new RuntimeException('Subscription generation requires owned staging customers.');
        }

        $uniqueCustomerCount = min($target, $customerCount);
        $eventCounts = array_fill(0, $customerCount, 0);
        for ($eventIndex = 0; $eventIndex < $uniqueCustomerCount; $eventIndex++) {
            $customerIndex = (int) floor($eventIndex * $customerCount / $uniqueCustomerCount);
            $eventCounts[$customerIndex] = 1;
        }

        $repeatTarget = $target - $uniqueCustomerCount;
        if ($repeatTarget > 0) {
            $repeatCutoff = $context->referenceDate->subDays(90);
            $eligibleCustomers = [];
            foreach ($customers as $index => $customer) {
                if ((int) $customer->status === 1 && \Carbon\CarbonImmutable::parse($customer->created_at)->lessThanOrEqualTo($repeatCutoff)) {
                    $eligibleCustomers[] = $index;
                }
            }
            if ($repeatTarget > count($eligibleCustomers)) {
                throw new RuntimeException('The requested subscription history exceeds the number of staging customers old enough for a valid renewal window.');
            }
            foreach (array_slice($eligibleCustomers, 0, $repeatTarget) as $customerIndex) {
                $eventCounts[$customerIndex]++;
            }
        }

        $events = [];
        foreach ($customers as $index => $customer) {
            $count = $eventCounts[$index];
            for ($sequence = 0; $sequence < $count; $sequence++) {
                $isLatest = $sequence === $count - 1;
                $createdAt = \Carbon\CarbonImmutable::parse($customer->created_at);
                $bucket = ($index * 37) % 100;
                if ((int) $customer->status !== 1 && $isLatest && $bucket < 35) {
                    $bucket = 70;
                }
                $planIndex = ($index + $sequence) % max(1, $monthlyPlanCount);
                $accountCanSubscribe = (int) $customer->status === 1;
                $isTrial = $isLatest && $accountCanSubscribe && $bucket >= 30 && $bucket < 35 && $planIndex === 0;
                $hasExpiredWindow = $createdAt->lessThanOrEqualTo($context->referenceDate->subDays(31));
                $active = $isLatest && $accountCanSubscribe && ($bucket < 30 || $isTrial || ! $hasExpiredWindow);
                $isPaid = ! $isTrial && (! $isLatest || ($accountCanSubscribe && ($active || ($bucket < 50 && $hasExpiredWindow))));

                if (! $isLatest) {
                    $start = $createdAt->greaterThan($context->referenceDate->subDays(90))
                        ? $createdAt
                        : $context->referenceDate->subDays(90);
                    $end = $start->addMonth();
                } elseif ($isTrial) {
                    $start = $createdAt->greaterThan($context->referenceDate->subDays(6))
                        ? $createdAt
                        : $context->referenceDate->subDays(6);
                    $end = $start->addDays(7);
                } elseif ($active) {
                    $start = $createdAt->greaterThan($context->referenceDate->subDays(27))
                        ? $createdAt
                        : $context->referenceDate->subDays(27);
                    $end = $start->addMonth();
                } elseif ($isPaid) {
                    $start = $createdAt->greaterThan($context->referenceDate->subDays(60))
                        ? $createdAt
                        : $context->referenceDate->subDays(60);
                    $end = $start->addMonth();
                } else {
                    $start = $createdAt->greaterThan($context->referenceDate->subDays(60))
                        ? $createdAt
                        : $context->referenceDate->subDays(60);
                    $end = $start->addMonth();
                }

                $events[] = [
                    'customer_index' => $index,
                    'customer_id' => (int) $customer->id,
                    'email' => (string) $customer->email,
                    'event_sequence' => $sequence + 1,
                    'is_latest' => $isLatest,
                    'is_trial' => $isTrial,
                    'is_paid' => $isPaid,
                    'status' => $active ? 'active' : 'inactive',
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return $events;
    }

    /** @param list<array<string, mixed>> $events
     *  @return list<int>
     */
    private function distributePaymentAttempts(array $events, int $paymentTarget): array
    {
        $attempts = array_fill(0, count($events), 0);
        $eligible = [];
        foreach ($events as $index => $event) {
            if (! $event['is_trial']) {
                $attempts[$index] = 1;
                $eligible[] = $index;
            }
        }

        $remaining = $paymentTarget - array_sum($attempts);
        foreach ($eligible as $offset => $eventIndex) {
            if ($remaining <= 0) {
                break;
            }
            $extra = intdiv($remaining, max(1, count($eligible) - $offset));
            if ($remaining % max(1, count($eligible) - $offset) !== 0) {
                $extra++;
            }
            $attempts[$eventIndex] += $extra;
            $remaining -= $extra;
        }

        return $attempts;
    }

    /** @param list<array<string, mixed>> $events
     *  @param list<int> $attempts
     *  @param list<array<string, mixed>> $plans
     *  @return array{int, int, list<int>}
     */
    private function persistSubscriptionEvents(StagingGenerationContext $context, array $events, array $attempts, array $plans): array
    {
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $planIndexes = [];
        foreach ($plans as $plan) {
            if ($plan['duration'] === 'month') {
                $planIndexes[] = $plan['index'];
            }
        }
        if ($events !== [] && $planIndexes === []) {
            throw new RuntimeException('Subscription generation requires at least one monthly staging plan for coherent renewal history.');
        }

        $createdSubscriptions = 0;
        $createdPayments = 0;
        $activeUserIds = [];
        foreach (array_chunk(array_keys($events), $context->profile->batchSize) as $eventIndexes) {
            DB::transaction(function () use ($context, $events, $attempts, $eventIndexes, $plans, $planIndexes, $datasetPart, &$createdSubscriptions, &$createdPayments, &$activeUserIds): void {
                $fixtureKeys = array_map(static fn (int $index): string => sprintf('stg-%s-sub-%07d', $datasetPart, $index + 1), $eventIndexes);
                $ownedSubscriptions = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'subscription')->whereIn('entity_key', $fixtureKeys)
                    ->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $existingSubscriptions = DB::table('subscriptions')->whereIn('id', $ownedSubscriptions->pluck('record_id')->filter()->all())
                    ->get(['id', 'plan_id', 'user_id', 'start_date', 'end_date', 'status', 'is_trial', 'total_amount', 'payment_id'])->keyBy('id');

                foreach ($eventIndexes as $eventIndex) {
                    $event = $events[$eventIndex];
                    $eventKey = sprintf('stg-%s-sub-%07d', $datasetPart, $eventIndex + 1);
                    $plan = $plans[$planIndexes[($event['customer_index'] + $event['event_sequence'] - 1) % count($planIndexes)]];
                    $owner = $ownedSubscriptions->get($eventKey);
                    $existing = $owner?->record_id ? $existingSubscriptions->get($owner->record_id) : null;
                    if ($existing) {
                        if ((int) $existing->user_id !== $event['customer_id'] || (int) $existing->plan_id !== $plan['id'] || $existing->status !== $event['status']) {
                            throw new RuntimeException("Existing staging subscription {$eventKey} no longer matches its generated lifecycle.");
                        }
                        if ($event['status'] === 'active' && $event['is_latest']) {
                            $activeUserIds[] = $event['customer_id'];
                        }
                        continue;
                    }
                    if ($owner) {
                        throw new RuntimeException("Subscription ownership for {$eventKey} exists but its row is missing.");
                    }

                    $amount = (float) $plan['total_price'];
                    $subscriptionId = (int) DB::table('subscriptions')->insertGetId([
                        'plan_id' => $plan['id'],
                        'user_id' => $event['customer_id'],
                        'start_date' => $event['start'],
                        'end_date' => $event['end'],
                        'status' => $event['status'],
                        'is_trial' => $event['is_trial'] ? 1 : 0,
                        'is_manual' => 0,
                        'amount' => $plan['price'],
                        'discount_percentage' => $plan['discount_percentage'],
                        'tax_amount' => 0,
                        'coupon_discount' => 0,
                        'total_amount' => $event['is_trial'] ? 0 : $amount,
                        'name' => $plan['name'],
                        'identifier' => $plan['identifier'],
                        'type' => $event['is_trial'] ? 'trial' : $plan['duration'],
                        'duration' => $event['is_trial'] ? 7 : $plan['duration_value'],
                        'level' => $plan['level'],
                        'plan_type' => $plan['plan_type'],
                        'payment_id' => null,
                        'created_at' => $event['start'],
                        'updated_at' => $event['start'],
                    ]);
                    $this->register($context->datasetId, 'subscription', $eventKey, (string) $subscriptionId);
                    $createdSubscriptions++;
                    $lastPaidTransactionId = null;

                    for ($attemptIndex = 0; $attemptIndex < $attempts[$eventIndex]; $attemptIndex++) {
                        $paymentKey = $eventKey.':payment:'.str_pad((string) ($attemptIndex + 1), 03, '0', STR_PAD_LEFT);
                        $paymentStatus = $event['is_paid']
                            ? ($attemptIndex === 0 || $attemptIndex % 2 === 0 ? 'paid' : 'failed')
                            : 'failed';
                        $transactionAt = $event['start']->addDays(min($attemptIndex * 5, 20));
                        $transactionId = sprintf('stg-%s-payment-%07d', $datasetPart, $eventIndex * 1000 + $attemptIndex + 1);
                        $paymentId = (int) DB::table('subscriptions_transactions')->insertGetId([
                            'subscriptions_id' => $subscriptionId,
                            'user_id' => $event['customer_id'],
                            'amount' => $event['is_trial'] ? 0 : $amount,
                            'currency_code' => 'BDT',
                            'payment_type' => 'synthetic-staging',
                            'payment_status' => $paymentStatus,
                            'transaction_id' => $transactionId,
                            'tax_data' => json_encode([], JSON_THROW_ON_ERROR),
                            'other_transactions_details' => json_encode([
                                'origin' => 'synthetic-staging',
                                'external_request_sent' => false,
                                'attempt' => $attemptIndex + 1,
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => $transactionAt,
                            'updated_at' => $transactionAt,
                        ]);
                        $this->register($context->datasetId, 'subscription_transaction', $paymentKey, (string) $paymentId);
                        $createdPayments++;
                        if ($paymentStatus === 'paid') {
                            $lastPaidTransactionId = $paymentId;
                        }
                    }

                    if ($lastPaidTransactionId !== null) {
                        DB::table('subscriptions')->where('id', $subscriptionId)->update(['payment_id' => $lastPaidTransactionId]);
                    }
                    if ($event['status'] === 'active' && $event['is_latest']) {
                        $activeUserIds[] = $event['customer_id'];
                    }
                }
            });
        }

        return [$createdSubscriptions, $createdPayments, array_values(array_unique($activeUserIds))];
    }

    /** @param \Illuminate\Support\Collection<int, object> $customers
     *  @param list<int> $activeUserIds
     */
    private function synchronizeCustomerEntitlements(StagingGenerationContext $context, $customers, array $activeUserIds): void
    {
        $activeMap = array_fill_keys($activeUserIds, true);
        foreach ($customers->chunk(500) as $customerChunk) {
            $ownedEmails = array_fill_keys(DB::table('staging_dataset_records')
                ->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'customer')
                ->whereIn('entity_key', $customerChunk->pluck('email')->all())
                ->pluck('entity_key')->all(), true);
            $expectedChanges = [];
            foreach ($customerChunk as $customer) {
                $expected = isset($activeMap[(int) $customer->id]) ? 1 : 0;
                if ((int) $customer->is_subscribe !== $expected) {
                    if (! isset($ownedEmails[$customer->email])) {
                        throw new RuntimeException("Refusing to change the entitlement flag for unowned customer {$customer->email}.");
                    }
                    $expectedChanges[] = (int) $customer->id;
                }
            }
            if ($expectedChanges !== []) {
                $expectedActiveIds = array_values(array_intersect($expectedChanges, $activeUserIds));
                DB::table('users')->whereIn('id', $expectedChanges)->update(['is_subscribe' => 0]);
                if ($expectedActiveIds !== []) {
                    DB::table('users')->whereIn('id', $expectedActiveIds)->update(['is_subscribe' => 1]);
                }
            }
        }
    }

    private function register(string $datasetId, string $entityType, string $entityKey, string $recordId): void
    {
        DB::table('staging_dataset_records')->insert([
            'dataset_id' => $datasetId,
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'record_id' => $recordId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
