import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';

interface TvShowsState {
  shows: MediaItem[];
  currentShow: MediaItem | null;
  episodes: any[];
  page: number;
  isLoading: boolean;
  error: string | null;
}

const initialState: TvShowsState = {
  shows: [],
  currentShow: null,
  episodes: [],
  page: 1,
  isLoading: false,
  error: null,
};

export const fetchTvShowsList = createAsyncThunk<MediaItem[], { page?: number } | undefined>(
  'tvShows/fetchTvShowsList',
  async (params = { page: 1 }, { rejectWithValue }) => {
    try {
      const res = await api.getTvShows(params);
      const list = Array.isArray(res) ? res : res?.data || [];
      return Array.isArray(list) ? list.map(normalizeMediaItem) : [];
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch TV series');
    }
  }
);

export const fetchTvShowDetails = createAsyncThunk<MediaItem, number | string>(
  'tvShows/fetchTvShowDetails',
  async (id, { rejectWithValue }) => {
    try {
      const res = await api.getContentDetails(id, 'tvshow');
      return normalizeMediaItem(res);
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch TV show details');
    }
  }
);

export const fetchTvShowEpisodes = createAsyncThunk<any[], number | string>(
  'tvShows/fetchTvShowEpisodes',
  async (id, { rejectWithValue }) => {
    try {
      const res = await api.getEpisodes(id);
      return Array.isArray(res) ? res : res?.data || [];
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch episodes');
    }
  }
);

export const tvShowsSlice = createSlice({
  name: 'tvShows',
  initialState,
  reducers: {
    setPage: (state, action: PayloadAction<number>) => {
      state.page = action.payload;
    },
    clearCurrentShow: (state) => {
      state.currentShow = null;
      state.episodes = [];
    },
  },
  extraReducers: (builder) => {
    // fetchTvShowsList
    builder
      .addCase(fetchTvShowsList.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchTvShowsList.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
        state.isLoading = false;
        state.shows = action.payload;
      })
      .addCase(fetchTvShowsList.rejected, (state, action) => {
        state.isLoading = false;
        state.error = (action.payload as string) || 'Failed to fetch TV series';
      });

    // fetchTvShowDetails
    builder
      .addCase(fetchTvShowDetails.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchTvShowDetails.fulfilled, (state, action: PayloadAction<MediaItem>) => {
        state.isLoading = false;
        state.currentShow = action.payload;
      })
      .addCase(fetchTvShowDetails.rejected, (state, action) => {
        state.isLoading = false;
        state.error = (action.payload as string) || 'Failed to fetch TV show details';
      });

    // fetchTvShowEpisodes
    builder.addCase(fetchTvShowEpisodes.fulfilled, (state, action: PayloadAction<any[]>) => {
      state.episodes = action.payload;
    });
  },
});

export const { setPage, clearCurrentShow } = tvShowsSlice.actions;
export default tvShowsSlice.reducer;

