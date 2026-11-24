<?php

namespace App\Services;

use Carbon\CarbonImmutable;
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

        $mediaProcessing = $this->mediaProcessingStatus();
        $checks['media_processing'] = ['status' => $mediaProcessing['status']];

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
        $alertRecipient = config('operations.alerts.mail_to');
        $minimumAlertSeverity = config('operations.alerts.minimum_severity', 'high');
        $allowedAlertSeverities = ['low', 'medium', 'high', 'critical'];
        $alerting = [
            'mail_configured' => is_string($alertRecipient) && filter_var($alertRecipient, FILTER_VALIDATE_EMAIL) !== false,
            'minimum_severity' => is_string($minimumAlertSeverity) && in_array($minimumAlertSeverity, $allowedAlertSeverities, true)
                ? $minimumAlertSeverity
                : 'invalid',
        ];
        $releaseId = config('app.release_id');
        $releaseId = is_string($releaseId) && preg_match('/^[A-Za-z0-9._+-]{1,80}$/', $releaseId) ? $releaseId : null;
        $releasedAt = config('app.released_at');
        if (is_string($releasedAt) && $releasedAt !== '') {
            try {
                $releasedAt = CarbonImmutable::parse($releasedAt)->utc()->toIso8601String();
            } catch (\Throwable) {
                $releasedAt = null;
            }
        } else {
            $releasedAt = null;
        }

        return [
            'status' => count(array_filter($statuses, static fn (string $status): bool => $status !== 'ok' && $status !== 'not_configured')) > 0 ? 'degraded' : 'ok',
            'checked_at' => now('UTC')->toIso8601String(),
            'deployment' => ['release_id' => $releaseId, 'released_at' => $releasedAt],
            'alerting' => $alerting,
            'services' => $checks,
            'runtime_resources' => app(RuntimeResourceMetrics::class)->snapshot(),
            'queue' => $queueCounts,
            'rabbitmq_queues' => $rabbitQueues,
            'backups' => $backupStatus,
            'media_processing' => $mediaProcessing,
        ];
    }

    /** @return array{status: string, queued: int, processing: int, failed: int, stale_processing: int} */
    private function mediaProcessingStatus(): array
    {
        if (!Schema::hasTable('media_processing_jobs')) {
            return ['status' => 'not_configured', 'queued' => 0, 'processing' => 0, 'failed' => 0, 'stale_processing' => 0];
        }

        $counts = DB::table('media_processing_jobs')
            ->select('status', DB::raw('count(*) as job_count'))
            ->groupBy('status')
            ->pluck('job_count', 'status');
        $queued = (int) ($counts['queued'] ?? 0);
        $processing = (int) ($counts['processing'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $staleProcessing = DB::table('media_processing_jobs')
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->count();

        return [
            'status' => $failed > 0 || $staleProcessing > 0 ? 'degraded' : 'ok',
            'queued' => $queued,
            'processing' => $processing,
            'failed' => $failed,
            'stale_processing' => $staleProcessing,
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
