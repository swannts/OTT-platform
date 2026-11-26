<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingBannerGenerator
{
    public function generate(StagingGenerationContext $context): int
    {
        $target = $context->profile->counts['banners'];
        if ($target === 0) return 0;

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $titles = DB::table('staging_dataset_records as owners')
            ->join('entertainments as content', 'content.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'entertainment')
            ->where('content.status', 1)->whereNotNull('content.poster_url')->whereNull('content.deleted_at')
            ->orderBy('content.id')->get(['content.id', 'content.name', 'content.name_bn', 'content.type', 'content.poster_url']);
        if ($titles->isEmpty()) throw new RuntimeException('Staging home banners require published owned titles with generated poster assets.');

        $rows = [];
        $owners = [];
        $now = now();
        $startsAt = $context->referenceDate->subDays(3)->startOfDay()->toDateTimeString();
        $endsAt = $context->referenceDate->addDays(30)->endOfDay()->toDateTimeString();
        for ($ordinal = 1; $ordinal <= $target; $ordinal++) {
            $key = sprintf('stg-%s-banner-%06d', $datasetPart, $ordinal);
            $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'banner')->where('entity_key', $key)->first(['record_id']);
            if ($owned) {
                if (! DB::table('banners')->where('id', $owned->record_id)->exists()) throw new RuntimeException("Owned staging banner {$key} is missing.");
                continue;
            }

            $title = $titles[($ordinal - 1) % $titles->count()];
            $bannerId = StagingIdentifiers::stableUuid($context->datasetId, 'banner:'.$ordinal);
            if (DB::table('banners')->where('id', $bannerId)->exists()) throw new RuntimeException("Staging banner {$key} exists without an ownership record.");
            $rows[] = [
                'id' => $bannerId, 'title' => (string) ($title->name_bn ?: $title->name),
                'file_url' => null, 'poster_url' => $title->poster_url, 'type' => (string) $title->type,
                'type_id' => (string) $title->id, 'type_name' => (string) $title->name,
                'description' => 'Staging homepage feature linked to published dataset-owned content.',
                'status' => true, 'banner_for' => 'home', 'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'click_url' => null, 'created_at' => $now, 'updated_at' => $now,
            ];
            $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'banner', 'entity_key' => $key,
                'record_id' => $bannerId, 'created_at' => $now, 'updated_at' => $now];
            if (count($rows) >= $context->profile->batchSize) {
                $this->persist($rows, $owners);
                $rows = [];
                $owners = [];
            }
        }
        if ($rows !== []) $this->persist($rows, $owners);

        $actual = (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'banner')->count();
        if ($actual !== $target) throw new RuntimeException("Staging banner generation produced {$actual} of {$target} configured banners.");
        return $actual;
    }

    /** @param list<array<string,mixed>> $rows
     *  @param list<array<string,mixed>> $owners
     */
    private function persist(array $rows, array $owners): void
    {
        DB::transaction(function () use ($rows, $owners): void {
            DB::table('banners')->insert($rows);
            DB::table('staging_dataset_records')->insert($owners);
        });
    }
}
