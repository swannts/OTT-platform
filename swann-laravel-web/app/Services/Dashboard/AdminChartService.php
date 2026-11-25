<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\EntertainmentView;
use Modules\Entertainment\Models\Review;
use Modules\Frontend\Models\PayperviewTransaction;
use Modules\Genres\Models\Genres;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionTransactions;

class AdminChartService
{
    public function getLocalizedPlanName(?string $planName, ?string $planIdentifier = null): string
    {
        $planTranslations = [
            'basic' => __('plan.basic'),
            'premium_plan' => __('plan.premium_plan'),
            'premium' => __('plan.premium_plan'),
            'ultimate_plan' => __('plan.ultimate_plan'),
            'ultimate' => __('plan.ultimate_plan'),
            'elite_plan' => __('plan.elite_plan'),
            'elite' => __('plan.elite_plan'),
        ];

        if ($planIdentifier && isset($planTranslations[strtolower($planIdentifier)])) {
            return $planTranslations[strtolower($planIdentifier)];
        }

        $lowerName = strtolower((string) $planName);
        foreach ($planTranslations as $key => $translation) {
            if (strpos($lowerName, $key) !== false) {
                return $translation;
            }
        }

        return (string) $planName;
    }

    /**
     * @return array{chartData: array<int, float|int>, category: array<int, string>}
     */
    public function getRevenueChartData(string $type, ?string $dateRange = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        $chartData = [];
        $category = [];

        if ($type == 'Year') {
            $monthlyTotalsQuery = SubscriptionTransactions::selectRaw('YEAR(created_at) as year')
                ->selectRaw('MONTH(created_at) as month')
                ->selectRaw('SUM(amount) as total_amount')
                ->where('payment_status', 'paid')
                ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                ->orderByRaw('YEAR(created_at), MONTH(created_at)');
            if ($startDate && $endDate) {
                $monthlyTotalsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $monthlyTotals = $monthlyTotalsQuery->get();

            $monthlyPPVQuery = PayperviewTransaction::selectRaw('YEAR(created_at) as year')
                ->selectRaw('MONTH(created_at) as month')
                ->selectRaw('SUM(amount) as amount')
                ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                ->orderByRaw('YEAR(created_at), MONTH(created_at)');
            if ($startDate && $endDate) {
                $monthlyPPVQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $monthlyPayPerViewTotals = $monthlyPPVQuery->get();

            for ($month = 1; $month <= 12; $month++) {
                $subscriptionAmount = 0;
                $payPerViewAmount = 0;

                foreach ($monthlyTotals as $total) {
                    if ((int) $total->month === $month) {
                        $subscriptionAmount = (float) $total->total_amount;
                        break;
                    }
                }

                foreach ($monthlyPayPerViewTotals as $total) {
                    if ((int) $total->month === $month) {
                        $payPerViewAmount = (float) $total->amount;
                        break;
                    }
                }

                $chartData[] = $subscriptionAmount + $payPerViewAmount;
            }

            for ($month = 1; $month <= 12; $month++) {
                $category[] = Carbon::create()->month($month)->locale(app()->getLocale())->translatedFormat('M');
            }
        } elseif ($type == 'Month') {
            $refDate = ($endDate ?? Carbon::now());
            $startOfMonth = $refDate->copy()->startOfMonth();
            $endOfMonth = $refDate->copy()->endOfMonth();

            $dailyTotals = SubscriptionTransactions::selectRaw('DAY(created_at) as day, COALESCE(SUM(amount), 0) as total_amount')
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $dailyPayPerViewTotals = PayperviewTransaction::selectRaw('DAY(created_at) as day, COALESCE(SUM(amount), 0) as amount')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $subByDay = [];
            foreach ($dailyTotals as $total) {
                $subByDay[(int) $total->day] = (float) $total->total_amount;
            }
            $ppvByDay = [];
            foreach ($dailyPayPerViewTotals as $total) {
                $ppvByDay[(int) $total->day] = (float) $total->amount;
            }

            $weekStart = $startOfMonth->copy();
            $weekIndex = 1;

            while ($weekStart <= $endOfMonth) {
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

                $inMonthStart = $weekStart->copy()->max($startOfMonth);
                $inMonthEnd = $weekEnd->copy()->min($endOfMonth);

                $weekTotal = 0;
                $dayCursor = $inMonthStart->copy();
                while ($dayCursor <= $inMonthEnd) {
                    $dayNum = (int) $dayCursor->format('j');
                    $weekTotal += ($subByDay[$dayNum] ?? 0) + ($ppvByDay[$dayNum] ?? 0);
                    $dayCursor->addDay();
                }

                $chartData[] = $weekTotal;
                $category[] = __('dashboard.week').' '.$weekIndex;
                $weekStart = $weekEnd->copy()->addDay();
                $weekIndex++;
            }
        } elseif ($type == 'Week') {
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            $subQuery = SubscriptionTransactions::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$weekStart, $weekEnd]);
            if ($startDate && $endDate) {
                $subQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $subByDate = $subQuery->get()
                ->groupBy(fn ($r) => Carbon::parse($r->created_at)->format('Y-m-d'))
                ->map(fn ($rows) => (float) $rows->sum('amount'));

            $ppvQuery = PayperviewTransaction::query()
                ->whereBetween('created_at', [$weekStart, $weekEnd]);
            if ($startDate && $endDate) {
                $ppvQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $ppvByDate = $ppvQuery->get()
                ->groupBy(fn ($r) => Carbon::parse($r->created_at)->format('Y-m-d'))
                ->map(fn ($rows) => (float) $rows->sum('amount'));

            $dayCursor = $weekStart->copy();

            for ($i = 0; $i < 7; $i++) {
                $dateKey = $dayCursor->format('Y-m-d');
                $total = ($subByDate->get($dateKey) ?? 0) + ($ppvByDate->get($dateKey) ?? 0);
                $chartData[] = $total;
                $category[] = $dayCursor->locale(app()->getLocale())->translatedFormat('l');
                $dayCursor->addDay();
            }
        }

        return [
            'chartData' => $chartData,
            'category' => $category,
        ];
    }

    /**
     * @return array{chartData: array<int, array<string, mixed>>, category: array<int, string>}
     */
    public function getSubscriberChartData(string $type, ?string $dateRange = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $plans = Plan::all()->keyBy('id');

        $chartData = [];
        $category = [];

        if ($type == 'Year') {
            $monthlyTotalsQuery = Subscription::selectRaw('YEAR(start_date) as year, MONTH(start_date) as month, plan_id')
                ->selectRaw('COUNT(*) as total_subscribers')
                ->groupByRaw('YEAR(start_date), MONTH(start_date), plan_id')
                ->orderByRaw('YEAR(start_date), MONTH(start_date), plan_id');
            if ($startDate && $endDate) {
                $monthlyTotalsQuery->whereBetween('start_date', [$startDate, $endDate]);
            }
            $monthlyTotals = $monthlyTotalsQuery->get()->groupBy('plan_id');

            foreach ($plans as $planId => $plan) {
                $planData = [];
                $planName = $this->getLocalizedPlanName($plan->name, $plan->identifier);

                for ($month = 1; $month <= 12; $month++) {
                    $found = false;
                    if (isset($monthlyTotals[$planId])) {
                        foreach ($monthlyTotals[$planId] as $total) {
                            if ((int) $total->month === $month) {
                                $planData[] = $total->total_subscribers;
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (! $found) {
                        $planData[] = 0;
                    }
                }

                $chartData[] = [
                    'name' => $planName,
                    'data' => $planData,
                ];
            }

            for ($month = 1; $month <= 12; $month++) {
                $category[] = Carbon::create()->month($month)->locale(app()->getLocale())->translatedFormat('M');
            }
        } elseif ($type == 'Month') {
            $endOfMonth = Carbon::now()->endOfMonth();

            $monthlyDayTotalsQuery = Subscription::selectRaw('DAY(start_date) as day, plan_id')
                ->selectRaw('COUNT(*) as total_subscribers')
                ->whereYear('start_date', $currentYear)
                ->whereMonth('start_date', $currentMonth)
                ->groupBy('day', 'plan_id')
                ->orderBy('day');
            if ($startDate && $endDate) {
                $monthlyDayTotalsQuery->whereBetween('start_date', [$startDate, $endDate]);
            }
            $monthlyDayTotals = $monthlyDayTotalsQuery->get()->groupBy('plan_id');

            $weeksInMonth = ceil($endOfMonth->day / 7);

            foreach ($plans as $planId => $plan) {
                $planData = [];
                $planName = $this->getLocalizedPlanName($plan->name, $plan->identifier);

                for ($week = 1; $week <= $weeksInMonth; $week++) {
                    $weekTotal = 0;
                    $found = false;

                    for ($day = ($week - 1) * 7 + 1; $day <= min($week * 7, $endOfMonth->day); $day++) {
                        if (isset($monthlyDayTotals[$planId])) {
                            foreach ($monthlyDayTotals[$planId] as $total) {
                                if ((int) $total->day === $day) {
                                    $weekTotal += $total->total_subscribers;
                                    $found = true;
                                }
                            }
                        }
                    }

                    $planData[] = $found ? $weekTotal : 0;
                }

                $chartData[] = [
                    'name' => $planName,
                    'data' => $planData,
                ];
            }

            for ($i = 1; $i <= $weeksInMonth; $i++) {
                $category[] = __('dashboard.week').' '.$i;
            }
        } elseif ($type == 'Week') {
            $currentWeekStartDate = Carbon::now()->startOfWeek();
            $lastDayOfWeek = Carbon::now()->endOfWeek();

            $weeklyDayTotalsQuery = Subscription::selectRaw('DAY(start_date) as day, plan_id')
                ->selectRaw('COUNT(*) as total_subscribers')
                ->whereYear('start_date', $currentYear)
                ->whereMonth('start_date', $currentMonth)
                ->whereBetween('start_date', [$currentWeekStartDate, $lastDayOfWeek])
                ->groupBy('day', 'plan_id')
                ->orderBy('day');
            if ($startDate && $endDate) {
                $weeklyDayTotalsQuery->whereBetween('start_date', [$startDate, $endDate]);
            }
            $weeklyDayTotals = $weeklyDayTotalsQuery->get();

            foreach ($plans as $planId => $plan) {
                $planData = [];
                $planName = $this->getLocalizedPlanName($plan->name, $plan->identifier);

                for ($day = clone $currentWeekStartDate; $day <= $lastDayOfWeek; $day->addDay()) {
                    $found = false;

                    foreach ($weeklyDayTotals as $total) {
                        if ((int) $total->day === $day->day && $total->plan_id == $planId) {
                            $planData[] = $total->total_subscribers;
                            $found = true;
                            break;
                        }
                    }

                    if (! $found) {
                        $planData[] = 0;
                    }
                }

                $chartData[] = [
                    'name' => $planName,
                    'data' => $planData,
                ];
            }

            for ($day = 0; $day < 7; $day++) {
                $category[] = Carbon::now()->startOfWeek()->addDays($day)->locale(app()->getLocale())->translatedFormat('l');
            }
        }

        return [
            'chartData' => $chartData,
            'category' => $category,
        ];
    }

    /**
     * @return array{chartData: array<int, int>, category: array<int, string>}
     */
    public function getGenreChartData(?string $dateRange = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        $genreData = Genres::withCount(['entertainmentGenerMappings' => function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }
        }])
            ->orderBy('entertainment_gener_mappings_count', 'desc')
            ->limit(5)
            ->get();

        $genreNames = [];
        $entertainmentCounts = [];

        foreach ($genreData as $genre) {
            $genreNames[] = $genre->name;
            $entertainmentCounts[] = $genre->entertainment_gener_mappings_count;
        }

        return [
            'chartData' => $entertainmentCounts,
            'category' => $genreNames,
        ];
    }

    /**
     * @return array{chartData: array<int, array<string, mixed>>, category: array<int, string>}
     */
    public function getMostWatchChartData(string $type, ?string $dateRange = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $entertainmentTypes = [];

        if (isenablemodule('movie') == 1) {
            $entertainmentTypes['movie'] = __('messages.lbl_movies');
        }

        if (isenablemodule('tvshow') == 1) {
            $entertainmentTypes['tvshow'] = __('messages.lbl_tvshows');
        }

        $chartData = [];
        $category = [];

        if ($type == 'Year') {
            $monthlyTotalsQuery = EntertainmentView::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, entertainment_id')
                ->selectRaw('COUNT(*) as total_views')
                ->whereYear('created_at', $currentYear)
                ->groupByRaw('YEAR(created_at), MONTH(created_at), entertainment_id')
                ->orderByRaw('YEAR(created_at), MONTH(created_at), entertainment_id');
            if ($startDate && $endDate) {
                $monthlyTotalsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $monthlyTotals = $monthlyTotalsQuery->get()->groupBy('entertainment_id');
            $entertainments = Entertainment::whereIn('id', $monthlyTotals->keys())->get()->keyBy('id');

            foreach ($entertainmentTypes as $entertainmentType => $typeName) {
                $typeData = [];

                for ($month = 1; $month <= 12; $month++) {
                    $totalViews = 0;
                    foreach ($monthlyTotals as $entertainmentId => $totals) {
                        $entertainment = $entertainments->get($entertainmentId);
                        if ($entertainment && $entertainment->type === $entertainmentType) {
                            foreach ($totals as $total) {
                                if ((int) $total->month === $month) {
                                    $totalViews += $total->total_views;
                                }
                            }
                        }
                    }
                    $typeData[] = $totalViews;
                }

                $chartData[] = [
                    'name' => $typeName,
                    'data' => $typeData,
                ];
            }

            for ($month = 1; $month <= 12; $month++) {
                $category[] = Carbon::create()->month($month)->locale(app()->getLocale())->translatedFormat('M');
            }
        } elseif ($type == 'Month') {
            $firstWeek = Carbon::now()->startOfMonth()->week;
            $lastWeek = Carbon::now()->endOfMonth()->week;

            $monthlyWeekTotalsQuery = EntertainmentView::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, WEEK(created_at, 1) as week, entertainment_id')
                ->selectRaw('COUNT(*) as total_views')
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->groupBy('year', 'month', 'week', 'entertainment_id')
                ->orderBy('year')
                ->orderBy('month')
                ->orderBy('week');
            if ($startDate && $endDate) {
                $monthlyWeekTotalsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $monthlyWeekTotals = $monthlyWeekTotalsQuery->get()->groupBy('entertainment_id');
            $entertainments = Entertainment::whereIn('id', $monthlyWeekTotals->keys())->get()->keyBy('id');

            foreach ($entertainmentTypes as $entertainmentType => $typeName) {
                $typeData = [];

                for ($week = $firstWeek; $week <= $lastWeek; $week++) {
                    $totalViews = 0;
                    foreach ($monthlyWeekTotals as $entertainmentId => $totals) {
                        $entertainment = $entertainments->get($entertainmentId);
                        if ($entertainment && $entertainment->type === $entertainmentType) {
                            foreach ($totals as $total) {
                                if ((int) $total->week === $week) {
                                    $totalViews += $total->total_views;
                                }
                            }
                        }
                    }
                    $typeData[] = $totalViews;
                }

                $chartData[] = [
                    'name' => $typeName,
                    'data' => $typeData,
                ];
            }

            for ($week = $firstWeek; $week <= $lastWeek; $week++) {
                $category[] = __('dashboard.week').' '.($week - $firstWeek + 1);
            }
        } elseif ($type == 'Week') {
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();

            $weeklyDayTotalsQuery = EntertainmentView::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, DAY(created_at) as day, entertainment_id')
                ->selectRaw('COUNT(*) as total_views')
                ->groupBy('year', 'month', 'day', 'entertainment_id')
                ->orderBy('year')
                ->orderBy('month')
                ->orderBy('day');
            if ($startDate && $endDate) {
                $weeklyDayTotalsQuery->whereBetween('created_at', [$startDate, $endDate]);
            } else {
                $weeklyDayTotalsQuery->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
            }
            $weeklyDayTotals = $weeklyDayTotalsQuery->get()->groupBy('entertainment_id');
            $entertainments = Entertainment::whereIn('id', $weeklyDayTotals->keys())->get()->keyBy('id');

            foreach ($entertainmentTypes as $entertainmentType => $typeName) {
                $typeData = [];

                for ($day = 0; $day < 7; $day++) {
                    $date = Carbon::now()->startOfWeek()->addDays($day);
                    $totalViews = 0;
                    foreach ($weeklyDayTotals as $entertainmentId => $totals) {
                        $entertainment = $entertainments->get($entertainmentId);
                        if ($entertainment && $entertainment->type === $entertainmentType) {
                            foreach ($totals as $total) {
                                if ($total->day == $date->day) {
                                    $totalViews += $total->total_views;
                                }
                            }
                        }
                    }
                    $typeData[] = $totalViews;
                }

                $chartData[] = [
                    'name' => $typeName,
                    'data' => $typeData,
                ];
            }

            for ($day = 0; $day < 7; $day++) {
                $category[] = Carbon::now()->startOfWeek()->addDays($day)->locale(app()->getLocale())->translatedFormat('l');
            }
        }

        return [
            'chartData' => $chartData,
            'category' => $category,
        ];
    }

    /**
     * @return array{chartData: array<int, array<string, mixed>>, category: array<int, string>}
     */
    public function getTopRatedChartData(?string $dateRange = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        $topRatedQuery = Review::select('entertainment_id', DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('entertainment_id')
            ->orderBy('avg_rating', 'desc');
        if ($startDate && $endDate) {
            $topRatedQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $topRatedData = $topRatedQuery->get();

        $entertainmentData = Entertainment::whereIn('id', $topRatedData->pluck('entertainment_id'))
            ->get()
            ->keyBy('id');

        $movieCount = 0;
        $tvShowCount = 0;

        foreach ($topRatedData as $data) {
            $entertainment = $entertainmentData->get($data->entertainment_id);
            if ($entertainment) {
                if ($entertainment->type == 'movie') {
                    $movieCount++;
                } elseif ($entertainment->type == 'tvshow') {
                    $tvShowCount++;
                }
            }
        }

        $chartData = [];
        $category = [];

        if (isenablemodule('movie') == 1) {
            $chartData[] = [
                'name' => __('messages.lbl_movies'),
                'data' => [$movieCount],
            ];
            $category[] = __('messages.lbl_movies');
        }

        if (isenablemodule('tvshow') == 1) {
            $chartData[] = [
                'name' => __('messages.lbl_tvshows'),
                'data' => [$tvShowCount],
            ];
            $category[] = __('messages.lbl_tvshows');
        }

        return [
            'chartData' => $chartData,
            'category' => $category,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function parseDateRange(?string $dateRange): array
    {
        $startDate = null;
        $endDate = null;

        if ($dateRange) {
            $dates = explode(' to ', $dateRange);
            if (count($dates) == 1) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) == 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            }
        } else {
            $endDate = Carbon::now()->endOfDay();
            $startDate = Carbon::now()->startOfYear()->startOfDay();
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    public function resolveComparisonPeriods(?Carbon $startDate, ?Carbon $endDate): array
    {
        if ($startDate && $endDate) {
            $currentStart = (clone $startDate)->startOfDay();
            $currentEnd = (clone $endDate)->endOfDay();
        } else {
            $currentStart = Carbon::now()->startOfYear();
            $currentEnd = Carbon::now()->endOfDay();
        }

        $periodDays = $currentStart->diffInDays($currentEnd) + 1;
        $previousEnd = (clone $currentStart)->subDay()->endOfDay();
        $previousStart = (clone $previousEnd)->subDays($periodDays - 1)->startOfDay();

        return [$currentStart, $currentEnd, $previousStart, $previousEnd];
    }

    /**
     * @return array{0: float|int, 1: bool}
     */
    public function computePercentageChange(float|int $currentValue, float|int $previousValue): array
    {
        if ($previousValue == 0) {
            if ($currentValue == 0) {
                return [0, true];
            }

            return [100, true];
        }

        $change = (($currentValue - $previousValue) / $previousValue) * 100;
        $isUp = $change >= 0;
        $percent = min(100, abs(round($change, 2)));

        return [$percent, $isUp];
    }

    /**
     * @return array<int, string>
     */
    public function generateTrendDates(Carbon $previousStart, Carbon $previousEnd, Carbon $currentStart, Carbon $currentEnd): array
    {
        $dates = [];
        $previousMidpoint = $previousStart->copy()->addDays($previousStart->diffInDays($previousEnd) / 2);
        $dates[] = $previousMidpoint->toISOString();
        $currentMidpoint = $currentStart->copy()->addDays($currentStart->diffInDays($currentEnd) / 2);
        $dates[] = $currentMidpoint->toISOString();

        return $dates;
    }
}
