<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingNotificationGenerator
{
    /** @return array{notifications: int, unread: int, archived: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['notifications'];
        if ($target === 0) return ['notifications' => 0, 'unread' => 0, 'archived' => 0];
        $customers = DB::table('staging_dataset_records as owners')->join('users', 'users.email', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'customer')
            ->where('users.created_at', '<=', $context->referenceDate->endOfDay()->subHours(6))->whereNull('users.deleted_at')
            ->orderBy('users.id')->get(['users.id', 'users.created_at']);
        if ($customers->isEmpty()) throw new RuntimeException('Staging notifications require owned customer accounts.');

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        for ($offset = 0; $offset < $target; $offset += $context->profile->batchSize) {
            $end = min($target, $offset + $context->profile->batchSize);
            $keys = [];
            for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $keys[] = sprintf('stg-%s-notification-%08d', $datasetPart, $ordinal);
            $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'notification')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
            if ($owned->isNotEmpty()) {
                $ids = $owned->pluck('record_id')->all();
                if (DB::table('notifications')->whereIn('id', $ids)->count() !== $owned->count()) {
                    throw new RuntimeException('Owned staging notification records are missing from their source table.');
                }
            }
            $rows = [];
            $owners = [];
            for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                $key = sprintf('stg-%s-notification-%08d', $datasetPart, $ordinal);
                if ($owned->has($key)) continue;
                $customer = $customers[(($ordinal * 37) % $customers->count())];
                $createdAt = $this->createdAt($context, $ordinal, CarbonImmutable::parse($customer->created_at));
                $variant = ($ordinal - 1) % 5;
                [$type, $title, $message] = match ($variant) {
                    0 => ['subscription_update', 'Subscription reminder', 'Your saved subscription preferences are ready to review.'],
                    1 => ['content_update', 'New learning collection', 'A new collection has been added for you to explore.'],
                    2 => ['account_security', 'Account security update', 'Review your recent account security settings.'],
                    3 => ['support_update', 'Support ticket update', 'There is a new update on your support request.'],
                    default => ['plan_update', 'Membership information', 'Your membership information is available in your account.'],
                };
                $id = self::uuid($context->datasetId, $ordinal);
                $read = $ordinal % 4 !== 0;
                $archived = $ordinal % 13 === 0;
                $readAt = $read ? $createdAt->addMinutes(1 + ($ordinal % 240)) : null;
                $archivedAt = $archived ? ($readAt ?? $createdAt->addMinutes(1))->addHours(1) : null;
                $data = ['subject' => $title, 'message' => $message, 'data' => ['notification_type' => $type, 'message' => $message, 'staging' => true]];
                $rows[] = ['id' => $id, 'type' => 'App\\Notifications\\CommonNotification', 'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => (int) $customer->id, 'data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'read_at' => $readAt, 'archived_at' => $archivedAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'notification', 'entity_key' => $key,
                    'record_id' => $id, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($rows === []) continue;
            DB::transaction(function () use ($rows, $owners): void {
                DB::table('notifications')->insert($rows);
                DB::table('staging_dataset_records')->insert($owners);
            });
        }
        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'notification')->count();
        if ($actual !== $target) throw new RuntimeException("Staging notifications generated {$actual} of {$target} configured records.");
        $ownedNotifications = DB::table('staging_dataset_records as owners')
            ->where('owners.dataset_id', $context->datasetId)
            ->where('owners.entity_type', 'notification');

        return ['notifications' => $actual,
            'unread' => (clone $ownedNotifications)
                ->join('notifications', 'notifications.id', '=', 'owners.record_id')
                ->whereNull('notifications.read_at')
                ->whereNull('notifications.archived_at')
                ->count(),
            'archived' => (clone $ownedNotifications)
                ->join('notifications', 'notifications.id', '=', 'owners.record_id')
                ->whereNotNull('notifications.archived_at')
                ->count()];
    }

    private function createdAt(StagingGenerationContext $context, int $ordinal, CarbonImmutable $accountCreatedAt): CarbonImmutable
    {
        $latest = $context->referenceDate->endOfDay()->subHours(6);
        $earliest = $accountCreatedAt->greaterThan($context->historyStart()) ? $accountCreatedAt : $context->historyStart();
        $seconds = max(1, $earliest->diffInSeconds($latest));
        $hash = hash('sha256', $context->seed.':notification:'.$ordinal);
        return $earliest->addSeconds(hexdec(substr($hash, 0, 8)) % $seconds);
    }

    private static function uuid(string $datasetId, int $ordinal): string
    {
        $hex = substr(hash('sha256', $datasetId.':notification:'.$ordinal), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
