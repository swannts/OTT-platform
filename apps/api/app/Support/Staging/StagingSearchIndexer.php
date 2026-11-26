<?php

namespace App\Support\Staging;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StagingSearchIndexer
{
    /** @return array{index_uid:string,documents:int,english_hits:int,bengali_hits:int,genre_hits:int} */
    public function sync(StagingGenerationContext $context): array
    {
        $prefix = (string) config('staging-data.search_index_prefix');
        if (preg_match('/^ott_staging_[a-z0-9_]{2,24}$/', $prefix) !== 1) {
            throw new RuntimeException('Refusing to sync a search index without the reserved staging prefix.');
        }
        $indexUid = $prefix.'_'.StagingIdentifiers::datasetPart($context->datasetId);
        $client = $this->client();
        $this->ensureIndex($client, $indexUid);

        $documents = $this->documents($context);
        foreach (array_chunk($documents, min($context->profile->batchSize, 1000)) as $batch) {
            $response = $client->post($this->indexPath($indexUid).'/documents', $batch)->throw();
            $this->waitForTask($client, $this->taskUid($response->json('taskUid')));
        }

        $settings = $client->patch($this->indexPath($indexUid).'/settings', [
            'searchableAttributes' => ['name', 'name_bn', 'description', 'description_bn'],
            'filterableAttributes' => ['dataset_id', 'is_published', 'content_type', 'genre_ids'],
            'sortableAttributes' => ['release_date'],
        ])->throw();
        $this->waitForTask($client, $this->taskUid($settings->json('taskUid')));

        $published = collect($documents)->first(static fn (array $document): bool => $document['is_published']);
        $probe = $published ?? ($documents[0] ?? null);
        if ($probe === null) {
            throw new RuntimeException('Staging search validation requires owned catalog documents.');
        }
        $baseFilter = sprintf('dataset_id = "%s" AND is_published = true AND content_type = "%s"', $context->datasetId, $probe['content_type']);
        $englishHits = $this->search($client, $indexUid, (string) $probe['name'], $baseFilter);
        $bengaliHits = $this->search($client, $indexUid, (string) $probe['name_bn'], $baseFilter);
        $genreId = $probe['genre_ids'][0] ?? null;
        if ($genreId === null) {
            throw new RuntimeException('Staging search validation requires at least one genre-mapped catalog title.');
        }
        $genreHits = $this->search($client, $indexUid, (string) $probe['name'], $baseFilter.' AND genre_ids = '.$genreId);
        foreach ([$englishHits, $bengaliHits, $genreHits] as $hits) {
            $foundProbe = in_array((string) $probe['content_id'], array_map(static fn (array $hit): string => (string) ($hit['content_id'] ?? ''), $hits), true);
            if ($published !== null && ! $foundProbe) {
                throw new RuntimeException('The isolated staging search index failed an English, Bengali, publication, type, or genre query check.');
            }
            if ($published === null && $hits !== []) {
                throw new RuntimeException('Draft catalog content leaked through the published staging search filter.');
            }
        }

        $this->registerDocumentOwnership($context, $documents);

        return [
            'index_uid' => $indexUid,
            'documents' => count($documents),
            'english_hits' => count($englishHits),
            'bengali_hits' => count($bengaliHits),
            'genre_hits' => count($genreHits),
        ];
    }

    private function client(): PendingRequest
    {
        $url = rtrim((string) config('staging-data.search_index_url'), '/');
        $request = Http::acceptJson()->asJson()->timeout(15);
        $key = (string) config('staging-data.search_api_key');
        if ($key !== '') {
            $request = $request->withToken($key);
        }

        return $request->baseUrl($url);
    }

    private function ensureIndex(PendingRequest $client, string $uid): void
    {
        $existing = $client->get($this->indexPath($uid));
        if ($existing->successful()) {
            $this->assertPrimaryKey($existing);

            return;
        }
        if ($existing->status() !== 404) {
            $existing->throw();
        }

        $response = $client->post('/indexes', ['uid' => $uid, 'primaryKey' => 'content_id']);
        if ($response->status() === 409) {
            $this->assertPrimaryKey($client->get($this->indexPath($uid))->throw());

            return;
        }
        $response->throw();
        try {
            $this->waitForTask($client, $this->taskUid($response->json('taskUid')));
        } catch (RuntimeException $exception) {
            $existing = $client->get($this->indexPath($uid));
            if (! $existing->successful()) {
                throw $exception;
            }
            $this->assertPrimaryKey($existing);
        }
    }

