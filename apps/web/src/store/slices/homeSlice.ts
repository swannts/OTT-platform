import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { createAppAsyncThunk } from '../createAppAsyncThunk';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, LiveChannel, LiveCategory } from '@/types';

interface HomeState {
  banners: MediaItem[];
  top10: MediaItem[];
  latestMovies: MediaItem[];
  popularMovies: MediaItem[];
  payPerView: MediaItem[];
  tvShows: MediaItem[];
  liveChannels: LiveChannel[];
  continueWatching: MediaItem[];
  isLoading: boolean;
  error: string | null;
}

const initialState: HomeState = {
  banners: [],
  top10: [],
  latestMovies: [],
  popularMovies: [],
  payPerView: [],
  tvShows: [],
  liveChannels: [],
  continueWatching: [],
  isLoading: false,
  error: null,
};

// Async Thunks using Redux Thunk
export const fetchHomeBanners = createAppAsyncThunk<MediaItem[], void>(
  'home/fetchHomeBanners',
  async (_, { rejectWithValue, signal }) => {
    try {
      const res = await api.getBannerData('home', signal);
      if (Array.isArray(res) && res.length > 0) {
        return res;
      }
      return [];
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch home banners';
      return rejectWithValue(message);
    }
  }
);

export const fetchDashboardData = createAppAsyncThunk<
  {
    top10: MediaItem[];
    latestMovies: MediaItem[];
    popularMovies: MediaItem[];
    payPerView: MediaItem[];
  },
  void
>('home/fetchDashboardData', async (_, { rejectWithValue, signal }) => {
  try {
    const dashboard = await api.getDashboard(signal);
    const extractList = (section: unknown): unknown[] => {
      if (!section) return [];
      if (Array.isArray(section)) return section;
      if (typeof section === 'object' && section !== null && 'data' in section && Array.isArray((section as { data: unknown[] }).data)) {
        return (section as { data: unknown[] }).data;
      }
      return [];
    };

    return {
      top10: extractList(dashboard?.top_10).map(normalizeMediaItem),
      latestMovies: extractList(dashboard?.latest_movie).map(normalizeMediaItem),
      popularMovies: extractList(dashboard?.popular_movie).map(normalizeMediaItem),
      payPerView: extractList(dashboard?.pay_per_view).map(normalizeMediaItem),
    };
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to fetch dashboard detail';
    return rejectWithValue(message);
  }
});

export const fetchLiveChannels = createAppAsyncThunk<LiveChannel[], void>(
  'home/fetchLiveChannels',
  async (_, { rejectWithValue, signal }) => {
    try {
      const liveData = await api.getLiveTvDashboard(signal);
      if (liveData?.slider && Array.isArray(liveData.slider) && liveData.slider.length > 0) {
        return liveData.slider;
      }
      if (Array.isArray(liveData?.category_data) && liveData.category_data.length > 0) {
        return liveData.category_data.flatMap((c: LiveCategory) => c.channel_data || []);
      }
      return [];
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch live tv channels';
      return rejectWithValue(message);
    }
  }
);

export const fetchTvShows = createAppAsyncThunk<MediaItem[], void>(
  'home/fetchTvShows',
  async (_, { rejectWithValue, signal }) => {
    try {
      const tvData = await api.getTvShows({ page: 1 }, signal);
      const tvList = Array.isArray(tvData) ? tvData : tvData?.data || [];
      return Array.isArray(tvList) ? tvList.map(normalizeMediaItem) : [];
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch tv shows';
      return rejectWithValue(message);
    }
  }
);

export const fetchContinueWatching = createAppAsyncThunk<MediaItem[], void>(
  'home/fetchContinueWatching',
  async (_, { signal }) => {
    try {
      const res = await api.getContinueWatching(signal);
      return Array.isArray(res) ? res.map(normalizeMediaItem) : [];
    } catch {
      return [];
    }
  }
);

// Composite Thunk to load all Home Page content at once
export const loadAllHomeContent = createAppAsyncThunk<void, void>(
  'home/loadAllHomeContent',
  async (_, { dispatch }) => {
    await Promise.allSettled([
      dispatch(fetchHomeBanners()),
      dispatch(fetchDashboardData()),
      dispatch(fetchLiveChannels()),
      dispatch(fetchTvShows()),
      dispatch(fetchContinueWatching()),
    ]);
  }
);

export const homeSlice = createSlice({
  name: 'home',
  initialState,
  reducers: {
    clearHomeError: (state) => {
      state.error = null;
    },
    setInitialHomeData: (state, action: PayloadAction<Partial<HomeState>>) => {
      if (action.payload.banners !== undefined) state.banners = action.payload.banners;
      if (action.payload.top10 !== undefined) state.top10 = action.payload.top10;
      if (action.payload.latestMovies !== undefined) state.latestMovies = action.payload.latestMovies;
      if (action.payload.popularMovies !== undefined) state.popularMovies = action.payload.popularMovies;
      if (action.payload.payPerView !== undefined) state.payPerView = action.payload.payPerView;
      if (action.payload.tvShows !== undefined) state.tvShows = action.payload.tvShows;
      if (action.payload.liveChannels !== undefined) state.liveChannels = action.payload.liveChannels;
    },
  },
  extraReducers: (builder) => {
    // loadAllHomeContent
    builder
      .addCase(loadAllHomeContent.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(loadAllHomeContent.fulfilled, (state) => {
        state.isLoading = false;
      })
      .addCase(loadAllHomeContent.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload || action.error.message || 'Failed to load home content';
      });

    // fetchHomeBanners
    builder.addCase(fetchHomeBanners.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
      state.banners = action.payload;
    });

    // fetchDashboardData
    builder.addCase(fetchDashboardData.fulfilled, (state, action) => {
      state.top10 = action.payload.top10;
      state.latestMovies = action.payload.latestMovies;
      state.popularMovies = action.payload.popularMovies;
      state.payPerView = action.payload.payPerView;
    });

    // fetchLiveChannels
    builder.addCase(fetchLiveChannels.fulfilled, (state, action: PayloadAction<LiveChannel[]>) => {
      state.liveChannels = action.payload;
    });

    // fetchTvShows
    builder.addCase(fetchTvShows.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
      state.tvShows = action.payload;
    });

    // fetchContinueWatching
    builder.addCase(fetchContinueWatching.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
      state.continueWatching = action.payload;
    });
  },
});

export const { clearHomeError, setInitialHomeData } = homeSlice.actions;

// Selectors
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
