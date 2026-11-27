<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminOperationsController extends Controller
{
    public function media(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        $assetsAvailable = Schema::hasTable('media_assets');
        $jobsAvailable = Schema::hasTable('media_processing_jobs');
        $assetStatuses = $assetsAvailable
            ? DB::table('media_assets')->whereNull('deleted_at')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')
            : collect();
        $jobStatuses = $jobsAvailable
            ? DB::table('media_processing_jobs')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')
            : collect();
        $baseAssets = $assetsAvailable ? DB::table('media_assets')->whereNull('deleted_at') : null;
        $storageByCategory = $assetsAvailable && Schema::hasColumn('media_assets', 'category')
            ? DB::table('media_assets')->whereNull('deleted_at')->selectRaw('category, SUM(size_bytes) AS bytes')->groupBy('category')->pluck('bytes', 'category')
            : null;
        $recentFailures = $jobsAvailable
            ? DB::table('media_processing_jobs as jobs')->join('media_assets as assets', 'assets.id', '=', 'jobs.media_asset_id')
                ->where('jobs.status', 'failed')->whereNull('assets.deleted_at')->orderByDesc('jobs.updated_at')->limit(8)
                ->get(['jobs.id', 'jobs.media_asset_id', 'jobs.failure_code', 'jobs.updated_at', 'assets.original_name', 'assets.display_name'])
            : null;
        return response()->json(['data' => [
            'published_videos' => $this->countWhere('videos', 'status', 1),
            'published_catalog_items' => $this->countWhere('entertainments', 'status', 1),
            'pending_reviews' => $this->countWhere('admin_content_review_submissions', 'status', 'in_review'),
            'total_assets' => $assetsAvailable ? (int) (clone $baseAssets)->count() : null,
            'uploaded_assets' => $assetStatuses['uploaded'] ?? ($assetsAvailable ? 0 : null),
            'queued_assets' => $assetStatuses['queued'] ?? ($assetsAvailable ? 0 : null),
            'processing_assets' => $assetStatuses['processing'] ?? ($assetsAvailable ? 0 : null),
            'ready_assets' => $assetStatuses['ready'] ?? ($assetsAvailable ? 0 : null),
            'failed_assets' => $assetStatuses['failed'] ?? ($assetsAvailable ? 0 : null),
            'awaiting_quality_review' => $assetsAvailable && Schema::hasColumn('media_assets', 'quality_status')
                ? (clone $baseAssets)->whereIn('quality_status', ['pending_inspection', 'awaiting_manual_review'])->count() : null,
            'archived_assets' => $assetsAvailable ? DB::table('media_assets')->whereNotNull('deleted_at')->count() : null,
            'active_processing_jobs' => $jobStatuses['processing'] ?? ($jobsAvailable ? 0 : null),
            'queued_processing_jobs' => $jobStatuses['queued'] ?? ($jobsAvailable ? 0 : null),
            'failed_processing_jobs' => $jobStatuses['failed'] ?? ($jobsAvailable ? 0 : null),
            'source_storage_bytes_estimated' => $assetsAvailable ? (int) (clone $baseAssets)->sum('size_bytes') : null,
            'storage_by_category_bytes_estimated' => $storageByCategory,
            'recent_uploads' => $assetsAvailable ? (clone $baseAssets)->orderByDesc('created_at')->limit(8)->get(['id', 'display_name', 'original_name', 'status', 'size_bytes', 'created_at']) : null,
            'recent_processing_failures' => $recentFailures,
            'storage_usage_kind' => 'application metadata estimate; not provider-reported object storage usage',
        ]]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.view'), 403);
        return response()->json(['data' => [
            'plans' => Schema::hasTable('plan') ? DB::table('plan')->count() : 0,
            'active_subscriptions' => $this->countWhere('subscriptions', 'status', 'active'),
            'subscribed_accounts' => $this->countWhere('users', 'is_subscribe', 1),
        ]]);
    }

    public function finance(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);
        $transactionTables = ['subscriptions_transactions', 'payperviewstransactions'];
        $available = array_values(array_filter($transactionTables, fn (string $table): bool => Schema::hasTable($table)));
        return response()->json(['data' => [
            'transaction_tables_available' => count($available),
            'payment_records' => array_sum(array_map(fn (string $table): int => DB::table($table)->count(), $available)),
        ]]);
    }

    public function marketing(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'marketing.campaigns.manage'), 403);
        return response()->json(['data' => [
            'banners' => Schema::hasTable('banners') ? DB::table('banners')->count() : 0,
            'notification_templates' => Schema::hasTable('notification_templates') ? DB::table('notification_templates')->count() : 0,
        ]]);
    }

    public function analytics(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'analytics.audience.view'), 403);
        return response()->json(['data' => [
            'customer_accounts' => $this->countWhere('users', 'user_type', 'user'),
            'subscribed_accounts' => $this->countWhere('users', 'is_subscribe', 1),
            'published_movies' => $this->countWhere('entertainments', 'status', 1, 'movie'),
            'published_series' => $this->countWhere('entertainments', 'status', 1, 'tvshow'),
        ]]);
    }

    public function operations(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'operations.health.view'), 403);
        $database = 'ok';
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $database = 'unavailable';
        }
        return response()->json(['data' => [
            'application' => 'ok', 'database' => $database,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'queued_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
        ]]);
    }

    private function countWhere(string $table, string $column, mixed $value, ?string $type = null): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }
        $query = DB::table($table)->where($column, $value);
        if ($type !== null && Schema::hasColumn($table, 'type')) {
            $query->where('type', $type);
        }
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        return $query->count();
    }
}
