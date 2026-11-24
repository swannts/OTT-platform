<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminAnalyticsService;
use App\Support\Rbac\ExplicitPermission;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateScheduledAnalyticsReports extends Command
{
    protected $signature = 'analytics:generate-scheduled-reports';
    protected $description = 'Generate due permission-checked aggregate analytics reports.';

    public function handle(AdminAnalyticsService $analytics): int
    {
        DB::table('analytics_report_runs')->where('status', 'queued')->where('scheduled_at', '<=', now())->orderBy('id')->limit(100)->get()->each(function ($report) use ($analytics): void {
            DB::table('analytics_report_runs')->where('id', $report->id)->where('status', 'queued')->update(['status' => 'processing', 'updated_at' => now()]);
            $user = User::query()->find($report->requested_by);
            $permission = AdminAnalyticsService::GROUP_PERMISSIONS[$report->metric_group] ?? null;
            if (!$user || !$permission || !ExplicitPermission::allows($user, 'analytics.export') || !ExplicitPermission::allows($user, $permission)) {
                DB::table('analytics_report_runs')->where('id', $report->id)->update(['status' => 'failed', 'error_code' => 'permission_revoked', 'updated_at' => now()]);
                return;
            }
            try {
                $result = $analytics->group($report->metric_group, CarbonImmutable::parse($report->date_from), CarbonImmutable::parse($report->date_to));
                DB::table('analytics_report_runs')->where('id', $report->id)->update(['status' => 'ready', 'result' => json_encode($result), 'generated_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $exception) {
                Log::error('Scheduled analytics report generation failed.', [
                    'report_run_id' => $report->id,
                    'exception' => $exception::class,
                ]);
                DB::table('analytics_report_runs')->where('id', $report->id)->update(['status' => 'failed', 'error_code' => 'generation_failed', 'updated_at' => now()]);
            }
        });
        return self::SUCCESS;
    }
}
