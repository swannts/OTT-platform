<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class AdminOperationsService
{
    /** @return array<string, mixed> */
    public function health(): array
    {
        $checks = ['application' => ['status' => 'ok']];
        try {
            DB::select('select 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable) {
            $checks['database'] = ['status' => 'unavailable'];
        }

        try {
            $pong = Redis::connection()->ping();
            $checks['redis'] = ['status' => str_contains(strtoupper((string) $pong), 'PONG') ? 'ok' : 'degraded'];
        } catch (\Throwable) {
            $checks['redis'] = ['status' => 'unavailable'];
        }

        $checks['rabbitmq'] = ['status' => $this->tcpAvailable(
            (string) config('services.rabbitmq_host'),
            (int) config('services.rabbitmq_port'),
        ) ? 'ok' : 'unavailable'];

        $rabbitQueues = $this->rabbitQueues();
        $checks['rabbitmq_management'] = ['status' => $rabbitQueues === null ? 'unavailable' : 'ok'];

        $backupStatus = $this->backupStatus();
        $checks['backups'] = ['status' => $backupStatus['status']];

        $workerUrl = (string) config('services.go_worker_health_url', 'http://worker:8081/health');
        try {
            $worker = Http::timeout(2)->acceptJson()->get($workerUrl);
            $workerData = $worker->json();
            $checks['go_worker'] = [
                'status' => $worker->successful() && data_get($workerData, 'status') === 'ok' ? 'ok' : 'degraded',
                'checks' => is_array(data_get($workerData, 'checks')) ? data_get($workerData, 'checks') : [],
            ];
        } catch (\Throwable) {
            $checks['go_worker'] = ['status' => 'unavailable'];
        }

        $queueConnection = (string) config('queue.default');
        $queueCounts = ['connection' => $queueConnection, 'queued' => null, 'reserved' => null, 'failed' => null];
        if ($queueConnection === 'database' && Schema::hasTable('jobs')) {
            $queueCounts['queued'] = DB::table('jobs')->whereNull('reserved_at')->count();
            $queueCounts['reserved'] = DB::table('jobs')->whereNotNull('reserved_at')->count();
        }
        if (Schema::hasTable('failed_jobs')) {
            $queueCounts['failed'] = DB::table('failed_jobs')->count();
        }

        $statuses = array_column($checks, 'status');
        return [
            'status' => count(array_filter($statuses, static fn (string $status): bool => $status !== 'ok' && $status !== 'not_configured')) > 0 ? 'degraded' : 'ok',
            'checked_at' => now('UTC')->toIso8601String(),
            'services' => $checks,
            'queue' => $queueCounts,
            'rabbitmq_queues' => $rabbitQueues,
            'backups' => $backupStatus,
        ];
    }

    /** @return array{status: string, disks: list<array{status: string, backup_count: int, latest_at: ?string, bytes: int}>} */
    private function backupStatus(): array
    {
        $diskNames = config('backup.backup.destination.disks', []);
        if (!is_array($diskNames) || $diskNames === []) return ['status' => 'not_configured', 'disks' => []];
        $directory = (string) config('backup.backup.name');
        $disks = [];
        foreach ($diskNames as $diskName) {
            try {
                $disk = Storage::disk((string) $diskName);
                $archives = collect($disk->files($directory))->filter(static fn (string $file): bool => str_ends_with(strtolower($file), '.zip'));
                $latest = null;
                $bytes = 0;
                foreach ($archives as $archive) {
                    $modified = $disk->lastModified($archive);
                    $latest = max($latest ?? 0, $modified);
                    $bytes += $disk->size($archive);
                }
                $fresh = $latest !== null && $latest >= now()->subDay()->timestamp;
                $disks[] = ['status' => $fresh ? 'ok' : 'stale', 'backup_count' => $archives->count(), 'latest_at' => $latest ? now()->setTimestamp($latest)->toIso8601String() : null, 'bytes' => $bytes];
            } catch (\Throwable) {
                $disks[] = ['status' => 'unavailable', 'backup_count' => 0, 'latest_at' => null, 'bytes' => 0];
            }
        }
        $status = collect($disks)->contains(static fn (array $disk): bool => $disk['status'] !== 'ok') ? 'degraded' : 'ok';
        return ['status' => $status, 'disks' => $disks];
    }

    /** @return list<array{name: string, messages: int, ready: int, unacknowledged: int, consumers: int}>|null */
    private function rabbitQueues(): ?array
    {
        try {
            $url = rtrim((string) config('services.rabbitmq_management_url'), '/').'/queues/%2F';
            $response = Http::timeout(2)->withBasicAuth(
                (string) config('services.rabbitmq_username'),
                (string) config('services.rabbitmq_password'),
            )->acceptJson()->get($url);
            if (!$response->successful() || !is_array($response->json())) return null;

            $allowlist = ['ott.media.processing', 'ott.notifications', 'ott.data.import', 'ott.cron.maintenance', 'ott.dead-letter'];
            return collect($response->json())->filter(static fn (array $queue): bool => in_array($queue['name'] ?? null, $allowlist, true))
                ->map(static fn (array $queue): array => [
                    'name' => (string) $queue['name'], 'messages' => (int) ($queue['messages'] ?? 0),
                    'ready' => (int) ($queue['messages_ready'] ?? 0), 'unacknowledged' => (int) ($queue['messages_unacknowledged'] ?? 0),
                    'consumers' => (int) ($queue['consumers'] ?? 0),
                ])->values()->all();
        } catch (\Throwable) {
            return null;
        }
    }

    private function tcpAvailable(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 1.0);
        if ($socket === false) return false;
        fclose($socket);
        return true;
    }
}
