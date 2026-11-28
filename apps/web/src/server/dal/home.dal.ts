import 'server-only';

import { unstable_cache } from 'next/cache';
import { api, normalizeMediaItem, type HomepageConfig } from '@/lib/api';
import { MediaItem, LiveChannel, LiveCategory } from '@/types';

export interface HomeInitialData {
  banners: MediaItem[];
  top10: MediaItem[];
  latestMovies: MediaItem[];
  popularMovies: MediaItem[];
  payPerView: MediaItem[];
  tvShows: MediaItem[];
  liveChannels: LiveChannel[];
  homepageConfig: HomepageConfig;
}

async function loadHomeInitialData(locale: 'en' | 'bn'): Promise<{ data: HomeInitialData; complete: boolean }> {
  let complete = true;
  const result: HomeInitialData = {
    banners: [],
    top10: [],
    latestMovies: [],
    popularMovies: [],
    payPerView: [],
    tvShows: [],
    liveChannels: [],
    homepageConfig: { sections: [], premium_card: { enabled: true, title: 'Go Premium', description: 'Unlock exclusive content, ad-free experience and more.', image_url: '', cta: 'Upgrade Now', url: '/pricing' }, latest_movies: [] },
  };

  try {
    // The hero is the first visible homepage content, so request its slides
    // before starting the lower-page catalog requests.
    const bannerRes = await api.getBannerData('home', undefined, locale)
      .then((value) => ({ status: 'fulfilled', value }) as const)
      .catch((reason: unknown) => ({ status: 'rejected', reason }) as const);

    const [dashRes, liveRes, tvShowsRes, configRes] = await Promise.allSettled([
      api.getDashboard(undefined, locale),
      api.getLiveTvDashboard(undefined, locale),
      api.getTvShows({ page: 1 }, undefined, locale),
      api.getHomepageConfig(undefined, locale),
    ]);

    complete =
      bannerRes.status === 'fulfilled' &&
      bannerRes.value != null &&
      bannerRes.value.length > 0 &&
      dashRes.status === 'fulfilled' &&
      dashRes.value != null &&
      liveRes.status === 'fulfilled' &&
      liveRes.value != null &&
      tvShowsRes.status === 'fulfilled' &&
      tvShowsRes.value != null &&
      configRes.status === 'fulfilled';

    if (configRes.status === 'fulfilled' && configRes.value) result.homepageConfig = configRes.value;

    if (bannerRes.status === 'fulfilled' && Array.isArray(bannerRes.value)) {
      result.banners = bannerRes.value.map(normalizeMediaItem);
    }

    if (dashRes.status === 'fulfilled' && dashRes.value) {
      const d = (dashRes.value as Record<string, unknown>)?.data as Record<string, unknown> || (dashRes.value as Record<string, unknown>);
      const getList = (key: unknown): MediaItem[] => {
        if (!key) return [];
        if (Array.isArray(key)) return key.map(normalizeMediaItem);
        if (typeof key === 'object' && key !== null && 'data' in key && Array.isArray((key as { data: unknown[] }).data)) {
          return (key as { data: unknown[] }).data.map(normalizeMediaItem);
        }
        return [];
      };

      result.top10 = getList(d?.top_10);
      result.latestMovies = getList(d?.latest_movie);
      result.popularMovies = getList(d?.popular_movie);
      result.payPerView = getList(d?.pay_per_view);
      result.tvShows = getList(d?.tv_show);

      // The catalogue endpoint is a valid source for latest titles when the
      // dashboard omits that section. Do not relabel latest titles as Top 10.
      if (result.latestMovies.length === 0) {
        try {
          const mRes = await api.getMovies({ page: 1 }, undefined, locale);
          const mList = Array.isArray(mRes) ? mRes : (mRes as { data?: unknown[] })?.data || [];
          if (Array.isArray(mList) && mList.length > 0) {
            result.latestMovies = mList.map(normalizeMediaItem);
          }
        } catch {
          complete = false;
        }
      }
    }

    const trendingConfig = result.homepageConfig.sections.find((section) => section.key === 'latest-movies');
    if (Array.isArray(trendingConfig?.content_ids)) {
      result.latestMovies = result.homepageConfig.latest_movies.map(normalizeMediaItem);
    }

    if (tvShowsRes.status === 'fulfilled') {
      const response = tvShowsRes.value;
      const shows = Array.isArray(response)
        ? response
        : Array.isArray(response?.data)
          ? response.data
          : [];
      if (shows.length > 0) result.tvShows = shows.map(normalizeMediaItem);
    }

    if (liveRes.status === 'fulfilled' && liveRes.value) {
      const res = liveRes.value;
      const rawChannels: LiveChannel[] = [];
      if (res?.slider && Array.isArray(res.slider)) rawChannels.push(...res.slider);
      if (res?.category_data && Array.isArray(res.category_data)) {
        res.category_data.forEach((cat: LiveCategory) => {
          if (cat.channel_data && Array.isArray(cat.channel_data)) {
            rawChannels.push(...cat.channel_data);
          }
        });
      }
      const seen = new Set();
      const unique: LiveChannel[] = [];
      for (const c of rawChannels) {
        const name = c?.details?.name || c?.name;
        if (!c || !name || seen.has(c.id)) continue;
        seen.add(c.id);
        unique.push({
          id: c.id,
          name,
          category: c.details?.category || c.category,
          // The v3 live-TV endpoint returns poster_image/poster_tv_image;
          // poster_url is not present on its slider/category resources.
          poster_image:
            c.poster_tv_image ||
            c.poster_image ||
            c.thumbnail_image ||
            c.poster_url ||
            c.details?.poster_tv_image ||
            c.details?.poster_url ||
            '',
        });
      }
      result.liveChannels = unique;
    }
  } catch {
    complete = false;
  }

  return { data: result, complete };
}

class UncacheableHomeDataError extends Error {
  #data: HomeInitialData;

  constructor(data: HomeInitialData) {
    super('Home catalog is incomplete or empty.');
    this.#data = data;
  }

  get data(): HomeInitialData {
    return this.#data;
  }
}

const getCachedHomeInitialData = unstable_cache(async (locale: 'en' | 'bn') => {
  const { data, complete } = await loadHomeInitialData(locale);
  const hasCatalogData = [data.banners, data.top10, data.latestMovies, data.popularMovies, data.payPerView, data.tvShows, data.liveChannels]
    .some((section) => section.length > 0);
  if (!complete || !hasCatalogData) throw new UncacheableHomeDataError(data);
  return data;
}, ['home-initial-data-v9'], {
  revalidate: 60,
  tags: ['home-initial-data', 'dashboard-detail', 'banner-data-home', 'livetv-dashboard'],
});

export async function getHomeInitialDataDal(locale: 'en' | 'bn' = 'bn'): Promise<HomeInitialData> {
  try {
    return await getCachedHomeInitialData(locale);
  } catch (error) {
    if (error instanceof UncacheableHomeDataError) return error.data;
    return (await loadHomeInitialData(locale)).data;
  }
}
