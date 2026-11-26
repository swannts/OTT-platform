<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingCouponGenerator
{
    /** @return array{coupons: int, active: int, expired: int, scheduled: int, inactive: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['coupons'];
        $datasetPart = strtoupper(str_replace('-', '', StagingIdentifiers::datasetPart($context->datasetId)));
        $planIds = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'plan')->pluck('record_id')->map(static fn ($id): int => (int) $id)->all();
        if ($target > 0 && $planIds === []) {
            throw new RuntimeException('Staging coupons require owned subscription plans.');
        }
        $totals = ['active' => 0, 'expired' => 0, 'scheduled' => 0, 'inactive' => 0];
        for ($offset = 0; $offset < $target; $offset += $context->profile->batchSize) {
            $end = min($target, $offset + $context->profile->batchSize);
            DB::transaction(function () use ($offset, $end, $context, $datasetPart, &$totals): void {
                $rows = [];
                $owners = [];
                foreach (range($offset + 1, $end) as $ordinal) {
                    $code = sprintf('NOOR-%s-%04d', $datasetPart, $ordinal);
                    $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                        ->where('entity_type', 'coupon')->where('entity_key', $code)->first(['record_id']);
                    if ($owner !== null) {
                        $existing = DB::table('coupons')->where('id', $owner->record_id)->whereNull('deleted_at')->first(['code', 'status', 'start_date', 'expire_date']);
                        if ($existing === null || $existing->code !== $code) {
                            throw new RuntimeException("Refusing to adopt or overwrite a missing or unrelated coupon fixture {$code}.");
                        }
                        $totals[$this->state($existing->status, $existing->start_date, $existing->expire_date, $context)]++;
                        continue;
                    }
                    if (DB::table('coupons')->where('code', $code)->exists()) {
                        throw new RuntimeException("Coupon code {$code} exists without staging ownership.");
                    }
                    [$status, $start, $endDate, $state] = $this->schedule($ordinal, $context);
                    $percentage = $ordinal % 2 === 0;
                    $rows[] = [
                        'code' => $code,
                        'description' => $this->description($ordinal, $percentage),
                        'start_date' => $start,
                        'expire_date' => $endDate,
                        'discount_type' => $percentage ? 'percentage' : 'fixed',
                        'discount' => $percentage ? (5 + ($ordinal % 26)) : (25 + (($ordinal * 13) % 70)),
                        'max_redemptions' => 100 + (($ordinal * 97) % 9900),
                        'per_user_limit' => 1 + ($ordinal % 2),
                        'status' => $status,
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $context->historyStart()->addDays(($ordinal * 13) % max(1, $context->historyStart()->diffInDays($context->referenceDate))),
                        'updated_at' => $context->historyStart()->addDays(($ordinal * 13) % max(1, $context->historyStart()->diffInDays($context->referenceDate))),
                    ];
                    $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'coupon', 'entity_key' => $code, 'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
                    $totals[$state]++;
                }
                if ($rows === []) {
                    return;
                }
                DB::table('coupons')->insert($rows);
                $coupons = DB::table('coupons')->whereIn('code', array_column($rows, 'code'))->get(['id', 'code'])->keyBy('code');
                foreach ($owners as &$owner) {
                    $owner['record_id'] = (string) $coupons[$owner['entity_key']]->id;
                }
                unset($owner);
                DB::table('staging_dataset_records')->insert($owners);
            });
        }

        $mappingCount = $this->ensurePlanMappings($context, $datasetPart, $planIds);
        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'coupon')->count();
        if ($actual !== $target) {
            throw new RuntimeException("Staging coupon generation produced {$actual} of {$target} configured coupons.");
        }
        return ['coupons' => $actual, 'plan_mappings' => $mappingCount, ...$totals];
    }

    /** @param list<int> $planIds */
    private function ensurePlanMappings(StagingGenerationContext $context, string $datasetPart, array $planIds): int
    {
        if ($planIds === []) return 0;
        sort($planIds);
        $coupons = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'coupon')->get(['entity_key', 'record_id']);
        foreach ($coupons as $coupon) {
            if (! preg_match('/-(\d{4})$/', $coupon->entity_key, $matches)) {
                throw new RuntimeException('A staging coupon ownership key is malformed.');
            }
            $ordinal = (int) $matches[1];
            $planCount = min(count($planIds), 1 + ($ordinal % 3));
            $start = ($ordinal * 7) % count($planIds);
            $selected = [];
            for ($i = 0; $i < $planCount; $i++) {
                $selected[] = $planIds[($start + $i) % count($planIds)];
            }
            foreach (array_unique($selected) as $planId) {
                $key = $coupon->entity_key.':'.$planId;
                $ownership = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'coupon_plan_mapping')->where('entity_key', $key)->first(['record_id']);
                $mapping = DB::table('coupon_subscription_plan')->where('coupon_id', $coupon->record_id)
                    ->where('subscription_plan_id', $planId)->first(['id']);
                if ($ownership !== null) {
                    if ($mapping === null || (string) $ownership->record_id !== (string) $mapping->id) {
                        throw new RuntimeException("Coupon-to-plan mapping ownership is missing or inconsistent for {$key}.");
                    }
                    continue;
                }
                if ($mapping !== null) {
                    throw new RuntimeException("Coupon-to-plan mapping {$key} exists without staging ownership.");
                }
                $mappingId = DB::table('coupon_subscription_plan')->insertGetId([
                    'coupon_id' => (int) $coupon->record_id,
                    'subscription_plan_id' => $planId,
                ]);
                DB::table('staging_dataset_records')->insert([
                    'dataset_id' => $context->datasetId,
                    'entity_type' => 'coupon_plan_mapping',
                    'entity_key' => $key,
                    'record_id' => (string) $mappingId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'coupon_plan_mapping')->count();
    }

    /** @return array{int, string, string, string} */
    private function schedule(int $ordinal, StagingGenerationContext $context): array
    {
        $cycle = $ordinal % 20;
        if ($cycle === 0) {
            return [0, $context->referenceDate->subMonths(3)->toDateString(), $context->referenceDate->addMonths(1)->toDateString(), 'inactive'];
        }
        if ($cycle === 1) {
            return [1, $context->referenceDate->subMonths(6)->toDateString(), $context->referenceDate->subDay()->toDateString(), 'expired'];
        }
        if ($cycle === 2) {
            return [1, $context->referenceDate->addDays(30)->toDateString(), $context->referenceDate->addMonths(3)->toDateString(), 'scheduled'];
        }
        return [1, $context->referenceDate->subMonths(2)->toDateString(), $context->referenceDate->addMonths(2)->toDateString(), 'active'];
    }

    private function state(int $status, ?string $start, ?string $end, StagingGenerationContext $context): string
    {
        if (! $status) return 'inactive';
        if ($end !== null && $end < $context->referenceDate->toDateString()) return 'expired';
        if ($start !== null && $start > $context->referenceDate->toDateString()) return 'scheduled';
        return 'active';
    }

    private function description(int $ordinal, bool $percentage): string
    {
        $themes = ['reflection and learning', 'family viewing', 'documentary discovery', 'educational collections', 'new member welcome'];
        $theme = $themes[($ordinal - 1) % count($themes)];
        return 'Synthetic staging promotion for '.$theme.'. '.($percentage ? 'Percentage discount' : 'Fixed-value discount').'; test data only, no external redemption is implied.';
    }
}
