import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, LiveChannel, LiveCategory } from '@/types';

export interface HomeInitialData {
  banners: MediaItem[];
  top10: MediaItem[];
  latestMovies: MediaItem[];
  popularMovies: MediaItem[];
  payPerView: MediaItem[];
  tvShows: MediaItem[];
  liveChannels: LiveChannel[];
}

export async function getHomeInitialDataDal(): Promise<HomeInitialData> {
  const result: HomeInitialData = {
    banners: [],
    top10: [],
    latestMovies: [],
    popularMovies: [],
    payPerView: [],
    tvShows: [],
    liveChannels: [],
  };

  try {
    const [bannerRes, dashRes, liveRes] = await Promise.allSettled([
      api.getBannerData('home'),
      api.getDashboard(),
      api.getLiveTvDashboard(),
    ]);

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

      // Robust fallback if dashboard top10 / latest is empty
      if (result.latestMovies.length === 0) {
        try {
          const mRes = await api.getMovies({ page: 1 });
          const mList = Array.isArray(mRes) ? mRes : (mRes as { data?: unknown[] })?.data || [];
          if (Array.isArray(mList) && mList.length > 0) {
            result.latestMovies = mList.map(normalizeMediaItem);
            if (result.top10.length === 0) {
              result.top10 = result.latestMovies.slice(0, 10);
            }
          }
        } catch {
          // Fallback ignore
        }
      }
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
        if (!c || seen.has(c.id)) continue;
        seen.add(c.id);
        unique.push({
          id: c.id,
          name: c.details?.name || c.name || `Live Channel ${c.id}`,
          category: c.details?.category || c.category || 'Live Stream',
          poster_image: c.poster_url || c.details?.poster_url || '',
          server_url: c.details?.server_url || c.details?.stream_url || c.server_url || c.stream_url || '',
        });
      }
      result.liveChannels = unique;
    }
  } catch (error) {
    console.error('[DAL] getHomeInitialDataDal error:', error);
  }

  return result;
}
