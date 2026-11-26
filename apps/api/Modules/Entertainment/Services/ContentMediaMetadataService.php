<?php

namespace Modules\Entertainment\Services;

use App\Models\MediaAsset;
use App\Services\Media\MediaReadinessService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Ad\Models\CustomAdsSetting;
use Modules\Ad\Models\VastAdsSetting;
use Modules\Entertainment\Models\EntertainmentStreamContentMapping;
use Modules\Entertainment\Models\EntertainmnetDownloadMapping;
use Modules\Episode\Models\EpisodeDownloadMapping;
use Modules\Episode\Models\EpisodeStreamContentMapping;
use Modules\Video\Models\VideoDownloadMapping;
use Modules\Video\Models\VideoStreamContentMapping;

class ContentMediaMetadataService
{
    /**
     * @return Collection<int, object>
     */
    public function streamMappingsFor(string $type, int|string $contentId): Collection
    {
        return match ($type) {
            'movie', 'tvshow' => EntertainmentStreamContentMapping::query()
                ->where('entertainment_id', $contentId)
                ->get(['id', 'type as url_type', 'url', 'quality']),
            'episode' => EpisodeStreamContentMapping::query()
                ->where('episode_id', $contentId)
                ->get(['id', 'type as url_type', 'url', 'quality']),
            default => VideoStreamContentMapping::query()
                ->where('video_id', $contentId)
                ->get(['id', 'type as url_type', 'url', 'quality']),
        };
    }

    /**
     * @return Collection<int, object>
     */
    public function downloadMappingsFor(string $type, int|string $contentId, ?string $downloadQuality = null): Collection
    {
        return match ($type) {
            'movie', 'tvshow' => EntertainmnetDownloadMapping::query()
                ->where('entertainment_id', $contentId)
                ->when($downloadQuality, fn ($q) => $q->where('quality', $downloadQuality))
                ->get(),
            'episode' => EpisodeDownloadMapping::query()
                ->where('episode_id', $contentId)
                ->when($downloadQuality, fn ($q) => $q->where('quality', $downloadQuality))
                ->get(),
            default => VideoDownloadMapping::query()
                ->where('video_id', $contentId)
                ->when($downloadQuality, fn ($q) => $q->where('quality', $downloadQuality))
                ->get(),
        };
    }