    private function assertPrimaryKey(Response $response): void
    {
        if (($response->json('primaryKey') ?? null) !== 'content_id') {
            throw new RuntimeException('The dataset-scoped staging search index exists with an incompatible primary key.');
        }
    }

    /** @return list<array<string,mixed>> */
    private function documents(StagingGenerationContext $context): array
    {
        $rows = DB::table('staging_dataset_records as owners')
            ->join('entertainments', 'entertainments.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'entertainment')
            ->whereNull('entertainments.deleted_at')->orderBy('entertainments.id')
            ->get(['entertainments.id', 'entertainments.name', 'entertainments.name_bn', 'entertainments.description', 'entertainments.description_bn', 'entertainments.type', 'entertainments.status', 'entertainments.release_date']);
        $contentIds = $rows->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $genres = DB::table('entertainment_gener_mapping')->whereIn('entertainment_id', $contentIds)
            ->orderBy('genre_id')->get(['entertainment_id', 'genre_id'])->groupBy('entertainment_id');

        return $rows->map(static function ($row) use ($context, $genres): array {
            $id = (string) $row->id;

            return [
                'content_id' => $id,
                'dataset_id' => $context->datasetId,
                'name' => (string) $row->name,
                'name_bn' => (string) ($row->name_bn ?? ''),
                'description' => (string) ($row->description ?? ''),
                'description_bn' => (string) ($row->description_bn ?? ''),
                'content_type' => (string) $row->type,
                'is_published' => (bool) $row->status,
                'release_date' => $row->release_date,
                'genre_ids' => ($genres->get($id) ?? collect())->pluck('genre_id')->map(static fn ($genreId): int => (int) $genreId)->values()->all(),
            ];
        })->all();
    }

    /** @param list<array<string,mixed>> $hits */
    private function search(PendingRequest $client, string $uid, string $query, string $filter): array
    {
        if (trim($query) === '') {
            throw new RuntimeException('Staging search validation requires non-empty English and Bengali titles.');
        }
        $response = $client->post($this->indexPath($uid).'/search', [
            'q' => $query, 'filter' => $filter, 'limit' => 20,
        ])->throw();
        $hits = $response->json('hits');
        if (! is_array($hits)) {
            throw new RuntimeException('The staging search engine returned an invalid hits payload.');
        }

        return $hits;
    }

    private function waitForTask(PendingRequest $client, int $taskUid): void
    {
        if ($taskUid < 0) {
            throw new RuntimeException('The search engine did not return a valid indexing task identifier.');
        }
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $task = $client->get('/tasks/'.$taskUid)->throw();
            $status = $task->json('status');
            if ($status === 'succeeded') {
                return;
            }
            if ($status === 'failed' || $status === 'canceled') {
                throw new RuntimeException('The isolated staging search-index task failed.');
            }
            usleep(100_000);
        }
        throw new RuntimeException('The isolated staging search-index task exceeded its wait limit.');
    }

    private function taskUid(mixed $taskUid): int
    {
        if (! is_int($taskUid) || $taskUid < 0) {
            throw new RuntimeException('The search engine did not return a valid indexing task identifier.');
        }

        return $taskUid;
    }

    /** @param list<array<string,mixed>> $documents */
    private function registerDocumentOwnership(StagingGenerationContext $context, array $documents): void
    {
        $now = now('UTC')->toDateTimeString();
        $keys = array_column($documents, 'content_id');
        foreach (array_chunk($keys, $context->profile->batchSize) as $chunk) {
            DB::transaction(function () use ($context, $chunk, $now): void {
                $existing = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'search_document')->whereIn('entity_key', $chunk)->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $rows = [];
                foreach ($chunk as $contentId) {
                    if ($existing->has($contentId)) {
                        if ((string) $existing->get($contentId)->record_id !== (string) $contentId) {
                            throw new RuntimeException('A staging search-document ownership record no longer matches its content.');
                        }

                        continue;
                    }
                    $rows[] = [
                        'dataset_id' => $context->datasetId, 'entity_type' => 'search_document',
                        'entity_key' => (string) $contentId, 'record_id' => (string) $contentId,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                if ($rows !== []) {
                    DB::table('staging_dataset_records')->insert($rows);
                }
            });
        }
        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'search_document')->count();
        if ($actual !== count($documents)) {
            throw new RuntimeException('Search index document ownership does not match the generated catalog.');
        }
    }

    private function indexPath(string $uid): string
    {
        return '/indexes/'.rawurlencode($uid);
    }
}
