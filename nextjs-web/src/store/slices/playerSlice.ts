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
      const topSection = (dash?.data?.top_10 || dash?.top_10) as unknown;
      const topList = Array.isArray(topSection)
        ? topSection
        : (typeof topSection === 'object' && topSection !== null && 'data' in topSection && Array.isArray((topSection as { data: unknown[] }).data)
            ? (topSection as { data: unknown[] }).data
            : []);
      if (topList.length > 0) {
        return normalizeMediaItem(topList[0]);
      }

      return null;
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to load active track';
      return rejectWithValue(message);
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

// Selectors
export const selectPlayerState = (state: { player: PlayerState }) => state.player;
export const selectActiveItem = (state: { player: PlayerState }) => state.player.activeItem;
export const selectIsPlaying = (state: { player: PlayerState }) => state.player.isPlaying;
export const selectPlayerProgress = (state: { player: PlayerState }) => state.player.progress;
export const selectPlayerVolume = (state: { player: PlayerState }) => state.player.volume;
export const selectIsMuted = (state: { player: PlayerState }) => state.player.isMuted;
export const selectPlayerLoading = (state: { player: PlayerState }) => state.player.isLoading;

export default playerSlice.reducer;

