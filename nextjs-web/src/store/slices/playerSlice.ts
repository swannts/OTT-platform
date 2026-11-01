import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';

interface PlayerState {
  activeItem: MediaItem | null;
  isPlaying: boolean;
  progress: number;
  volume: number;
  isMuted: boolean;
  isLoading: boolean;
}

const initialState: PlayerState = {
  activeItem: null,
  isPlaying: false,
  progress: 28,
  volume: 80,
  isMuted: false,
  isLoading: false,
};

export const loadActiveTrack = createAsyncThunk<MediaItem | null, void>(
  'player/loadActiveTrack',
  async (_, { rejectWithValue }) => {
    try {
      // 1. Try continue watching
      const cw = await api.getContinueWatching().catch(() => null);
      if (cw && Array.isArray(cw) && cw.length > 0) {
        return normalizeMediaItem(cw[0]);
      }

      // 2. Try dashboard top 10
      const dash = await api.getDashboard().catch(() => null);
      if (dash?.top_10?.data && Array.isArray(dash.top_10.data) && dash.top_10.data.length > 0) {
        return normalizeMediaItem(dash.top_10.data[0]);
      }

      // 3. Fallback to default featured title
      return {
        id: 1,
        name: 'The Life of Prophet Muhammad',
        type: 'tvshow',
        description: 'A journey of mercy, wisdom and guidance for all humanity.',
        banner_image:
          'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85',
        thumbnail_image:
          'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=200&auto=format&fit=crop&q=80',
        duration: '45:20',
        access: 'free',
      };
    } catch (err: any) {
      return rejectWithValue(err.message || 'Failed to load active track');
    }
  }
);

export const playerSlice = createSlice({
  name: 'player',
  initialState,
  reducers: {
    setActiveItem: (state, action: PayloadAction<MediaItem>) => {
      state.activeItem = action.payload;
      state.isPlaying = true;
    },
    togglePlay: (state) => {
      state.isPlaying = !state.isPlaying;
    },
    setIsPlaying: (state, action: PayloadAction<boolean>) => {
      state.isPlaying = action.payload;
    },
    setProgress: (state, action: PayloadAction<number>) => {
      state.progress = action.payload;
    },
    setVolume: (state, action: PayloadAction<number>) => {
      state.volume = action.payload;
      if (state.volume > 0) {
        state.isMuted = false;
      }
    },
    toggleMute: (state) => {
      state.isMuted = !state.isMuted;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(loadActiveTrack.pending, (state) => {
        state.isLoading = true;
      })
      .addCase(loadActiveTrack.fulfilled, (state, action: PayloadAction<MediaItem | null>) => {
        state.isLoading = false;
        if (action.payload) {
          state.activeItem = action.payload;
        }
      })
      .addCase(loadActiveTrack.rejected, (state) => {
        state.isLoading = false;
      });
  },
});

export const {
  setActiveItem,
  togglePlay,
  setIsPlaying,
  setProgress,
  setVolume,
  toggleMute,
} = playerSlice.actions;

export default playerSlice.reducer;

