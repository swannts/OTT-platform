import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, LiveChannel } from '@/types';

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
export const fetchHomeBanners = createAsyncThunk<MediaItem[], void>(
  'home/fetchHomeBanners',
  async (_, { rejectWithValue }) => {
    try {
      const res = await api.getBannerData('home');
      if (Array.isArray(res) && res.length > 0) {
        return res;
      }
      return [];
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch home banners');
    }
  }
);

export const fetchDashboardData = createAsyncThunk<
  {
    top10: MediaItem[];
    latestMovies: MediaItem[];
    popularMovies: MediaItem[];
    payPerView: MediaItem[];
  },
  void
>('home/fetchDashboardData', async (_, { rejectWithValue }) => {
  try {
    const dashboard = await api.getDashboard();
    return {
      top10: Array.isArray(dashboard?.top_10?.data)
        ? dashboard.top_10.data.map(normalizeMediaItem)
        : [],
      latestMovies: Array.isArray(dashboard?.latest_movie?.data)
        ? dashboard.latest_movie.data.map(normalizeMediaItem)
        : [],
      popularMovies: Array.isArray(dashboard?.popular_movie?.data)
        ? dashboard.popular_movie.data.map(normalizeMediaItem)
        : [],
      payPerView: Array.isArray(dashboard?.pay_per_view?.data)
        ? dashboard.pay_per_view.data.map(normalizeMediaItem)
        : [],
    };
  } catch (err: any) {
    return rejectWithValue(err.message || 'Failed to fetch dashboard detail');
  }
});

export const fetchLiveChannels = createAsyncThunk<LiveChannel[], void>(
  'home/fetchLiveChannels',
  async (_, { rejectWithValue }) => {
    try {
      const liveData = await api.getLiveTvDashboard();
      if (liveData?.slider && Array.isArray(liveData.slider) && liveData.slider.length > 0) {
        return liveData.slider;
      }
      if (Array.isArray(liveData?.category_data) && liveData.category_data.length > 0) {
        return liveData.category_data.flatMap((c: any) => c.channel_data || []);
      }
      return [];
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch live tv channels');
    }
  }
);

export const fetchTvShows = createAsyncThunk<MediaItem[], void>(
  'home/fetchTvShows',
  async (_, { rejectWithValue }) => {
    try {
      const tvData = await api.getTvShows({ page: 1 });
      const tvList = Array.isArray(tvData) ? tvData : tvData?.data || [];
      return Array.isArray(tvList) ? tvList.map(normalizeMediaItem) : [];
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch tv shows');
    }
  }
);

export const fetchContinueWatching = createAsyncThunk<MediaItem[], void>(
  'home/fetchContinueWatching',
  async (_, { rejectWithValue }) => {
    try {
      const res = await api.getContinueWatching();
      return Array.isArray(res) ? res.map(normalizeMediaItem) : [];
    } catch {
      return [];
    }
  }
);

// Composite Thunk to load all Home Page content at once
export const loadAllHomeContent = createAsyncThunk(
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
      if (action.payload.banners && action.payload.banners.length > 0) {
        state.banners = action.payload.banners;
      }
      if (action.payload.top10 && action.payload.top10.length > 0) {
        state.top10 = action.payload.top10;
      }
      if (action.payload.latestMovies && action.payload.latestMovies.length > 0) {
        state.latestMovies = action.payload.latestMovies;
      }
      if (action.payload.popularMovies && action.payload.popularMovies.length > 0) {
        state.popularMovies = action.payload.popularMovies;
      }
      if (action.payload.payPerView && action.payload.payPerView.length > 0) {
        state.payPerView = action.payload.payPerView;
      }
      if (action.payload.tvShows && action.payload.tvShows.length > 0) {
        state.tvShows = action.payload.tvShows;
      }
      if (action.payload.liveChannels && action.payload.liveChannels.length > 0) {
        state.liveChannels = action.payload.liveChannels;
      }
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
        state.error = (action.payload as string) || 'Failed to load home content';
      });

    // fetchHomeBanners
    builder.addCase(fetchHomeBanners.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
      if (action.payload.length > 0) {
        state.banners = action.payload;
      }
    });

    // fetchDashboardData
    builder.addCase(fetchDashboardData.fulfilled, (state, action) => {
      if (action.payload.top10.length > 0) state.top10 = action.payload.top10;
      if (action.payload.latestMovies.length > 0) state.latestMovies = action.payload.latestMovies;
      if (action.payload.popularMovies.length > 0) state.popularMovies = action.payload.popularMovies;
      if (action.payload.payPerView.length > 0) state.payPerView = action.payload.payPerView;
    });

    // fetchLiveChannels
    builder.addCase(fetchLiveChannels.fulfilled, (state, action: PayloadAction<LiveChannel[]>) => {
      if (action.payload.length > 0) {
        state.liveChannels = action.payload;
      }
    });

    // fetchTvShows
    builder.addCase(fetchTvShows.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
      if (action.payload.length > 0) {
        state.tvShows = action.payload;
      }
    });

    // fetchContinueWatching
    builder.addCase(fetchContinueWatching.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
      state.continueWatching = action.payload;
    });
  },
});

export const { clearHomeError, setInitialHomeData } = homeSlice.actions;
export default homeSlice.reducer;

