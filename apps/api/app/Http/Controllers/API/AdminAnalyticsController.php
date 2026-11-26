<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAnalyticsService;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function dashboard(Request $request, AdminAnalyticsService $analytics): JsonResponse
    {
        $permissions = array_values(array_filter(AdminAnalyticsService::GROUP_PERMISSIONS, fn (string $permission): bool => ExplicitPermission::allows($request->user(), $permission)));
        abort_if(!$permissions, 403);
        [$from, $to] = $this->dateRange($request);
        return response()->json(['data' => $analytics->dashboard($from, $to, $permissions)]);
    }

    public function exportCsv(Request $request, AdminAnalyticsService $analytics, AdminAuditLogger $audit): StreamedResponse
    {
        $data = $request->validate(['group' => ['required', 'in:audience,subscriptions,content,revenue,playback'], 'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date']]);
        $group = $data['group'];
        abort_unless(ExplicitPermission::allows($request->user(), AdminAnalyticsService::GROUP_PERMISSIONS[$group]), 403);
        abort_unless(ExplicitPermission::allows($request->user(), 'analytics.export'), 403);
        [$from, $to] = $this->dateRange($request);
        $rows = $analytics->group($group, $from, $to);
        $audit->record($request->user(), 'analytics.exported', 'analytics_report', null, null, ['group' => $group, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['metric', 'value']);
            foreach ($rows as $metric => $value) {
                if (is_array($value)) {
                    foreach ($value as $index => $item) fputcsv($output, [$this->csvCell((string) $metric.'.'.$index), $this->csvCell(is_array($item) ? json_encode($item) : (string) $item)]);
                } else fputcsv($output, [$this->csvCell((string) $metric), $this->csvCell((string) $value)]);
            }
            fclose($output);
        }, 'ott-'.$group.'-analytics-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'analytics.export'), 403);
        $page = DB::table('analytics_report_runs')->where('requested_by', $request->user()->id)->orderByDesc('id')->paginate(25,
            ['id', 'metric_group', 'date_from', 'date_to', 'scheduled_at', 'status', 'error_code', 'generated_at', 'created_at']);
        return response()->json($page);
    }

    public function scheduleReport(Request $request, AdminAnalyticsService $analytics, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'analytics.export'), 403);
        $data = $request->validate([
            'group' => ['required', 'in:audience,subscriptions,content,revenue,playback'],
            'from' => ['required', 'date'], 'to' => ['required', 'date'], 'scheduled_at' => ['required', 'date', 'after:now'],
        ]);
        $permission = AdminAnalyticsService::GROUP_PERMISSIONS[$data['group']];
        abort_unless(ExplicitPermission::allows($request->user(), $permission), 403);
        [$from, $to] = $this->dateRange($request, $data['from'], $data['to']);
        $id = DB::table('analytics_report_runs')->insertGetId([
            'requested_by' => $request->user()->id, 'metric_group' => $data['group'], 'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(), 'scheduled_at' => CarbonImmutable::parse($data['scheduled_at']), 'status' => 'queued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $audit->record($request->user(), 'analytics.report.scheduled', 'analytics_report', $id, null, ['group' => $data['group'], 'scheduled_at' => $data['scheduled_at']]);
        return response()->json(['data' => DB::table('analytics_report_runs')->find($id)], 201);
    }

    public function report(Request $request, int $report): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'analytics.export'), 403);
        $item = DB::table('analytics_report_runs')->where('id', $report)->where('requested_by', $request->user()->id)->first();
        abort_if(!$item, 404);
        abort_unless(ExplicitPermission::allows($request->user(), AdminAnalyticsService::GROUP_PERMISSIONS[$item->metric_group] ?? ''), 403);
        return response()->json(['data' => $item]);
    }

    private function dateRange(Request $request, ?string $from = null, ?string $to = null): array
    {
        $input = $request->validate(['from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date']]);
        $start = CarbonImmutable::parse($from ?? $input['from'] ?? now()->subDays(29))->startOfDay();
        $end = CarbonImmutable::parse($to ?? $input['to'] ?? now())->startOfDay();
        abort_if($start->greaterThan($end) || $start->diffInDays($end) > 365 || $end->greaterThan(now()->endOfDay()), 422, 'Choose a date range of at most 366 days ending today or earlier.');
        return [$start, $end];
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]/', $value) ? "'".$value : $value;
    }
}
