<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminAnalyticsService
{
    public const GROUP_PERMISSIONS = [
        'audience' => 'analytics.audience.view',
        'subscriptions' => 'analytics.subscriptions.view',
        'content' => 'analytics.content.view',
        'revenue' => 'analytics.revenue.view',
        'playback' => 'analytics.playback.view',
    ];

    public function dashboard(CarbonImmutable $from, CarbonImmutable $to, array $permissions): array
    {
        $groups = array_values(array_filter(array_keys(self::GROUP_PERMISSIONS), fn (string $group): bool => in_array(self::GROUP_PERMISSIONS[$group], $permissions, true)));
        $key = 'admin.analytics.'.hash('sha256', json_encode([$from->toDateString(), $to->toDateString(), $groups]));
        return Cache::remember($key, now()->addMinute(), function () use ($from, $to, $groups): array {
            $data = ['range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()], 'metric_definitions' => $this->definitions()];
            foreach ($groups as $group) $data[$group] = $this->group($group, $from, $to);
            return $data;
        });
    }

    public function group(string $group, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($group) {
            'audience' => $this->audience($from, $to),
            'subscriptions' => $this->subscriptions($from, $to),
            'content' => $this->content($from, $to),
            'revenue' => $this->revenue($from, $to),
            'playback' => $this->playback($from, $to),
            default => abort(422, 'Unknown analytics report group.'),
        };
    }

    private function audience(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $activeViews = DB::table('entertainment_views')->select('user_id')->whereBetween('created_at', [$from, $to->endOfDay()])->whereNotNull('user_id');
        $activeIds = $activeViews->union(DB::table('playback_sessions')->select('user_id')->whereBetween('last_heartbeat_at', [$from, $to->endOfDay()])->whereNotNull('user_id'));
        $customerIds = $this->customerIdsQuery();
        $eligibleActive = DB::query()->fromSub($activeIds, 'activities')->joinSub($customerIds, 'customer_accounts', 'customer_accounts.id', '=', 'activities.user_id')->select('customer_accounts.id');
        $countryCounts = collect();
        if (Schema::hasColumn('users', 'country_code')) {
            $countryCounts = DB::table('users')->whereIn('users.id', $eligibleActive)
                ->selectRaw("COALESCE(NULLIF(UPPER(country_code), ''), 'UNKNOWN') as country, COUNT(DISTINCT users.id) as accounts")
                ->groupBy('country')->havingRaw('COUNT(*) >= 5')->orderByDesc('accounts')->limit(20)->get();
        }
        $searchTerms = Schema::hasTable('user_search_histories')
            ? DB::table('user_search_histories')->whereBetween('created_at', [$from, $to->endOfDay()])->whereNotNull('search_query')->where('search_query', 'not like', '%@%')->whereRaw("search_query NOT REGEXP '[0-9]'")->whereRaw('CHAR_LENGTH(TRIM(search_query)) BETWEEN 2 AND 60')
                ->selectRaw('LOWER(TRIM(search_query)) as term, COUNT(*) as searches')->groupBy('term')->havingRaw('COUNT(*) >= 5')->orderByDesc('searches')->limit(20)->get()
                ->map(fn ($row): array => ['term' => $row->term, 'searches' => (int) $row->searches])->all()
            : [];
        return [
            'new_accounts' => DB::table('users')->whereIn('id', $customerIds)->whereBetween('created_at', [$from, $to->endOfDay()])->count(),
            'active_accounts' => DB::query()->fromSub($eligibleActive, 'active_customer_accounts')->count(),
            'new_subscribers' => DB::table('subscriptions')->whereIn('user_id', $customerIds)->whereBetween('start_date', [$from, $to->endOfDay()])->whereNull('deleted_at')->where('is_trial', false)->distinct('user_id')->count('user_id'),
            'geography' => $countryCounts->map(fn ($row): array => ['country' => $row->country, 'accounts' => (int) $row->accounts])->values()->all(),
            'popular_search_terms' => $searchTerms,
        ];
    }

    private function subscriptions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $customerIds = $this->customerIdsQuery();
        $started = DB::table('subscriptions')->whereIn('user_id', $customerIds)->whereBetween('start_date', [$from, $to->endOfDay()])->whereNull('deleted_at');
        $churned = DB::table('subscriptions')->whereIn('user_id', $customerIds)->whereBetween('end_date', [$from, $to->endOfDay()])->whereNull('deleted_at')->where('is_trial', false)->whereIn('status', ['expired', 'cancelled', 'canceled']);
        $trials = DB::table('subscriptions as trial')->whereIn('trial.user_id', $customerIds)->whereBetween('trial.start_date', [$from, $to->endOfDay()])->whereNull('trial.deleted_at')->where('trial.is_trial', true);
        $converted = (clone $trials)->whereExists(function ($query): void {
            $query->selectRaw('1')->from('subscriptions as paid')->whereColumn('paid.user_id', 'trial.user_id')
                ->where('paid.is_trial', false)->whereColumn('paid.start_date', '>', 'trial.start_date')->whereNull('paid.deleted_at');
        })->count();
        $mrrByCurrency = [];
        if (Schema::hasTable('subscriptions_transactions') && Schema::hasColumn('subscriptions_transactions', 'currency_code')) {
            $latest = DB::table('subscriptions_transactions')->selectRaw('subscriptions_id, MAX(id) as transaction_id')->where('payment_status', 'paid')->whereNull('deleted_at')->groupBy('subscriptions_id');
            $rows = DB::table('subscriptions as s')->join('plan as p', 'p.id', '=', 's.plan_id')->leftJoinSub($latest, 'latest_payment', 'latest_payment.subscriptions_id', '=', 's.id')
                ->leftJoin('subscriptions_transactions as transaction', 'transaction.id', '=', 'latest_payment.transaction_id')
                ->whereIn('s.user_id', $customerIds)->where('s.status', 'active')->whereNull('s.deleted_at')->where(fn ($query) => $query->whereNull('s.end_date')->orWhere('s.end_date', '>=', now()))
                ->selectRaw("UPPER(COALESCE(NULLIF(transaction.currency_code, ''), 'UNKNOWN')) as currency, SUM(GREATEST(COALESCE(s.amount, 0), 0) * CASE LOWER(p.duration) WHEN 'day' THEN 30.4375 / GREATEST(p.duration_value, 1) WHEN 'days' THEN 30.4375 / GREATEST(p.duration_value, 1) WHEN 'week' THEN 4.348214 / GREATEST(p.duration_value, 1) WHEN 'weeks' THEN 4.348214 / GREATEST(p.duration_value, 1) WHEN 'year' THEN 1 / (12 * GREATEST(p.duration_value, 1)) WHEN 'years' THEN 1 / (12 * GREATEST(p.duration_value, 1)) ELSE 1 / GREATEST(p.duration_value, 1) END) as mrr")
                ->groupBy('currency')->get();
            foreach ($rows as $row) $mrrByCurrency[$row->currency] = (float) $row->mrr;
        }
        $conversion = $trials->count() > 0 ? round($converted / $trials->count() * 100, 2) : 0.0;
        return [
            'acquired_subscriptions' => (clone $started)->count(),
            'new_subscribers' => (clone $started)->where('is_trial', false)->distinct('user_id')->count('user_id'),
            'churned_subscriptions' => $churned->count(),
            'trial_starts' => (clone $trials)->count(),
            'trial_to_paid_conversions' => $converted,
            'trial_to_paid_percent' => $conversion,
            'active_subscriptions' => DB::table('subscriptions')->whereIn('user_id', $customerIds)->where('status', 'active')->whereNull('deleted_at')->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))->count(),
            'mrr_by_currency' => collect($mrrByCurrency)->map(fn ($value, $currency): array => ['currency' => $currency, 'mrr' => round($value, 2), 'arr' => round($value * 12, 2)])->values()->all(),
            'conversion_funnel' => ['trial_started' => (clone $trials)->count(), 'converted_to_paid' => $converted],
        ];
    }

    private function content(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $duration = "CASE WHEN watched_time REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(watched_time AS DECIMAL(12,2)) WHEN watched_time LIKE '%:%' THEN TIME_TO_SEC(watched_time) ELSE 0 END";
        $totalDuration = "CASE WHEN total_watched_time REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(total_watched_time AS DECIMAL(12,2)) WHEN total_watched_time LIKE '%:%' THEN TIME_TO_SEC(total_watched_time) ELSE 0 END";
        $watch = DB::table('continue_watch')->whereBetween('updated_at', [$from, $to->endOfDay()])->whereNull('deleted_at')
            ->selectRaw("SUM($duration) as watched_seconds, COUNT(*) as records, SUM(CASE WHEN $totalDuration > 0 AND $duration / $totalDuration >= 0.8 THEN 1 ELSE 0 END) as completed_records")->first();
        $popular = DB::table('entertainment_views as views')->join('entertainments as content', 'content.id', '=', 'views.entertainment_id')
            ->whereBetween('views.created_at', [$from, $to->endOfDay()])->whereNull('views.deleted_at')->whereNull('content.deleted_at')
            ->selectRaw('content.id, content.name, content.type, COUNT(*) as view_count')->groupBy('content.id', 'content.name', 'content.type')
            ->orderByDesc('view_count')->limit(10)->get()->map(fn ($row): array => ['content_id' => (int) $row->id, 'title' => $row->name, 'type' => $row->type, 'views' => (int) $row->view_count])->all();
        $sessions = DB::table('playback_sessions as sessions')
            ->join('entertainments as content', 'content.id', '=', 'sessions.content_id')
            ->join('media_assets as assets', 'assets.id', '=', 'content.media_asset_id')
            ->whereIn('sessions.user_id', $this->customerIdsQuery())
            ->whereNotNull('sessions.ended_at')->whereBetween('sessions.ended_at', [$from, $to->endOfDay()])
            ->whereNull('content.deleted_at')
            ->whereRaw('TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at) > 0')
            ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(assets.metadata, '$.Format.duration')) AS DECIMAL(12, 6)) > 0")
            ->whereRaw("TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at) <= CAST(JSON_UNQUOTE(JSON_EXTRACT(assets.metadata, '$.Format.duration')) AS DECIMAL(12, 6))");
        $sessionStats = (clone $sessions)->selectRaw("COUNT(*) as session_count, SUM(TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at)) as watched_seconds, SUM(CASE WHEN TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at) / NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(assets.metadata, '$.Format.duration')) AS DECIMAL(12, 6)), 0) >= 0.8 THEN 1 ELSE 0 END) as completed_sessions")->first();
        $sessionCount = (int) ($sessionStats->session_count ?? 0);
        $popularPlaybackContent = (clone $sessions)
            ->selectRaw('content.id, content.name, content.type, COUNT(*) as session_count')
            ->groupBy('content.id', 'content.name', 'content.type')->orderByDesc('session_count')->limit(10)->get()
            ->map(fn ($row): array => ['content_id' => (string) $row->id, 'title' => $row->name, 'type' => $row->type, 'sessions' => (int) $row->session_count])->all();
        $records = (int) ($watch->records ?? 0);
        return [
            'watch_hours' => round((float) ($watch->watched_seconds ?? 0) / 3600, 2),
            'playback_session_count' => $sessionCount,
            'session_watch_hours' => round((float) ($sessionStats->watched_seconds ?? 0) / 3600, 2),
            'session_completion_rate_percent' => $sessionCount > 0 ? round((int) $sessionStats->completed_sessions / $sessionCount * 100, 2) : null,
            'popular_playback_content' => $popularPlaybackContent,
            'continue_watch_records' => $records,
            'completion_rate_percent' => $records > 0 ? round((int) $watch->completed_records / $records * 100, 2) : 0.0,
            'audience_retention_percent' => $records > 0 ? round((int) $watch->completed_records / $records * 100, 2) : 0.0,
            'popular_content' => $popular,
        ];
    }

    private function revenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $transactions = collect();
        if (Schema::hasTable('subscriptions_transactions')) {
            $transactions = $transactions->merge(DB::table('subscriptions_transactions')->whereIn('user_id', $this->customerIdsQuery())->whereBetween('created_at', [$from, $to->endOfDay()])->where('payment_status', 'paid')->whereNull('deleted_at')
                ->selectRaw("DATE(created_at) as day, COALESCE(NULLIF(currency_code, ''), 'UNKNOWN') as currency, COUNT(*) as payments, SUM(amount) as gross_amount")->groupBy('day', 'currency')->get());
        }
        if (Schema::hasTable('payperviewstransactions')) {
            $transactions = $transactions->merge(DB::table('payperviewstransactions')->whereIn('user_id', $this->customerIdsQuery())->whereBetween('created_at', [$from, $to->endOfDay()])->where('payment_status', 'paid')
                ->selectRaw("DATE(created_at) as day, 'UNKNOWN' as currency, COUNT(*) as payments, SUM(amount) as gross_amount")->groupBy('day')->get());
        }
        $series = $transactions->groupBy(fn ($row) => $row->day.'|'.$row->currency)->map(fn ($rows, $key): array => [
            'day' => explode('|', $key)[0], 'currency' => explode('|', $key)[1], 'payments' => (int) $rows->sum('payments'), 'gross_amount' => round((float) $rows->sum('gross_amount'), 2),
        ])->sortBy('day')->values()->all();
        return ['payment_series' => $series, 'paid_payment_count' => (int) $transactions->sum('payments')];
    }

    private function playback(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = DB::table('playback_diagnostic_events')->whereIn('user_id', $this->customerIdsQuery())->whereBetween('occurred_at', [$from, $to->endOfDay()]);
        $counts = (clone $events)->selectRaw('event_code, COUNT(*) as occurrences')->groupBy('event_code')->orderByDesc('occurrences')->limit(30)->get()
            ->map(fn ($row): array => ['event_code' => $row->event_code, 'occurrences' => (int) $row->occurrences])->all();
        $distribution = Schema::hasColumn('playback_diagnostic_events', 'quality')
            ? (clone $events)->whereNotNull('quality')->selectRaw('quality, COUNT(*) as occurrences')->groupBy('quality')->orderByDesc('occurrences')->limit(20)->get()->map(fn ($row): array => ['quality' => $row->quality, 'occurrences' => (int) $row->occurrences])->all()
            : [];
        $platforms = DB::table('devices')->whereIn('user_id', $this->customerIdsQuery())->whereBetween('last_activity', [$from, $to->endOfDay()])->selectRaw("COALESCE(NULLIF(LOWER(platform), ''), 'unknown') as platform, COUNT(*) as devices, COUNT(DISTINCT user_id) as accounts")->groupBy('platform')->get()->map(fn ($row): array => ['platform' => $row->platform, 'devices' => (int) $row->devices, 'accounts' => (int) $row->accounts])->all();
        return [
            'startup_failures' => (clone $events)->whereIn('event_code', ['playback.startup_failed', 'playback.client_error.manifest_error', 'playback.client_error.license_error'])->count(),
            'rebuffer_events' => (clone $events)->where('event_code', 'playback.rebuffered')->count(),
            'average_startup_ms' => Schema::hasColumn('playback_diagnostic_events', 'duration_ms') ? (clone $events)->where('event_code', 'playback.started')->avg('duration_ms') : null,
            'event_counts' => $counts,
            'quality_distribution' => $distribution,
            'platform_distribution' => $platforms,
        ];
    }

    private function definitions(): array
    {
        return [
            'active_accounts' => 'Distinct registered accounts with a playback session or catalog view during the selected period.',
            'watch_hours' => 'Sum of continue-watch watched_time, parsed as seconds or HH:MM:SS, divided by 3,600.',
            'playback_session_count' => 'Ended customer playback sessions with a matching title and measured media duration in the selected period.',
            'session_watch_hours' => 'Sum of ended playback-session elapsed seconds divided by 3,600; sessions exceeding measured media duration are excluded.',
            'session_completion_rate_percent' => 'Ended playback sessions reaching at least 80 percent of the measured media duration.',
            'popular_playback_content' => 'Titles ranked by valid ended customer playback-session count; UUID content identifiers remain strings.',
            'completion_rate_percent' => 'Continue-watch records where watched_time / total_watched_time is at least 80 percent.',
            'audience_retention_percent' => 'Completion proxy using the same 80 percent continue-watch threshold; this is not second-by-second cohort retention.',
            'mrr_by_currency' => 'Active subscription amount normalized to one month using plan duration and the most recent paid transaction currency; unknown currencies stay separate.',
            'arr' => 'Monthly recurring revenue multiplied by 12, grouped by currency.',
            'trial_to_paid_percent' => 'Trial subscriptions started in the date range with a later non-trial subscription for the same account.',
            'geography' => 'Active accounts grouped by stored country code; groups below five accounts are omitted.',
            'popular_search_terms' => 'Search terms are lowercased, aggregated, and omitted below five searches; no account identifiers are included.',
            'playback_quality' => 'Uses standardized client diagnostic events; detailed startup timing and quality distributions require clients to report those events.',
        ];
    }

    private function customerIdsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('users')->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('model_has_roles')->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', \App\Models\User::class);
        })->select('users.id');
    }
}
