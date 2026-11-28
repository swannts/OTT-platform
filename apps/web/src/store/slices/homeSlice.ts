import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { api, normalizeMediaItem, type HomepageConfig } from '@/lib/api';
import type { DashboardData, LiveCategory, LiveChannel, MediaItem } from '@/types';
import { createAppAsyncThunk } from '../createAppAsyncThunk';

export interface HomeContent {
  banners: MediaItem[];
  top10: MediaItem[];
  latestMovies: MediaItem[];
  popularMovies: MediaItem[];
  payPerView: MediaItem[];
  tvShows: MediaItem[];
  liveChannels: LiveChannel[];
  homepageConfig: HomepageConfig;
}

interface HomeState extends HomeContent {
  continueWatching: MediaItem[];
  isLoading: boolean;
  hasLoaded: boolean;
  error: string | null;
}

const emptyHomeContent = (): HomeContent => ({
  banners: [],
  top10: [],
  latestMovies: [],
  popularMovies: [],
  payPerView: [],
  tvShows: [],
  liveChannels: [],
  homepageConfig: { sections: [], premium_card: { enabled: true, title: 'Go Premium', description: 'Unlock exclusive content, ad-free experience and more.', image_url: '', cta: 'Upgrade Now', url: '/pricing' }, latest_movies: [] },
});

const initialState: HomeState = {
  ...emptyHomeContent(),
  continueWatching: [],
  isLoading: false,
  hasLoaded: false,
  error: null,
};

function extractMediaList(section: unknown): MediaItem[] {
  const list = Array.isArray(section)
    ? section
    : section && typeof section === 'object' && 'data' in section && Array.isArray(section.data)
      ? section.data
      : [];
  return list.map(normalizeMediaItem);
}

function extractLiveChannels(data: { slider?: LiveChannel[]; category_data?: LiveCategory[] }): LiveChannel[] {
  const channels = [
    ...(Array.isArray(data.slider) ? data.slider : []),
    ...(Array.isArray(data.category_data)
      ? data.category_data.flatMap((category) => category.channel_data || [])
      : []),
  ];
  const seen = new Set<string>();

  return channels.flatMap((channel) => {
    const name = channel?.details?.name || channel?.name;
    const id = String(channel?.id ?? '');
    if (!id || !name || seen.has(id)) return [];
    seen.add(id);
    return [{
      ...channel,
      name,
      category: channel.details?.category || channel.category,
      poster_image:
        channel.poster_tv_image || channel.poster_image || channel.thumbnail_image ||
        channel.poster_url || channel.details?.poster_tv_image || channel.details?.poster_url || '',
    }];
  });
}

function errorMessage(result: PromiseSettledResult<unknown>, fallback: string): string | null {
  if (result.status === 'fulfilled') return null;
  return result.reason instanceof Error ? result.reason.message : fallback;
}

