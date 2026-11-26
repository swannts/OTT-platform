<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class StagingCastCrewGenerator
{
    private const FIRST_NAMES = [
        'Aariz', 'Amina', 'Anika', 'Arham', 'Ayesha', 'Daniyal', 'Farah', 'Faris', 'Hana', 'Hamza',
        'Ibrahim', 'Iman', 'Ishaq', 'Jamil', 'Khadija', 'Layla', 'Mahira', 'Mariam', 'Musa', 'Nadia',
        'Nashit', 'Nura', 'Omar', 'Rafi', 'Rahim', 'Rania', 'Rayyan', 'Ruqayya', 'Saad', 'Safiya',
        'Salma', 'Sami', 'Samira', 'Tariq', 'Yasmin', 'Yusuf', 'Zain', 'Zara', 'Zayd', 'Zoya',
    ];

    private const LAST_NAMES = [
        'Abbas', 'Adil', 'Ahmed', 'Akhtar', 'Ali', 'Ansari', 'Azad', 'Bashir', 'Chowdhury', 'Farooq',
        'Faruqi', 'Haque', 'Hasan', 'Hossain', 'Huq', 'Iqbal', 'Islam', 'Jabbar', 'Kamal', 'Karim',
        'Khan', 'Mahmud', 'Malik', 'Mannan', 'Miah', 'Mirza', 'Morshed', 'Nawaz', 'Qadir', 'Rahman',
        'Rashid', 'Reza', 'Siddiqui', 'Sikder', 'Uddin', 'Usman', 'Waheed', 'Yunus', 'Zaman', 'Zubair',
    ];
    private const MIDDLE_NAMES = [
        'Ahsan', 'Akram', 'Amin', 'Anwar', 'Arif', 'Asad', 'Azim', 'Bari', 'Basit', 'Fahim',
        'Faisal', 'Fakhr', 'Hadi', 'Hakim', 'Halim', 'Hamid', 'Harun', 'Ilyas', 'Imran', 'Irfan',
    ];

    /** @return array{cast_crew: int, mappings: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['cast_crew'];
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $talents = $this->seedTalents($context, $datasetPart, $target);
        $mappings = $this->seedMappings($context, $talents);

        return ['cast_crew' => count($talents['actors']) + count($talents['directors']), 'mappings' => $mappings];
    }

    /** @return array{actors: list<array{id: string, key: string}>, directors: list<array{id: string, key: string}>} */
    private function seedTalents(StagingGenerationContext $context, string $datasetPart, int $target): array
    {
        if ($target > count(self::FIRST_NAMES) * count(self::MIDDLE_NAMES) * count(self::LAST_NAMES)) {
            throw new RuntimeException('The staging cast and crew name catalog does not cover the configured target.');
        }

        $keys = [];
        for ($index = 0; $index < $target; $index++) {
            $keys[] = sprintf('stg-%s-talent-%05d', $datasetPart, $index + 1);
        }
        $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'cast_crew')->whereIn('entity_key', $keys)
            ->get(['entity_key', 'record_id'])->keyBy('entity_key');
        $existing = DB::table('cast_crew')->whereIn('id', $owned->pluck('record_id')->filter()->all())
            ->get(['id', 'name', 'type', 'status', 'deleted_at'])->keyBy('id');
        $names = array_map(fn (int $index): string => $this->nameForIndex($index), range(0, max(0, $target - 1)));
        $collisions = $target > 0
            ? array_fill_keys(DB::table('cast_crew')->whereIn('name', $names)->whereNull('deleted_at')->pluck('name')->all(), true)
            : [];

        $actors = [];
        $directors = [];
        $newRows = [];
        $newOwners = [];
        for ($index = 0; $index < $target; $index++) {
            $fixtureKey = $keys[$index];
            $type = $index < max(3, $target - (int) floor($target * 0.2)) ? 'actor' : 'director';
            $name = $names[$index];
            $owner = $owned->get($fixtureKey);
            $row = $owner?->record_id ? $existing->get($owner->record_id) : null;
            if ($row) {
                if ($row->name !== $name || $row->type !== $type || (int) $row->status !== 1 || $row->deleted_at !== null) {
                    throw new RuntimeException("Refusing to adopt or alter existing cast/crew fixture {$fixtureKey}.");
                }
                $talent = ['id' => (string) $row->id, 'key' => $fixtureKey];
            } else {
                if ($owner || isset($collisions[$name])) {
                    throw new RuntimeException("Cast/crew ownership for {$fixtureKey} is incomplete or collides with an unrelated person.");
                }
                $id = (string) Str::uuid7();
                $bornYear = 1950 + (($index * 7) % 48);
                $newRows[] = [
                    'id' => $id,
                    'name' => $name,
                    'type' => $type,
                    'file_url' => null,
                    'tmdb_id' => null,
                    'bio' => "Fictional staging {$type} profile used to exercise content credits. No real-person identity or external filmography is implied.",
                    'place_of_birth' => ['Dhaka', 'Chattogram', 'Sylhet', 'Cox\'s Bazar'][$index % 4].', Bangladesh',
                    'dob' => sprintf('%04d-%02d-%02d', $bornYear, 1 + ($index % 12), 1 + ($index % 27)),
                    'designation' => ucfirst($type),
                    'status' => 1,
                    'created_at' => $context->historyStart(),
                    'updated_at' => $context->historyStart(),
                ];
                $newOwners[] = [
                    'dataset_id' => $context->datasetId,
                    'entity_type' => 'cast_crew',
                    'entity_key' => $fixtureKey,
                    'record_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $talent = ['id' => $id, 'key' => $fixtureKey];
            }
            if ($type === 'actor') {
                $actors[] = $talent;
            } else {
                $directors[] = $talent;
            }
        }

        if ($target > 0 && (count($actors) < 3 || $directors === [])) {
            throw new RuntimeException('Staging content credits require at least three actors and one director.');
        }
        foreach (array_chunk($newRows, $context->profile->batchSize) as $chunkIndex => $rows) {
            $offset = $chunkIndex * $context->profile->batchSize;
            DB::transaction(function () use ($rows, $offset, $newOwners): void {
                DB::table('cast_crew')->insert($rows);
                DB::table('staging_dataset_records')->insert(array_slice($newOwners, $offset, count($rows)));
            });
        }

        return ['actors' => $actors, 'directors' => $directors];
    }

    private function nameForIndex(int $index): string
    {
        $first = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)];
        $middleIndex = intdiv($index, count(self::FIRST_NAMES));
        $middle = self::MIDDLE_NAMES[$middleIndex % count(self::MIDDLE_NAMES)];
        $lastIndex = intdiv($middleIndex, count(self::MIDDLE_NAMES));
        $last = self::LAST_NAMES[$lastIndex % count(self::LAST_NAMES)];

        return $first.' '.$middle.' '.$last;
    }

    /** @param array{actors: list<array{id: string, key: string}>, directors: list<array{id: string, key: string}>} $talents */
    private function seedMappings(StagingGenerationContext $context, array $talents): int
    {
        $content = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'entertainment')->orderBy('entity_key')->get(['entity_key', 'record_id']);
        if ($content->isEmpty()) {
            return 0;
        }
        if ($talents['actors'] === [] || $talents['directors'] === []) {
            throw new RuntimeException('Staging content exists but cast and director fixtures are missing.');
        }

        $mappingCount = 0;
        $offset = 0;
        foreach ($content->chunk(max(1, intdiv($context->profile->batchSize, 4))) as $items) {
            $links = [];
            foreach ($items->values() as $localIndex => $item) {
                $contentIndex = $offset + $localIndex;
                foreach ([0, 1, 2] as $actorOffset) {
                    $actorIndex = ($contentIndex * 3 + $actorOffset) % count($talents['actors']);
                    $actor = $talents['actors'][$actorIndex];
                    $links[] = [
                        'content_key' => (string) $item->entity_key,
                        'content_id' => (string) $item->record_id,
                        'talent_key' => $actor['key'],
                        'talent_id' => $actor['id'],
                    ];
                }
                $director = $talents['directors'][$contentIndex % count($talents['directors'])];
                $links[] = [
                    'content_key' => (string) $item->entity_key,
                    'content_id' => (string) $item->record_id,
                    'talent_key' => $director['key'],
                    'talent_id' => $director['id'],
                ];
            }

            DB::transaction(function () use ($context, $links, &$mappingCount): void {
                $keys = array_map(static fn (array $link): string => $link['content_key'].':'.$link['talent_key'], $links);
                $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'entertainment_talent_mapping')->whereIn('entity_key', $keys)
                    ->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $existing = DB::table('entertainment_talent_mapping')->whereIn('id', $owned->pluck('record_id')->filter()->all())
                    ->get(['id', 'entertainment_id', 'talent_id', 'deleted_at'])->keyBy('id');
                $existingPairs = DB::table('entertainment_talent_mapping')
                    ->whereIn('entertainment_id', array_values(array_unique(array_column($links, 'content_id'))))
                    ->whereIn('talent_id', array_values(array_unique(array_column($links, 'talent_id'))))
                    ->whereNull('deleted_at')->get(['entertainment_id', 'talent_id'])
                    ->mapWithKeys(static fn (object $mapping): array => [(string) $mapping->entertainment_id.':'.(string) $mapping->talent_id => true]);
                $rows = [];
                $owners = [];
                foreach ($links as $link) {
                    $key = $link['content_key'].':'.$link['talent_key'];
                    $owner = $owned->get($key);
                    $row = $owner?->record_id ? $existing->get($owner->record_id) : null;
                    if ($row) {
                        if ((string) $row->entertainment_id !== $link['content_id'] || (string) $row->talent_id !== $link['talent_id'] || $row->deleted_at !== null) {
                            throw new RuntimeException("Staging talent mapping {$key} no longer matches its content and person.");
                        }
                        $mappingCount++;
                        continue;
                    }
                    if ($owner || isset($existingPairs[$link['content_id'].':'.$link['talent_id']])) {
                        throw new RuntimeException("Staging talent mapping {$key} is incomplete or already belongs to another dataset.");
                    }
                    $mappingId = (string) Str::uuid7();
                    $rows[] = [
                        'id' => $mappingId,
                        'entertainment_id' => $link['content_id'],
                        'talent_id' => $link['talent_id'],
                        'created_at' => $context->historyStart(),
                        'updated_at' => $context->historyStart(),
                    ];
                    $owners[] = [
                        'dataset_id' => $context->datasetId,
                        'entity_type' => 'entertainment_talent_mapping',
                        'entity_key' => $key,
                        'record_id' => $mappingId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $mappingCount++;
                }
                if ($rows !== []) {
                    DB::table('entertainment_talent_mapping')->insert($rows);
                    DB::table('staging_dataset_records')->insert($owners);
                }
            });
            $offset += $items->count();
        }

        return $mappingCount;
    }
}
