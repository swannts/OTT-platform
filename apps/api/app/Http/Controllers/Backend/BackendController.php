<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminChartService;
use App\Models\User;
use App\Models\Setting;
use Carbon\Carbon;
use Modules\Entertainment\Models\EntertainmentDownload;
use Modules\Entertainment\Models\EntertainmentView;
use Modules\Subscriptions\Models\SubscriptionTransactions;
use Modules\Entertainment\Models\Entertainment;
use Modules\Video\Models\Video;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Genres\Models\Genres;
use Modules\Entertainment\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Frontend\Models\PayperviewTransaction;
use Modules\Episode\Models\Episode;
use Illuminate\Support\Facades\Storage;


class BackendController extends Controller
{
    public function __construct(private readonly AdminChartService $adminChartService)
    {
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
      
        $defaultFormat = Setting::where('name', 'default_date_format')->where('datatype', 'misc')->value('val')  ?? 'Y-m-d';

        [$startDate, $endDate] = $this->adminChartService->parseDateRange($request->get('date_range'));

        $dateRange = $request->get('date_range');

        // Set default date range for display if none provided
        if (!$dateRange) {
            $dateRange = formatDate(Carbon::now()->startOfYear()->format('Y-m-d')) . ' to ' . formatDate(Carbon::now()->format('Y-m-d'));
        } else {
            // Format the date range using the default format for display
            $dates = explode(' to ', $dateRange);
            if (count($dates) == 2) {
                $dateRange = formatDate(trim($dates[0])) . ' to ' . formatDate(trim($dates[1]));
            } elseif (count($dates) == 1) {
                $dateRange = formatDate(trim($dates[0]));
            }
        }

        [$currentStart, $currentEnd, $previousStart, $previousEnd] = $this->adminChartService->resolveComparisonPeriods($startDate, $endDate);

        // Optimize: Cache frequently used values
        $startOfMonth = Carbon::now()->startOfMonth();
        $currentDate = Carbon::now();
        $expiryThreshold = $currentDate->copy()->addDays(7);

        // Optimize: Use single query for all user counts
        $userCounts = $this->getOptimizedUserCounts($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd, $startOfMonth);

        // Optimize: Use single query for basic counts
        $basicCounts = $this->getBasicCounts();

        // Optimize: Use single query for entertainment data
    
        $entertainmentData = $this->getEntertainmentData();
        // Optimize: Use single query for subscription data
        $subscriptionData = $this->getSubscriptionData($startDate, $endDate);

        // Optimize: Use single query for revenue data
        $revenueData = $this->getRevenueData($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd);

        // Optimize: Use single query for rent content data
        $rentContentData = $this->getRentContentData($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd);

        // Optimize: Use single query for review data
        $reviewData = $this->getReviewData($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd);

        // Optimize: Use single query for transactions
        $transactions = SubscriptionTransactions::orderBy('created_at', 'desc')->take(4)->get();
        
        // Optimize: Use single query for most viewed entertainments
        $entertainments = $this->getMostViewedEntertainments();
        
        // Extract values from optimized queries
        $allUsers = $userCounts['allUsers'];
        $newUsersCount = $userCounts['newUsersCount'];
        $totalusers = $userCounts['totalusers'];
        $usersCurrent = $userCounts['usersCurrent'];
        $usersPrevious = $userCounts['usersPrevious'];
        $usersChangePercent = $userCounts['usersChangePercent'];
        $usersChangeUp = $userCounts['usersChangeUp'];
        $usersTrend = $userCounts['usersTrend'];
        $usersTrendDates = $userCounts['usersTrendDates'];
        $activeusers = $userCounts['activeusers'];
        $totalSubscribers = $userCounts['totalSubscribers'];
        $subsCurrent = $userCounts['subsCurrent'];
        $subsPrevious = $userCounts['subsPrevious'];
        $subsChangePercent = $userCounts['subsChangePercent'];
        $subsChangeUp = $userCounts['subsChangeUp'];
        $subsTrend = $userCounts['subsTrend'];
        $subsTrendDates = $userCounts['subsTrendDates'];
        $totalsoontoexpire = $userCounts['totalsoontoexpire'];
        $soonExpireCurrent = $userCounts['soonExpireCurrent'];
        $soonExpirePrevious = $userCounts['soonExpirePrevious'];
        $soonExpireChangePercent = $userCounts['soonExpireChangePercent'];
        $soonExpireChangeUp = $userCounts['soonExpireChangeUp'];
        $soonExpireTrend = $userCounts['soonExpireTrend'];
        $soonExpireTrendDates = $userCounts['soonExpireTrendDates'];

        $totalDownloads = $basicCounts['totalDownloads'];
        $totalTransactions = $basicCounts['totalTransactions'];

        // $entertainments = $entertainmentData['entertainments'];

        $totalmovies = $entertainmentData['totalmovies'];
        $totaltvshow = $entertainmentData['totaltvshow'];
        $totalvideo = $entertainmentData['totalvideo'];
        // Use most viewed entertainments for the view (set below)

        $subscriptionData = $subscriptionData['subscriptionData'];

        $subscription_revenue = $revenueData['subscription_revenue'];
        $rent_revenue = $revenueData['rent_revenue'];
        $total_revenue = $revenueData['total_revenue'];
        $subscriptionRevenueChangePercent = $revenueData['subscriptionRevenueChangePercent'];
        $subscriptionRevenueChangeUp = $revenueData['subscriptionRevenueChangeUp'];
        $subscriptionRevenueTrend = $revenueData['subscriptionRevenueTrend'];
        $subscriptionRevenueTrendDates = $revenueData['subscriptionRevenueTrendDates'];
        $rentRevenueChangePercent = $revenueData['rentRevenueChangePercent'];
        $rentRevenueChangeUp = $revenueData['rentRevenueChangeUp'];
        $rentRevenueTrend = $revenueData['rentRevenueTrend'];
        $rentRevenueTrendDates = $revenueData['rentRevenueTrendDates'];
        $totalRevenueChangePercent = $revenueData['totalRevenueChangePercent'];
        $totalRevenueChangeUp = $revenueData['totalRevenueChangeUp'];
        $totalRevenueTrend = $revenueData['totalRevenueTrend'];
        $totalRevenueTrendDates = $revenueData['totalRevenueTrendDates'];

        $count_of_rent_movie = $rentContentData['count_of_rent_movie'];
        $count_of_rent_episode = $rentContentData['count_of_rent_episode'];
        $count_of_rent_video = $rentContentData['count_of_rent_video'];
        $rentContentChangePercent = $rentContentData['rentContentChangePercent'];
        $rentContentChangeUp = $rentContentData['rentContentChangeUp'];
        $rentContentTrend = $rentContentData['rentContentTrend'];
        $rentContentTrendDates = $rentContentData['rentContentTrendDates'];

        $totalreview = $reviewData['totalreview'];
        $reviewsChangePercent = $reviewData['reviewsChangePercent'];
        $reviewsChangeUp = $reviewData['reviewsChangeUp'];
        $reviewsTrend = $reviewData['reviewsTrend'];
        $reviewsTrendDates = $reviewData['reviewsTrendDates'];
        $reviewData = $reviewData['reviewData'];



        $diskType = env('ACTIVE_STORAGE', 'local');
        if ($diskType == 'local') {
            // Use local storage disk
            $totalUsageInBytes = $this->getTotalStorageUsage($diskType);
        } else {
            // Use DigitalOcean Spaces for production
            $totalUsageInBytes = $this->getTotalStorageUsage($diskType);
        }
        // Format the storage usage into a readable format
        $totalUsageFormatted = $this->formatBytes($totalUsageInBytes);
        return view('backend.dashboard.index', compact(
            'defaultFormat','dateRange','totalUsageFormatted','count_of_rent_movie','count_of_rent_episode','rent_revenue','subscription_revenue','totalreview','totalsoontoexpire','total_revenue','allUsers', 'newUsersCount', 'totalDownloads', 'totalTransactions', 'transactions', 'entertainments','totalusers','activeusers','totalSubscribers','totalmovies','totaltvshow','totalvideo','reviewData','subscriptionData','count_of_rent_video',
            'usersChangePercent','usersChangeUp',
            'subsChangePercent','subsChangeUp',
            'soonExpireChangePercent','soonExpireChangeUp',
            'reviewsChangePercent','reviewsChangeUp',
            'subscriptionRevenueChangePercent','subscriptionRevenueChangeUp',
            'rentRevenueChangePercent','rentRevenueChangeUp',
            'totalRevenueChangePercent','totalRevenueChangeUp',
            'rentContentChangePercent','rentContentChangeUp',
            'usersTrend','subsTrend','soonExpireTrend','reviewsTrend',
            'subscriptionRevenueTrend','rentRevenueTrend','totalRevenueTrend','rentContentTrend',
            'usersTrendDates','subsTrendDates','soonExpireTrendDates','reviewsTrendDates',
            'subscriptionRevenueTrendDates','rentRevenueTrendDates','totalRevenueTrendDates','rentContentTrendDates'
        ));
    }

