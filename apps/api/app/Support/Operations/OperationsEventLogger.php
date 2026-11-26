<?php

namespace App\Support\Operations;

use App\Models\OperationsEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Stores allowlisted operational events without arbitrary messages or request data. */
final class OperationsEventLogger
{
    private const EVENTS = [
        'admin.invitation.delivery_failed' => ['service' => 'admin', 'severity' => 'warning', 'summary' => 'Administrative invitation delivery failed.'],
        'operations.incident.alert_failed' => ['service' => 'notifications', 'severity' => 'warning', 'summary' => 'Incident alert delivery failed.'],
        'backup.destination.failed' => ['service' => 'backup', 'severity' => 'warning', 'summary' => 'A backup destination could not be listed.'],
        'backup.create.failed' => ['service' => 'backup', 'severity' => 'error', 'summary' => 'Backup creation failed.'],
        'analytics.report.failed' => ['service' => 'analytics', 'severity' => 'error', 'summary' => 'Scheduled analytics report generation failed.'],
        'media.encryption.failed' => ['service' => 'media', 'severity' => 'error', 'summary' => 'Video encryption failed.'],
        'media.decryption.failed' => ['service' => 'media', 'severity' => 'warning', 'summary' => 'Video decryption failed.'],
    ];

    public function record(string $eventCode): void
    {
        $event = self::EVENTS[$eventCode] ?? null;
        if ($event === null) {
            Log::warning('An unknown operational event was rejected.', ['event_code' => 'unrecognized']);
            return;
        }

        try {
            $window = now('UTC')->startOfMinute();
            DB::table('operations_events')->insertOrIgnore([
                'event_code' => $eventCode,
                'service' => $event['service'],
                'severity' => $event['severity'],
                'summary' => $event['summary'],
                'window_started_at' => $window,
                'occurrence_count' => 0,
                'created_at' => now('UTC'),
            ]);
            DB::table('operations_events')->where('event_code', $eventCode)
                ->where('window_started_at', $window)->increment('occurrence_count');
        } catch (\Throwable $exception) {
            Log::warning('Sanitized operations event could not be persisted.', ['exception' => $exception::class]);
        }
    }
}
