<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingSupportTicketGenerator
{
    /** @return array{tickets: int, notes: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['support_tickets'];
        if ($target === 0) return ['tickets' => 0, 'notes' => 0];

        $customers = DB::table('staging_dataset_records as owners')
            ->join('users', 'users.email', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'customer')
            ->where('users.created_at', '<=', $context->referenceDate->endOfDay()->subDays(4))
            ->whereNull('users.deleted_at')->orderBy('users.id')->get(['users.id', 'users.created_at']);
        $staff = DB::table('staging_dataset_records as owners')
            ->join('users', 'users.email', '=', 'owners.entity_key')
            ->join('model_has_roles as assigned_roles', function ($join): void {
                $join->on('assigned_roles.model_id', '=', 'users.id')->where('assigned_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'assigned_roles.role_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'staff_user')
            ->whereNull('users.deleted_at')->where('roles.name', 'customer_support')->orderBy('users.id')->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
        if ($customers->isEmpty() || $staff === []) throw new RuntimeException('Staging support tickets require owned customer and customer-support staff accounts.');

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        for ($offset = 0; $offset < $target; $offset += $context->profile->batchSize) {
            $end = min($target, $offset + $context->profile->batchSize);
            DB::transaction(function () use ($offset, $end, $context, $datasetPart, $customers, $staff): void {
                $keys = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $keys[] = sprintf('stg-%s-ticket-%08d', $datasetPart, $ordinal);
                $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'support_ticket')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $publicIds = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $publicIds[] = self::uuid($context->datasetId, $ordinal);
                $unownedPublicIds = DB::table('support_tickets')->whereIn('public_id', $publicIds)->pluck('public_id')->flip();
                $rows = [];
                $owners = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                    $key = sprintf('stg-%s-ticket-%08d', $datasetPart, $ordinal);
                    $owner = $existingOwners->get($key);
                    if ($owner !== null) {
                        $existing = DB::table('support_tickets')->where('id', $owner->record_id)->where('public_id', self::uuid($context->datasetId, $ordinal))->first();
                        if ($existing === null) throw new RuntimeException("Owned support ticket {$key} is missing or inconsistent.");
                        continue;
                    }
                    if ($unownedPublicIds->has(self::uuid($context->datasetId, $ordinal))) {
                        throw new RuntimeException("Support ticket {$key} exists without staging ownership.");
                    }

                    $customer = $customers[(($ordinal * 17) % $customers->count())];
                    $created = $this->createdAt($context, $ordinal, CarbonImmutable::parse($customer->created_at));
                    $cycle = $ordinal % 10;
                    $status = match (true) { $cycle < 3 => 'open', $cycle < 5 => 'in_progress', $cycle < 7 => 'waiting_customer', $cycle === 7 => 'escalated', $cycle === 8 => 'resolved', default => 'closed' };
                    $responded = $status !== 'open';
                    $resolved = in_array($status, ['resolved', 'closed'], true);
                    $categories = ['account', 'billing', 'playback', 'device', 'subscription', 'general'];
                    $subjects = ['Help choosing a profile', 'Subtitle selection question', 'Subscription access clarification', 'Playback quality report', 'Device registration assistance', 'Account settings question'];
                    $publicId = self::uuid($context->datasetId, $ordinal);
                    $rows[] = [
                        'public_id' => $publicId,
                        'customer_id' => (int) $customer->id,
                        'created_by' => (int) $customer->id,
                        'assigned_to' => $staff[($ordinal * 7) % count($staff)],
                        'subject' => $subjects[($ordinal - 1) % count($subjects)],
                        'description' => 'Synthetic staging support scenario for account and playback workflow testing. No real customer reported this issue.',
                        'category' => $categories[($ordinal - 1) % count($categories)],
                        'priority' => ['low', 'normal', 'normal', 'high', 'urgent'][($ordinal - 1) % 5],
                        'status' => $status,
                        'escalation_target' => $status === 'escalated' ? (($ordinal % 2) ? 'technical_operations' : 'finance') : null,
                        'first_response_at' => $responded ? $created->addMinutes(5 + ($ordinal % 180)) : null,
                        'resolved_at' => $resolved ? $created->addHours(1 + ($ordinal % 96)) : null,
                        'created_at' => $created,
                        'updated_at' => $resolved ? $created->addHours(1 + ($ordinal % 96)) : ($responded ? $created->addMinutes(5 + ($ordinal % 180)) : $created),
                    ];
                    $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'support_ticket', 'entity_key' => $key, 'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
                }
                if ($rows === []) return;
                DB::table('support_tickets')->insert($rows);
                $ids = DB::table('support_tickets')->whereIn('public_id', array_column($rows, 'public_id'))->pluck('id', 'public_id');
                foreach ($owners as &$owner) {
                    $ordinal = (int) substr($owner['entity_key'], -8);
                    $owner['record_id'] = (string) $ids[self::uuid($context->datasetId, $ordinal)];
                }
                unset($owner);
                DB::table('staging_dataset_records')->insert($owners);
            });
        }

        $offset = 0;
        do {
            $tickets = DB::table('staging_dataset_records as owners')->join('support_tickets as tickets', 'tickets.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'support_ticket')
                ->whereNotNull('tickets.first_response_at')->orderBy('tickets.id')->offset($offset)->limit($context->profile->batchSize)
                ->get(['tickets.id', 'tickets.assigned_to', 'tickets.first_response_at', 'owners.entity_key']);
            $offset += $context->profile->batchSize;
            $noteKeys = $tickets->map(fn ($ticket): string => sprintf('stg-%s-note-%08d', StagingIdentifiers::datasetPart($context->datasetId), (int) substr($ticket->entity_key, -8)))->all();
            $existingNotes = $noteKeys === [] ? collect() : DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'support_ticket_note')->whereIn('entity_key', $noteKeys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
            $rows = [];
            foreach ($tickets as $ticket) {
                $ordinal = (int) substr($ticket->entity_key, -8);
                $noteKey = sprintf('stg-%s-note-%08d', StagingIdentifiers::datasetPart($context->datasetId), $ordinal);
                $ownedNote = $existingNotes->get($noteKey);
                if ($ownedNote !== null) {
                    if (! DB::table('support_ticket_notes')->where('id', $ownedNote->record_id)->where('ticket_id', $ticket->id)->exists()) {
                        throw new RuntimeException("Owned support note {$noteKey} is missing or inconsistent.");
                    }
                    continue;
                }
                $rows[] = ['ticket_id' => $ticket->id, 'author_id' => $ticket->assigned_to,
                    'note' => 'Synthetic internal note: review the reported scenario and follow the standard support workflow.',
                    'is_internal' => true, 'created_at' => $ticket->first_response_at, 'updated_at' => $ticket->first_response_at,
                    '_ownership_key' => $noteKey];
            }
            if ($rows !== []) DB::transaction(function () use ($rows, $context): void {
                $keys = array_column($rows, '_ownership_key');
                foreach ($rows as &$row) unset($row['_ownership_key']);
                unset($row);
                DB::table('support_ticket_notes')->insert($rows);
                $firstId = (int) DB::scalar('SELECT LAST_INSERT_ID()');
                if ($firstId < 1) throw new RuntimeException('Could not resolve generated support-note identifiers.');
                $owners = [];
                foreach ($keys as $index => $key) {
                    $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'support_ticket_note',
                        'entity_key' => $key,
                        'record_id' => (string) ($firstId + $index), 'created_at' => now(), 'updated_at' => now()];
                }
                DB::table('staging_dataset_records')->insert($owners);
            });
        } while ($tickets->count() === $context->profile->batchSize);

        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'support_ticket')->count();
        if ($actual !== $target) throw new RuntimeException("Staging support generated {$actual} of {$target} configured tickets.");
        return ['tickets' => $actual, 'notes' => DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'support_ticket_note')->count()];
    }

    private function createdAt(StagingGenerationContext $context, int $ordinal, CarbonImmutable $customerCreatedAt): CarbonImmutable
    {
        $latest = $context->referenceDate->endOfDay()->subDays(4);
        $earliest = $customerCreatedAt->greaterThan($context->historyStart()) ? $customerCreatedAt : $context->historyStart();
        $seconds = max(1, $earliest->diffInSeconds($latest));
        $hash = hash('sha256', $context->seed.':ticket:'.$ordinal);
        return $earliest->addSeconds(hexdec(substr($hash, 0, 8)) % $seconds);
    }

    private static function uuid(string $datasetId, int $ordinal): string
    {
        $hex = substr(hash('sha256', $datasetId.':support-ticket:'.$ordinal), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
