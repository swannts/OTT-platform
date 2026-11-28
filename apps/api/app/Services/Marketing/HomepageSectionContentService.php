<?php

namespace App\Services\Marketing;

use App\Models\MobileSetting;
use App\Services\Dashboard\DashboardSectionService;
use Illuminate\Http\Request;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Transformers\MoviesResourceV3;
use Modules\Entertainment\Transformers\TvshowResourceV3;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvChannelResourceV3;

class HomepageSectionContentService
{
    public function __construct(private readonly DashboardSectionService $dashboardSections) {}

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<int, array<string, mixed>>  $latestMovies
     * @return array<int, array<string, mixed>>
     */
    public function hydrate(array $sections, array $latestMovies): array
    {
        return array_map(function (array $section) use ($latestMovies): array {
            $key = $section['key'];
            $section['items'] = ! ($section['enabled'] ?? true) ? [] : match ($key) {
                'latest-movies' => $latestMovies,
                'live-now' => $this->liveChannels(),
                'top-10' => $this->movies('top-10', 'top_10'),
                'popular-tvshows' => $this->tvShows(),
                'popular-movies' => $this->movies('popular-movies', 'popular-movies'),
                'pay-per-view' => $this->payPerView(),
                default => [],
            };
            $section['more_href'] = match ($key) {
                'popular-tvshows' => '/tv-shows',
                'live-now' => '/live-tv',
                default => '/movies',
            };
            $section['ranked'] = $key === 'top-10';

            return $section;
        }, $sections);
    }

    /** @return array<int, array<string, mixed>> */
    private function movies(string $settingSlug, string $contentType): array
    {
        $ids = $this->selectedIds($settingSlug);
        if ($ids === []) {
            return [];
        }

        $items = $contentType === 'top_10'
            ? Entertainment::get_top_movie_v3($ids)
            : Entertainment::get_popular_movieV3($ids);

        return MoviesResourceV3::collection($items)->toArray(request());
    }

    /** @return array<int, array<string, mixed>> */
    private function tvShows(): array
    {
        $ids = $this->selectedIds('popular-tvshows');
        $shows = $ids !== []
            ? Entertainment::get_popular_tvshowV3($ids)
            : Entertainment::query()
                ->with(['genresdata:id,name,name_bn', 'plan:id,level'])
                ->where('type', 'tvshow')
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->when(request()->has('is_restricted'), fn ($query) => $query->where('is_restricted', request()->boolean('is_restricted')))
                ->when(! empty(getCurrentProfileSession('is_child_profile')), fn ($query) => $query->where('is_restricted', 0))
                ->orderByDesc('release_date')
                ->limit(20)
                ->get();

        return TvshowResourceV3::collection($shows)->toArray(request());
    }

    /** @return array<int, array<string, mixed>> */
    private function liveChannels(): array
    {
        $channels = LiveTvChannel::query()
            ->with(['TvCategory', 'plan', 'TvChannelStreamContentMappings'])
            ->where('status', 1)
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        return LiveTvChannelResourceV3::collection($channels)->toArray(request());
    }

    /** @return array<int, array<string, mixed>> */
    private function payPerView(): array
    {
        return $this->dashboardSections->getPayPerViewUnlockedContent(request(), 8);
    }

    /** @return array<int, string> */
    private function selectedIds(string $slug): array
    {
        $setting = MobileSetting::query()->where('slug', $slug)->first(['value', 'type']);
        if (! $setting || filled($setting->type)) {
            return [];
        }

        $ids = json_decode((string) $setting->value, true);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_slice(array_filter($ids, fn ($id) => is_string($id) || is_int($id)), 0, 100));
    }
}
