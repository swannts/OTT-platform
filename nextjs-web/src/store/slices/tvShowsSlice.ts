import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, Episode } from '@/types';

export interface TvShowsCursorPayload {
  items: MediaItem[];
  nextCursor: string | null;
  hasMore: boolean;
}

interface TvShowsState {
  shows: MediaItem[];
  currentShow: MediaItem | null;
  episodes: Episode[];
  page: number;
  cursor: string | null;
  hasMore: boolean;
  isLoading: boolean;
  isLoadingMore: boolean;
  error: string | null;
}

const initialState: TvShowsState = {
  shows: [],
  currentShow: null,
  episodes: [],
  page: 1,
  cursor: null,
  hasMore: true,
  isLoading: false,
  isLoadingMore: false,
  error: null,
};

export const fetchTvShowsList = createAsyncThunk<MediaItem[], { page?: number } | undefined>(
  'tvShows/fetchTvShowsList',
  async (params = { page: 1 }, { rejectWithValue }) => {
    try {
      const res = await api.getTvShows(params);
      const list = Array.isArray(res) ? res : res?.data || [];
      return Array.isArray(list) ? list.map(normalizeMediaItem) : [];
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch TV series';
      return rejectWithValue(message);
    }
  }
);

export const fetchNextTvShowsCursor = createAsyncThunk<
  TvShowsCursorPayload,
  { cursor?: string | null; per_page?: number } | undefined
>(
  'tvShows/fetchNextTvShowsCursor',
  async (params = {}, { rejectWithValue }) => {
    try {
      const res = await api.getTvShows({
        cursor: params.cursor,
        per_page: params.per_page || 12,
      });
      const list = Array.isArray(res) ? res : res?.data || [];
      const items = Array.isArray(list) ? list.map(normalizeMediaItem) : [];
      const nextCursor = !Array.isArray(res) && typeof res?.next_cursor === 'string' ? res.next_cursor : null;
      const hasMore = !Array.isArray(res) ? Boolean(res?.has_more ?? res?.hasMore ?? false) : false;
      return { items, nextCursor, hasMore };
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch TV series';
      return rejectWithValue(message);
    }
  }
);

export const fetchTvShowDetails = createAsyncThunk<MediaItem, number | string>(
  'tvShows/fetchTvShowDetails',
  async (id, { rejectWithValue }) => {
    try {
      const res = await api.getContentDetails(id, 'tvshow');
      return normalizeMediaItem(res);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch TV show details';
      return rejectWithValue(message);
    }
  }
);

export const fetchTvShowEpisodes = createAsyncThunk<Episode[], number | string>(
  'tvShows/fetchTvShowEpisodes',
  async (id, { rejectWithValue }) => {
    try {
      const res = await api.getEpisodes(id);
      const list = Array.isArray(res) ? res : res?.data || [];
      return (list as Episode[]) || [];
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to fetch episodes';
      return rejectWithValue(message);
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
    setInitialShows: (state, action: PayloadAction<MediaItem[]>) => {
      state.shows = action.payload;
    },
    setInitialCursorData: (
      state,
      action: PayloadAction<{ shows: MediaItem[]; nextCursor: string | null; hasMore: boolean }>
    ) => {
      state.shows = action.payload.shows;
      state.cursor = action.payload.nextCursor;
      state.hasMore = action.payload.hasMore;
      state.isLoading = false;
      state.isLoadingMore = false;
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

    // fetchNextTvShowsCursor
    builder
      .addCase(fetchNextTvShowsCursor.pending, (state) => {
        state.isLoadingMore = true;
        state.error = null;
      })
      .addCase(fetchNextTvShowsCursor.fulfilled, (state, action: PayloadAction<TvShowsCursorPayload>) => {
        state.isLoadingMore = false;
        const existingIds = new Set(state.shows.map((s) => s.id));
        const newItems = action.payload.items.filter((s) => !existingIds.has(s.id));
        state.shows = [...state.shows, ...newItems];
        state.cursor = action.payload.nextCursor;
        state.hasMore = action.payload.hasMore;
      })
      .addCase(fetchNextTvShowsCursor.rejected, (state, action) => {
        state.isLoadingMore = false;
        state.error = (action.payload as string) || 'Failed to fetch more TV series';
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
    builder.addCase(fetchTvShowEpisodes.fulfilled, (state, action: PayloadAction<Episode[]>) => {
      state.episodes = action.payload;
    });
  },
});

export const { setPage, clearCurrentShow, setInitialShows, setInitialCursorData } = tvShowsSlice.actions;

// Selectors
export const selectTvShowsState = (state: { tvShows: TvShowsState }) => state.tvShows;
export const selectTvShows = (state: { tvShows: TvShowsState }) => state.tvShows.shows;
export const selectTvShowsLoading = (state: { tvShows: TvShowsState }) => state.tvShows.isLoading;
export const selectTvShowsLoadingMore = (state: { tvShows: TvShowsState }) => state.tvShows.isLoadingMore;
export const selectTvShowsHasMore = (state: { tvShows: TvShowsState }) => state.tvShows.hasMore;
export const selectTvShowsCursor = (state: { tvShows: TvShowsState }) => state.tvShows.cursor;
export const selectTvShowsPage = (state: { tvShows: TvShowsState }) => state.tvShows.page;
export const selectCurrentTvShow = (state: { tvShows: TvShowsState }) => state.tvShows.currentShow;
export const selectTvShowEpisodes = (state: { tvShows: TvShowsState }) => state.tvShows.episodes;
export const selectTvShowsError = (state: { tvShows: TvShowsState }) => state.tvShows.error;

export default tvShowsSlice.reducer;
