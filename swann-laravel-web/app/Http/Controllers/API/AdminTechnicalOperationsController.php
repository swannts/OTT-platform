<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Notifications\OperationsIncidentAlert;
use App\Services\AdminOperationsService;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminTechnicalOperationsController extends Controller
{
    public function health(AdminOperationsService $operations): JsonResponse
    {
        return response()->json(['data' => $operations->health()]);
    }

    public function playback(): JsonResponse
    {
        if (!Schema::hasTable('playback_diagnostic_events')) {
            return response()->json(['data' => ['window_hours' => 24, 'events' => []]]);
        }
        $events = DB::table('playback_diagnostic_events')
            ->select('event_code', DB::raw('count(*) as event_count'))
            ->where('occurred_at', '>=', now()->subHours(24))
            ->groupBy('event_code')->orderByDesc('event_count')->limit(30)->get();
        return response()->json(['data' => ['window_hours' => 24, 'events' => $events]]);
    }

    public function jobs(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('failed_jobs'), 503, 'Failed job storage is not configured.');
        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $jobs = DB::table('failed_jobs')->orderByDesc('failed_at')->paginate((int) ($validated['per_page'] ?? 25));
        $items = $jobs->getCollection()->map(function (object $job): array {
            $payload = json_decode((string) $job->payload, true);
            $name = data_get($payload, 'data.commandName');
            return [
                'id' => $job->uuid ?: (string) $job->id,
                'queue' => (string) $job->queue,
                'connection' => (string) $job->connection,
                'job_type' => is_string($name) ? class_basename($name) : 'Unknown job',
                'failed_at' => $job->failed_at,
                'retry_allowed' => in_array($name, $this->retryableJobs(), true),
            ];
        });

        return response()->json(['data' => $items, 'meta' => [
            'current_page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage(), 'per_page' => $jobs->perPage(), 'total' => $jobs->total(),
        ]]);
    }

    public function retryJob(string $job, Request $request, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(Schema::hasTable('failed_jobs'), 503, 'Failed job storage is not configured.');
        $record = DB::table('failed_jobs')->where('uuid', $job)->first();
        if ($record === null && ctype_digit($job)) $record = DB::table('failed_jobs')->where('id', (int) $job)->first();
        abort_if($record === null, 404);
        $payload = json_decode((string) $record->payload, true);
        $name = data_get($payload, 'data.commandName');
        abort_unless(is_string($name) && in_array($name, $this->retryableJobs(), true), 409, 'This job type is not approved for operations retry.');

        $id = $record->uuid ?: (string) $record->id;
        Artisan::call('queue:retry', ['id' => [$id]]);
        $audit->record($request->user(), 'operations.job.retry_requested', 'failed_job', $id, ['job_type' => class_basename($name)], ['status' => 'queued']);

        return response()->json(['data' => ['id' => $id, 'job_type' => class_basename($name), 'status' => 'queued']]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'acknowledged', 'resolved'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = DB::table('operations_incidents')->orderByDesc('created_at');
        if (isset($validated['status'])) $query->where('status', $validated['status']);
        $page = $query->paginate((int) ($validated['per_page'] ?? 25));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function createIncident(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'summary' => ['required', 'string', 'max:3000'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'affected_services' => ['sometimes', 'array', 'max:10'],
            'affected_services.*' => ['string', Rule::in(['api', 'database', 'redis', 'rabbitmq', 'go_worker', 'playback', 'media_processing', 'cdn', 'drm'])],
        ]);
        $incident = DB::transaction(function () use ($data, $request, $audit): object {
            $now = now('UTC');
            $id = DB::table('operations_incidents')->insertGetId([
                'title' => $data['title'], 'summary' => $data['summary'], 'severity' => $data['severity'], 'status' => 'open',
                'affected_services' => json_encode(array_values(array_unique($data['affected_services'] ?? []))),
                'opened_by' => $request->user()->id, 'assigned_to' => null, 'resolved_by' => null,
                'acknowledged_at' => null, 'resolved_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $audit->record($request->user(), 'operations.incident.created', 'operations_incident', $id, null, ['severity' => $data['severity'], 'status' => 'open']);
            return DB::table('operations_incidents')->find($id);
        });
        $this->notifyIncidentRoute($incident);

        return response()->json(['data' => $incident], 201);
    }

    public function updateIncident(int $incident, Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['acknowledged', 'resolved', 'open'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'summary' => ['sometimes', 'string', 'max:3000'],
        ]);
        $result = DB::transaction(function () use ($incident, $data, $request, $audit): array {
            $row = DB::table('operations_incidents')->where('id', $incident)->lockForUpdate()->first();
            abort_if($row === null, 404);
            $before = ['status' => $row->status, 'assigned_to' => $row->assigned_to];
            $update = array_intersect_key($data, array_flip(['status', 'assigned_to', 'summary']));
            if (isset($data['assigned_to'])) {
                $staff = \App\Models\User::query()->find($data['assigned_to']);
                abort_unless($staff && ExplicitPermission::allows($staff, 'admin.access'), 422, 'Incidents can only be assigned to active administrative accounts.');
            }
            if (($data['status'] ?? null) === 'acknowledged' && $row->status === 'resolved') {
                abort(409, 'Reopen a resolved incident before acknowledging it again.');
            }
            if (($data['status'] ?? null) === 'acknowledged' && $row->acknowledged_at === null) $update['acknowledged_at'] = now('UTC');
            if (($data['status'] ?? null) === 'resolved') {
                $update['resolved_at'] = now('UTC');
                $update['resolved_by'] = $request->user()->id;
            } elseif (($data['status'] ?? null) === 'open') {
                $update['resolved_at'] = null;
                $update['resolved_by'] = null;
            }
            $update['updated_at'] = now('UTC');
            DB::table('operations_incidents')->where('id', $incident)->update($update);
            $after = ['status' => $update['status'] ?? $row->status, 'assigned_to' => $update['assigned_to'] ?? $row->assigned_to];
            $audit->record($request->user(), 'operations.incident.updated', 'operations_incident', $incident, $before, $after);
            return [
                'incident' => DB::table('operations_incidents')->find($incident),
                'state_changed' => isset($data['status']) && $data['status'] !== $row->status,
            ];
        });
        if ($result['state_changed']) {
            $this->notifyIncidentRoute($result['incident']);
        }

        return response()->json(['data' => $result['incident']]);
    }

    private function notifyIncidentRoute(object $incident): void
    {
        $recipient = filter_var(config('operations.alerts.mail_to'), FILTER_VALIDATE_EMAIL);
        if ($recipient === false || $recipient === null) {
            return;
        }

        $severityOrder = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $minimum = config('operations.alerts.minimum_severity', 'high');
        if (! isset($severityOrder[$incident->severity], $severityOrder[$minimum])
            || $severityOrder[$incident->severity] < $severityOrder[$minimum]) {
            return;
        }

        try {
            Notification::route('mail', $recipient)->notify(new OperationsIncidentAlert(
                (int) $incident->id,
                (string) $incident->severity,
                (string) $incident->status,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Operations incident alert delivery failed.', ['exception' => $exception::class]);
        }
    }

    /** @return list<class-string> */
    private function retryableJobs(): array
    {
        // These jobs use idempotent delivery rows and independently recheck publication and consent.
        return [\App\Jobs\DispatchMarketingCampaign::class, \App\Jobs\SendMarketingCampaignDelivery::class];
    }
}
