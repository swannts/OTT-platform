<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingContentLicensingGenerator
{
    /** @return array{windows: int, territories: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $content = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'entertainment')->orderBy('entity_key')->get(['entity_key', 'record_id']);
        $windows = 0;
        $territories = 0;
        $offset = 0;
        foreach ($content->chunk(100) as $items) {
            [$windowCount, $territoryCount] = $this->seedChunk($context, $items->all(), $offset);
            $windows += $windowCount;
            $territories += $territoryCount;
            $offset += $items->count();
        }

        return ['windows' => $windows, 'territories' => $territories];
    }

    /** @param list<object> $content
     *  @return array{int, int}
     */
    private function seedChunk(StagingGenerationContext $context, array $content, int $offset): array
    {
        $expectations = [];
        foreach ($content as $localIndex => $item) {
            $index = $offset + $localIndex;
            if ($index % 10 === 9) {
                continue;
            }
            $key = (string) $item->entity_key;
            $territoryCodes = ['BD'];
            if ($index % 4 === 0) $territoryCodes[] = 'SA';
            if ($index % 5 === 0) $territoryCodes[] = 'MY';
            if ($index % 7 === 0) $territoryCodes[] = 'GB';

            $playableFixtureRequired = $index === 0
                && $context->profile->counts['media_assets'] > 0
                && $context->profile->counts['playback_sessions'] > 0;
            if ($index % 10 === 0 && ! $playableFixtureRequired) {
                $startsAt = $context->referenceDate->addDays(1 + ($index % 90))->startOfDay();
                $endsAt = $startsAt->addDays(365);
            } elseif ($index % 10 === 1) {
                $startsAt = $context->historyStart();
                $endsAt = $context->referenceDate->subDays(1 + ($index % 180))->endOfDay();
            } else {
                $startsAt = $context->referenceDate->subDays(360 - ($index % 300))->startOfDay();
                $endsAt = $context->referenceDate->addDays(30 + ($index % 180))->endOfDay();
            }
            $expectations[] = [
                'content_key' => $key,
                'content_id' => (string) $item->record_id,
                'window_key' => $key,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'territories' => $territoryCodes,
            ];
        }
        if ($expectations === []) {
            return [0, 0];
        }

        return DB::transaction(function () use ($context, $expectations): array {
            $windowKeys = array_column($expectations, 'window_key');
            $territoryKeys = [];
            $contentIds = array_column($expectations, 'content_id');
            foreach ($expectations as $item) {
                foreach ($item['territories'] as $code) {
                    $territoryKeys[] = $item['content_key'].':'.$code;
                }
            }
            $ownedWindows = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'content_license_window')->whereIn('entity_key', $windowKeys)
                ->get(['entity_key', 'record_id'])->keyBy('entity_key');
            $existingWindows = DB::table('content_license_windows')->whereIn('id', $ownedWindows->pluck('record_id')->filter()->all())
                ->get(['id', 'content_type', 'content_id', 'starts_at', 'ends_at'])->keyBy('id');
            $unownedWindows = array_fill_keys(DB::table('content_license_windows')
                ->where('content_type', 'entertainment')->whereIn('content_id', $contentIds)
                ->pluck('content_id')->all(), true);
            $ownedTerritories = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'content_license_territory')->whereIn('entity_key', $territoryKeys)
                ->pluck('entity_key')->all();
            $ownedTerritoryMap = array_fill_keys($ownedTerritories, true);
            $existingTerritoryRows = DB::table('content_license_territories')
                ->where('content_type', 'entertainment')->whereIn('content_id', $contentIds)
                ->get(['content_id', 'country_code'])
                ->mapWithKeys(static fn (object $row): array => [(string) $row->content_id.':'.$row->country_code => true]);

            $newTerritoryRows = [];
            $newTerritoryOwners = [];
            $windowCount = 0;
            $territoryCount = 0;
            foreach ($expectations as $item) {
                $windowCount++;
                $windowOwner = $ownedWindows->get($item['window_key']);
                $window = $windowOwner?->record_id ? $existingWindows->get($windowOwner->record_id) : null;
                if ($window) {
                    if ($window->content_type !== 'entertainment' || $window->content_id !== $item['content_id']
                        || (string) $window->starts_at !== $item['starts_at']->format('Y-m-d H:i:s')
                        || (string) $window->ends_at !== $item['ends_at']->format('Y-m-d H:i:s')) {
                        throw new RuntimeException("Staging license window {$item['window_key']} no longer matches its deterministic fixture.");
                    }
                } else {
                    if ($windowOwner || isset($unownedWindows[$item['content_id']])) {
                        throw new RuntimeException("Staging license window {$item['window_key']} is incomplete or unowned.");
                    }
                    $windowId = DB::table('content_license_windows')->insertGetId([
                        'content_type' => 'entertainment',
                        'content_id' => $item['content_id'],
                        'starts_at' => $item['starts_at'],
                        'ends_at' => $item['ends_at'],
                        'created_at' => $context->historyStart(),
                        'updated_at' => $context->historyStart(),
                    ]);
                    DB::table('staging_dataset_records')->insert(
                        $this->owner($context->datasetId, 'content_license_window', $item['window_key'], (string) $windowId)
                    );
                }

                foreach ($item['territories'] as $code) {
                    $territoryCount++;
                    $key = $item['content_key'].':'.$code;
                    $exists = isset($existingTerritoryRows[$item['content_id'].':'.$code]);
                    if (isset($ownedTerritoryMap[$key])) {
                        if (! $exists) {
                            throw new RuntimeException("Staging territory license {$key} is owned but its policy row is missing.");
                        }
                        continue;
                    }
                    if ($exists) {
                        throw new RuntimeException("Staging territory license {$key} already exists without dataset ownership.");
                    }
                    $newTerritoryRows[] = [
                        'content_type' => 'entertainment',
                        'content_id' => $item['content_id'],
                        'country_code' => $code,
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $context->historyStart(),
                        'updated_at' => $context->historyStart(),
                    ];
                    $newTerritoryOwners[] = $this->owner($context->datasetId, 'content_license_territory', $key, null);
                }
            }
            if ($newTerritoryRows !== []) {
                DB::table('content_license_territories')->insert($newTerritoryRows);
                DB::table('staging_dataset_records')->insert($newTerritoryOwners);
            }

            return [$windowCount, $territoryCount];
        });
    }

    /** @return array{dataset_id: string, entity_type: string, entity_key: string, record_id: ?string, created_at: \Illuminate\Support\Carbon, updated_at: \Illuminate\Support\Carbon} */
    private function owner(string $datasetId, string $entityType, string $entityKey, ?string $recordId): array
    {
        return [
            'dataset_id' => $datasetId,
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'record_id' => $recordId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