    /**
     * @param  Collection<int, object>  $qualityRows
     * @return array<int, array<string, mixed>>
     */
    public function formatPlaybackQualities(object $content, string $type, Collection $qualityRows): array
    {
        $qualities = [[
            'quality' => 'default_quality',
            'url' => ($content->video_upload_type ?? '') === 'Local'
                ? setBaseUrlWithFileName(trim((string) $content->video_url_input), 'video', $type)
                : trim((string) $content->video_url_input),
            'url_type' => trim((string) ($content->video_upload_type ?? 'URL')),
        ]];

        foreach ($qualityRows as $quality) {
            $qualities[] = [
                'id' => $quality->id,
                'quality' => trim((string) $quality->quality),
                'url_type' => trim((string) $quality->url_type),
                'url' => $quality->url_type === 'Local'
                    ? setBaseUrlWithFileName(trim((string) $quality->url), 'video', $type)
                    : trim((string) $quality->url),
            ];
        }

        return $qualities;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildTrailerData(object $content, string $type, string $deviceType): array
    {
        $trailers = [];

        if (isset($content->trailer_url_type) && isset($content->trailer_url)) {
            $trailers[] = [
                'id' => $content->id,
                'title' => 'default trailer',
                'url_type' => trim((string) $content->trailer_url_type),
                'url' => $content->trailer_url_type === 'Local'
                    ? setBaseUrlWithFileName(trim((string) $content->trailer_url), 'video', $type)
                    : trim((string) $content->trailer_url),
                'poster_image' => isset($content->poster_url)
                    ? setBaseUrlWithFileName($content->poster_url, 'image', $type)
                    : null,
            ];
        }

        if (isset($content->clips) && method_exists($content, 'relationLoaded') && $content->relationLoaded('clips') && $content->clips->count() > 0) {
            foreach ($content->clips as $clip) {
                $trailers[] = [
                    'id' => $clip->id,
                    'title' => $clip->title,
                    'url_type' => $clip->type,
                    'url' => $clip->type === 'Local'
                        ? setBaseUrlWithFileName(trim((string) $clip->url), 'video', $type)
                        : trim((string) $clip->url),
                    'poster_image' => $deviceType === 'tv'
                        ? setBaseUrlWithFileName($clip->tv_poster_url ?? null, 'image', $type)
                        : setBaseUrlWithFileName($clip->poster_url ?? null, 'image', $type),
                ];
            }
        }

        return $trailers;
    }

    /**
     * @param  array<int, string>  $placement
     * @return array{customAds: Collection<int, array<string, mixed>>, vastAds: array<string, array<int, mixed>>}
     */
    public function loadAds(int $contentId, string $type, array $placement): array
    {
        $today = Carbon::now()->toDateString();

        $customAds = CustomAdsSetting::query()
            ->where('status', 1)
            ->whereIn('placement', $placement)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereJsonContains('target_categories', $contentId)
            ->select('type', 'media', 'redirect_url', 'placement')
            ->get()
            ->map(static fn ($ad): array => [
                'type' => $ad->type,
                'placement' => $ad->placement,
                'url' => $ad->media ? setBaseUrlWithFileName(trim((string) $ad->media), $ad->type, 'ads') : null,
                'redirect_url' => $ad->redirect_url,
            ]);

        $vastAds = VastAdsSetting::query()
            ->where('status', 1)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereJsonContains('target_selection', $contentId)
            ->where('target_type', $type === 'episode' ? 'tvshow' : $type)
            ->select('type', 'url')
            ->get();

        return [
            'customAds' => $customAds,
            'vastAds' => [
                'pre_role_ad_url' => $vastAds->where('type', 'pre-roll')->pluck('url')->toArray(),
                'mid_role_ad_url' => $vastAds->where('type', 'mid-roll')->pluck('url')->toArray(),
                'post_role_ad_url' => $vastAds->where('type', 'post-roll')->pluck('url')->toArray(),
                'overlay_ad_url' => $vastAds->where('type', 'overlay')->pluck('url')->toArray(),
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $downloadMappings
     * @return array{download_enable: mixed, download_quality: array<int, array<string, mixed>>}
     */
    public function buildDownloadData(object $content, string $type, Collection $downloadMappings): array
    {
        $defaultDownload = [];

        if (! empty($content->download_type) || ! empty($content->download_url)) {
            $defaultDownload[] = [
                'id' => $content->id,
                'url_type' => $content->download_type ?? null,
                'url' => ($content->download_type === 'Local')
                    ? setBaseUrlWithFileName($content->download_url ?? null, 'video', $type)
                    : ($content->download_url ?? null),
                'quality' => 'default_quality',
            ];
        }

        $mappingDownloads = [];
        foreach ($downloadMappings as $mapping) {
            $mappingDownloads[] = [
                'id' => $mapping->id,
                'url_type' => $mapping->type,
                'url' => ($mapping->type === 'Local')
                    ? setBaseUrlWithFileName($mapping->url, 'video', $type)
                    : $mapping->url,
                'quality' => $mapping->quality,
            ];
        }

        return [
            'download_enable' => $content->download_status ?? 0,
            'download_quality' => array_merge($defaultDownload, $mappingDownloads),
        ];
    }

    /**
     * @param  array<string, mixed>  $contentAccess
     * @return array<string, mixed>
     */
    public function buildRentalData(object $content, array $contentAccess): array
    {
        if (($contentAccess['access'] ?? null) !== 'pay-per-view') {
            return [];
        }

        $rental = [
            'price' => (float) ($content->price ?? 0),
            'discount' => (int) ($content->discount ?? 0),
            'access_duration' => $content->access_duration ?? null,
            'availability_days' => $content->available_for ?? null,
            'access' => $content->purchase_type ?? null,
        ];

        $rental['discounted_price'] = ($rental['price'] > 0 && $rental['discount'] > 0)
            ? round($rental['price'] - ($rental['price'] * $rental['discount'] / 100), 2)
            : $rental['price'];

        return $rental;
    }

    /**
     * @return array{asset: ?MediaAsset, ready: bool}
     */
    public function resolveReadyMediaAsset(object $content, MediaReadinessService $mediaReadiness): array
    {
        if (! $content->media_asset_id) {
            return ['asset' => null, 'ready' => true];
        }

        $mediaAsset = MediaAsset::query()
            ->whereKey($content->media_asset_id)
            ->where('status', 'ready')
            ->first();

        if (! $mediaAsset || ! $mediaReadiness->inspect($mediaAsset)['ready']) {
            return ['asset' => null, 'ready' => false];
        }

        return ['asset' => $mediaAsset, 'ready' => true];
    }
}