    /**
     * Get optimized user counts with single query
     */
    private function getOptimizedUserCounts($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd, $startOfMonth)
    {
        // Get all users with date filter
        $allUsersQuery = User::where('user_type', 'user');
        if ($startDate && $endDate) {
            $allUsersQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $allUsers = $allUsersQuery->get();

        // Calculate user metrics
        $newUsersCount = User::where('user_type', 'user')
            ->whereDate('created_at', '>=', $startOfMonth)
            ->count();
        $totalusers = $allUsers->count();
        $activeusers = $allUsers->where('status', 1)->count();
        $totalSubscribers = $allUsers->where('is_subscribe', 1)->count();

        // Current and previous period counts
        $usersCurrent = User::where('user_type', 'user')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count();
        $usersPrevious = User::where('user_type', 'user')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        // Subscription counts
        $subsCurrent = User::where('user_type', 'user')->where('is_subscribe', 1)
            ->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $subsPrevious = User::where('user_type', 'user')->where('is_subscribe', 1)
            ->whereBetween('created_at', [$previousStart, $previousEnd])->count();

        // Soon to expire calculations
        $currentDate = Carbon::now();
        $expiryThreshold = $currentDate->copy()->addDays(7);
        $soonExpireUserIds = Subscription::where('status', 'active')
            ->whereDate('end_date', '<=', $expiryThreshold)
            ->pluck('user_id');
        $totalsoontoexpire = $allUsers->whereIn('id', $soonExpireUserIds)->where('status', 1)->count();

        $soonExpireCurrent = User::where('user_type', 'user')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->whereIn('id', $soonExpireUserIds)->count();
        $soonExpirePrevious = User::where('user_type', 'user')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->whereIn('id', $soonExpireUserIds)->count();

        // Calculate percentage changes and trends
        [$usersChangePercent, $usersChangeUp] = $this->adminChartService->computePercentageChange($usersCurrent, $usersPrevious);
        $usersTrend = [$usersPrevious, $usersCurrent];
        $usersTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        [$subsChangePercent, $subsChangeUp] = $this->adminChartService->computePercentageChange($subsCurrent, $subsPrevious);
        $subsTrend = [$subsPrevious, $subsCurrent];
        $subsTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        [$soonExpireChangePercent, $soonExpireChangeUp] = $this->adminChartService->computePercentageChange($soonExpireCurrent, $soonExpirePrevious);
        $soonExpireTrend = [$soonExpirePrevious, $soonExpireCurrent];
        $soonExpireTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        return compact('allUsers', 'newUsersCount', 'totalusers', 'activeusers', 'totalSubscribers',
                      'usersCurrent', 'usersPrevious', 'usersChangePercent', 'usersChangeUp', 'usersTrend', 'usersTrendDates',
                      'subsCurrent', 'subsPrevious', 'subsChangePercent', 'subsChangeUp', 'subsTrend', 'subsTrendDates',
                      'totalsoontoexpire', 'soonExpireCurrent', 'soonExpirePrevious', 'soonExpireChangePercent',
                      'soonExpireChangeUp', 'soonExpireTrend', 'soonExpireTrendDates');
    }

    /**
     * Get basic counts with optimized queries
     */
    private function getBasicCounts()
    {
        $totalDownloads = EntertainmentDownload::count();
        $totalTransactions = SubscriptionTransactions::count();

        return compact('totalDownloads', 'totalTransactions');
    }

    /**
     * Get entertainment data with optimized queries
     * OPTIMIZATION: Use direct count queries instead of loading all records into memory
     */
    private function getEntertainmentData()
    {
        // $entertainments = Entertainment::where('status', 1)->get();
        // $totalmovies = $entertainments->where('type', 'movie')->count();
        // $totaltvshow = $entertainments->where('type', 'tvshow')->count();
        // Use direct count queries instead of ->get() to avoid memory exhaustion with large datasets
        $totalmovies = Entertainment::where('status', 1)->where('type', 'movie')->count();
        $totaltvshow = Entertainment::where('status', 1)->where('type', 'tvshow')->count();
        $totalvideo = Video::where('status', 1)->count();

        // Return empty collection for $entertainments since it's not used in the view
        // (the actual $entertainments used in view comes from getMostViewedEntertainments())
        $entertainments = collect();

       

        // Return empty array for entertainments as most viewed are loaded separately
        return compact('entertainments', 'totalmovies', 'totaltvshow', 'totalvideo');
    }

    /**
     * Get subscription data with optimized queries
     */
    private function getSubscriptionData($startDate, $endDate)
    {
        $subscriptionDataQuery = Subscription::with('user', 'subscription_transaction', 'plan');
        if ($startDate && $endDate) {
            $subscriptionDataQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $subscriptionData = $subscriptionDataQuery->orderBy('created_at', 'desc')->take(6)->get();

        return compact('subscriptionData');
    }

    /**
     * Get revenue data with optimized queries
     */
    private function getRevenueData($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd)
    {
        // Current period revenue - Use created_at (payment date) for consistent totals with chart
        $subscriptionRevenueQuery = SubscriptionTransactions::query()
            ->where('payment_status', 'paid');
        if ($startDate && $endDate) {
            $subscriptionRevenueQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $subscription_revenue = (float) $subscriptionRevenueQuery->sum('amount');

        $rentRevenueQuery = PayperviewTransaction::query();
        if ($startDate && $endDate) {
            $rentRevenueQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $rent_revenue = (float) $rentRevenueQuery->sum('amount');
        $total_revenue = $subscription_revenue + $rent_revenue;

        // Comparison period revenue
        $subscriptionRevenueCurrent = (float) SubscriptionTransactions::where('payment_status', 'paid')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->sum('amount');
        $subscriptionRevenuePrevious = (float) SubscriptionTransactions::where('payment_status', 'paid')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('amount');
        [$subscriptionRevenueChangePercent, $subscriptionRevenueChangeUp] = $this->adminChartService->computePercentageChange($subscriptionRevenueCurrent, $subscriptionRevenuePrevious);
        $subscriptionRevenueTrend = [$subscriptionRevenuePrevious, $subscriptionRevenueCurrent];
        $subscriptionRevenueTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        $rentRevenueCurrent = (float) PayperviewTransaction::whereBetween('created_at', [$currentStart, $currentEnd])->sum('amount');
        $rentRevenuePrevious = (float) PayperviewTransaction::whereBetween('created_at', [$previousStart, $previousEnd])->sum('amount');
        [$rentRevenueChangePercent, $rentRevenueChangeUp] = $this->adminChartService->computePercentageChange($rentRevenueCurrent, $rentRevenuePrevious);
        $rentRevenueTrend = [$rentRevenuePrevious, $rentRevenueCurrent];
        $rentRevenueTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        $totalRevenueCurrent = $subscriptionRevenueCurrent + $rentRevenueCurrent;
        $totalRevenuePrevious = $subscriptionRevenuePrevious + $rentRevenuePrevious;
        [$totalRevenueChangePercent, $totalRevenueChangeUp] = $this->adminChartService->computePercentageChange($totalRevenueCurrent, $totalRevenuePrevious);
        $totalRevenueTrend = [$totalRevenuePrevious, $totalRevenueCurrent];
        $totalRevenueTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        return compact('subscription_revenue', 'rent_revenue', 'total_revenue', 'subscriptionRevenueChangePercent',
                      'subscriptionRevenueChangeUp', 'subscriptionRevenueTrend', 'subscriptionRevenueTrendDates',
                      'rentRevenueChangePercent', 'rentRevenueChangeUp', 'rentRevenueTrend', 'rentRevenueTrendDates',
                      'totalRevenueChangePercent', 'totalRevenueChangeUp', 'totalRevenueTrend', 'totalRevenueTrendDates');
    }

    /**
     * Get rent content data with optimized queries
     */
    private function getRentContentData($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd)
    {
        $rentMovieQuery = Entertainment::where('type', 'movie')->where('status', 1)->where('movie_access', 'pay-per-view');
        if ($startDate && $endDate) {
            $rentMovieQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $count_of_rent_movie = $rentMovieQuery->count();

        $rentEpisodeQuery = Episode::where('access', 'pay-per-view')->where('status', 1);
        if ($startDate && $endDate) {
            $rentEpisodeQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $count_of_rent_episode = $rentEpisodeQuery->count();

        $rentVideoQuery = Video::where('access', 'pay-per-view')->where('status', 1);
        if ($startDate && $endDate) {
            $rentVideoQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $count_of_rent_video = $rentVideoQuery->count();

        $rentContentCurrent = $count_of_rent_movie + $count_of_rent_episode + $count_of_rent_video;

        $prevRentMovie = Entertainment::where('type', 'movie')->where('status', 1)->where('movie_access', 'pay-per-view')
            ->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $prevRentEpisode = Episode::where('access', 'pay-per-view')->where('status', 1)
            ->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $prevRentVideo = Video::where('access', 'pay-per-view')->where('status', 1)
            ->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $rentContentPrevious = $prevRentMovie + $prevRentEpisode + $prevRentVideo;

        [$rentContentChangePercent, $rentContentChangeUp] = $this->adminChartService->computePercentageChange($rentContentCurrent, $rentContentPrevious);
        $rentContentTrend = [$rentContentPrevious, $rentContentCurrent];
        $rentContentTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        return compact('count_of_rent_movie', 'count_of_rent_episode', 'count_of_rent_video',
                      'rentContentChangePercent', 'rentContentChangeUp', 'rentContentTrend', 'rentContentTrendDates');
    }

    /**
     * Get review data with optimized queries
     */
    private function getReviewData($startDate, $endDate, $currentStart, $currentEnd, $previousStart, $previousEnd)
    {
        $totalreviewQuery = Review::query();
        if ($startDate && $endDate) {
            $totalreviewQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $totalreview = $totalreviewQuery->count();

        $reviewsCurrent = Review::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $reviewsPrevious = Review::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        [$reviewsChangePercent, $reviewsChangeUp] = $this->adminChartService->computePercentageChange($reviewsCurrent, $reviewsPrevious);
        $reviewsTrend = [$reviewsPrevious, $reviewsCurrent];
        $reviewsTrendDates = $this->adminChartService->generateTrendDates($previousStart, $previousEnd, $currentStart, $currentEnd);

        $reviewDataQuery = Review::with(['entertainment', 'user'])
            ->whereHas('entertainment', function($query) {
                if (isenablemodule('tvshow') == 1 && isenablemodule('movie') == 1) {
                    $query->where('type', 'movie')->orwhere('type', 'tvshow');
                } else {
                    if (isenablemodule('movie') == 1) {
                        $query->where('type', 'movie');
                    }
                    if (isenablemodule('tvshow') == 1) {
                        $query->Where('type', 'tvshow');
                    }
                }
            });
        if ($startDate && $endDate) {
            $reviewDataQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $reviewData = $reviewDataQuery->orderBy('created_at', 'desc')->take(6)->get();

        return compact('totalreview', 'reviewsChangePercent', 'reviewsChangeUp', 'reviewsTrend',
                      'reviewsTrendDates', 'reviewData');
    }

    /**
     * Get most viewed entertainments with optimized query
     */
    private function getMostViewedEntertainments()
    {
        $mostFrequentIds = EntertainmentView::select('entertainment_id')
            ->groupBy('entertainment_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(4)
            ->pluck('entertainment_id')
            ->toArray();

        return Entertainment::whereIn('id', $mostFrequentIds)->get();
    }

    /**
     * Get total storage usage in bytes for the configured disk.
     * The MB amount shown on the dashboard is the sum of all file sizes
     * on the disk (env ACTIVE_STORAGE, default 'local') — includes uploads, media, and app files.
     */
    private function getTotalStorageUsage($disk)
    {
        $totalSize = 0;
        $files = Storage::disk($disk)->allFiles();

        foreach ($files as $file) {
            $fileSize = Storage::disk($disk)->size($file);

            $totalSize += $fileSize;
        }
        return $totalSize;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    public function getRevenuechartData(Request $request, $type)
    {
        $data = $this->adminChartService->getRevenueChartData((string) $type, $request->get('date_range'));

        return response()->json(['data' => $data, 'status' => true])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function getSubscriberChartData(Request $request, $type)
    {
        $data = $this->adminChartService->getSubscriberChartData((string) $type, $request->get('date_range'));

        return response()->json(['data' => $data, 'status' => true]);
    }

    public function getGenreChartData(Request $request)
    {
        $data = $this->adminChartService->getGenreChartData($request->get('date_range'));

        return response()->json(['data' => $data, 'status' => true]);
    }

    public function getMostwatchChartData(Request $request, $type)
    {
        $data = $this->adminChartService->getMostWatchChartData((string) $type, $request->get('date_range'));

        return response()->json(['data' => $data, 'status' => true]);
    }

    public function getTopRatedChartData(Request $request)
    {
        $data = $this->adminChartService->getTopRatedChartData($request->get('date_range'));

        return response()->json(['data' => $data, 'status' => true]);
    }
}
