import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';

interface MoviesState {
  movies: MediaItem[];
  currentMovie: MediaItem | null;
  page: number;
  isLoading: boolean;
  error: string | null;
}

const initialState: MoviesState = {
  movies: [],
  currentMovie: null,
  page: 1,
  isLoading: false,
  error: null,
};

export const fetchMoviesList = createAsyncThunk<MediaItem[], { page?: number } | undefined>(
  'movies/fetchMoviesList',
  async (params = { page: 1 }, { rejectWithValue }) => {
    try {
      const res = await api.getMovies(params);
      const list = Array.isArray(res) ? res : res?.data || [];
      return Array.isArray(list) ? list.map(normalizeMediaItem) : [];
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch movies');
    }
  }
);

export const fetchMovieDetails = createAsyncThunk<MediaItem, number | string>(
  'movies/fetchMovieDetails',
  async (id, { rejectWithValue }) => {
    try {
      const res = await api.getContentDetails(id, 'movie');
      return normalizeMediaItem(res);
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to fetch movie details');
    }
  }
);

export const moviesSlice = createSlice({
  name: 'movies',
  initialState,
  reducers: {
    setPage: (state, action: PayloadAction<number>) => {
      state.page = action.payload;
    },
    clearCurrentMovie: (state) => {
      state.currentMovie = null;
    },
  },
  extraReducers: (builder) => {
    // fetchMoviesList
    builder
      .addCase(fetchMoviesList.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchMoviesList.fulfilled, (state, action: PayloadAction<MediaItem[]>) => {
        state.isLoading = false;
        state.movies = action.payload;
      })
      .addCase(fetchMoviesList.rejected, (state, action) => {
        state.isLoading = false;
        state.error = (action.payload as string) || 'Failed to fetch movies';
      });

    // fetchMovieDetails
    builder
      .addCase(fetchMovieDetails.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchMovieDetails.fulfilled, (state, action: PayloadAction<MediaItem>) => {
        state.isLoading = false;
        state.currentMovie = action.payload;
      })
      .addCase(fetchMovieDetails.rejected, (state, action) => {
        state.isLoading = false;
        state.error = (action.payload as string) || 'Failed to fetch movie details';
      });
  },
});

export const { setPage, clearCurrentMovie } = moviesSlice.actions;
export default moviesSlice.reducer;