export const fetchHomePageContent = createAppAsyncThunk<HomeContent, void>(
  'home/fetchHomePageContent',
  async (_, { rejectWithValue, signal }) => {
    // Prioritize the hero request before requesting below-the-fold sections.
    const bannerResult = await api.getBannerData('home', signal)
      .then((value) => ({ status: 'fulfilled', value }) as const)
      .catch((reason: unknown) => ({ status: 'rejected', reason }) as const);

    const [dashboardResult, liveResult, tvShowsResult, configResult] = await Promise.allSettled([
      api.getDashboard(signal),
      api.getLiveTvDashboard(signal),
      api.getTvShows({ page: 1 }, signal),
      api.getHomepageConfig(signal),
    ]);

    const failures = [
      errorMessage(bannerResult, 'Could not load homepage banners.'),
      errorMessage(dashboardResult, 'Could not load the content dashboard.'),
      errorMessage(liveResult, 'Could not load live channels.'),
      errorMessage(tvShowsResult, 'Could not load TV shows.'),
      errorMessage(configResult, 'Could not load homepage layout.'),
    ].filter((message): message is string => Boolean(message));
    const hasAnySuccessfulRequest = [bannerResult, dashboardResult, liveResult, tvShowsResult, configResult]
      .some((result) => result.status === 'fulfilled');

    if (!hasAnySuccessfulRequest) {
      return rejectWithValue(failures[0] || 'Could not load homepage content.');
    }

    const dashboard = dashboardResult.status === 'fulfilled'
      ? (dashboardResult.value.data || dashboardResult.value) as DashboardData
      : {};
    const trendingConfig = configResult.status === 'fulfilled'
      ? configResult.value.sections.find((section) => section.key === 'latest-movies')
      : undefined;
    let latestMovies = Array.isArray(trendingConfig?.content_ids)
      ? extractMediaList(configResult.status === 'fulfilled' ? configResult.value.latest_movies : [])
      : extractMediaList(dashboard.latest_movie);
    if (latestMovies.length === 0 && !Array.isArray(trendingConfig?.content_ids)) {
      try {
        const movies = await api.getMovies({ page: 1 }, signal);
        latestMovies = extractMediaList(movies);
      } catch {
        // Keep this section empty when its optional catalog fallback is unavailable.
      }
    }

    const tvShows = tvShowsResult.status === 'fulfilled'
      ? extractMediaList(tvShowsResult.value)
      : extractMediaList(dashboard.tv_show);

    return {
      banners: bannerResult.status === 'fulfilled' ? bannerResult.value : [],
      top10: extractMediaList(dashboard.top_10),
      latestMovies,
      popularMovies: extractMediaList(dashboard.popular_movie),
      payPerView: extractMediaList(dashboard.pay_per_view),
      tvShows,
      liveChannels: liveResult.status === 'fulfilled' ? extractLiveChannels(liveResult.value) : [],
      homepageConfig: configResult.status === 'fulfilled' ? configResult.value : emptyHomeContent().homepageConfig,
    };
  },
  {
    condition: (_, { getState }) => !(getState() as { home: HomeState }).home.isLoading,
  }
);

export const fetchContinueWatching = createAppAsyncThunk<MediaItem[], void>(
  'home/fetchContinueWatching',
  async (_, { signal }) => {
    try {
      const response = await api.getContinueWatching(signal);
      return Array.isArray(response) ? response.map(normalizeMediaItem) : [];
    } catch {
      return [];
    }
  }
);

export const homeSlice = createSlice({
  name: 'home',
  initialState,
  reducers: {
    clearHomeError: (state) => {
      state.error = null;
    },
    hydrateHomeContent: (state, action: PayloadAction<HomeContent>) => {
      Object.assign(state, action.payload);
      state.hasLoaded = true;
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchHomePageContent.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchHomePageContent.fulfilled, (state, action) => {
        Object.assign(state, action.payload);
        state.isLoading = false;
        state.hasLoaded = true;
      })
      .addCase(fetchHomePageContent.rejected, (state, action) => {
        state.isLoading = false;
        state.hasLoaded = true;
        state.error = action.payload || action.error.message || 'Could not load homepage content.';
      })
      .addCase(fetchContinueWatching.fulfilled, (state, action) => {
        state.continueWatching = action.payload;
      });
  },
});

export const { clearHomeError, hydrateHomeContent } = homeSlice.actions;

export const selectHomeState = (state: { home: HomeState }) => state.home;
export const selectHomeBanners = (state: { home: HomeState }) => state.home.banners;
export const selectHomeTop10 = (state: { home: HomeState }) => state.home.top10;
export const selectHomeLatestMovies = (state: { home: HomeState }) => state.home.latestMovies;
export const selectHomePopularMovies = (state: { home: HomeState }) => state.home.popularMovies;
export const selectHomePayPerView = (state: { home: HomeState }) => state.home.payPerView;
export const selectHomeTvShows = (state: { home: HomeState }) => state.home.tvShows;
export const selectHomeLiveChannels = (state: { home: HomeState }) => state.home.liveChannels;
export const selectHomeContinueWatching = (state: { home: HomeState }) => state.home.continueWatching;
export const selectHomeLoading = (state: { home: HomeState }) => state.home.isLoading;
export const selectHomeError = (state: { home: HomeState }) => state.home.error;

export default homeSlice.reducer;
