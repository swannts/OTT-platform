import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api } from '@/lib/api';
import { LiveChannel } from '@/types';

interface LiveTvState {
  channels: LiveChannel[];
  selectedChannel: LiveChannel | null;
  categories: any[];
  isLoading: boolean;
  error: string | null;
}

const initialState: LiveTvState = {
  channels: [],
  selectedChannel: null,
  categories: [],
  isLoading: false,
  error: null,
};

export const fetchLiveTvDashboard = createAsyncThunk<
  { channels: LiveChannel[]; categories: any[] },
  void
>('liveTv/fetchLiveTvDashboard', async (_, { rejectWithValue }) => {
  try {
    const res = await api.getLiveTvDashboard();
    const rawChannels: any[] = [];

    if (res?.slider && Array.isArray(res.slider)) {
      rawChannels.push(...res.slider);
    }

    if (res?.category_data && Array.isArray(res.category_data)) {
      res.category_data.forEach((cat: any) => {
        if (cat.channel_data && Array.isArray(cat.channel_data)) {
          rawChannels.push(...cat.channel_data);
        }
      });
    }

    // Deduplicate channels by id
    const seen = new Set();
    const unique: LiveChannel[] = [];
    for (const c of rawChannels) {
      if (!c || seen.has(c.id)) continue;
      seen.add(c.id);
      unique.push({
        id: c.id,
        name: c.details?.name || c.name || `Live Channel ${c.id}`,
        category: c.details?.category || c.category || 'Live Stream',
        description:
          c.details?.description ||
          c.description ||
          'Live broadcast from sacred shrines and news studios.',
        poster_image: c.poster_image || c.poster_tv_image || c.thumbnail_image,
        thumbnail_image: c.poster_tv_image || c.thumbnail_image || c.poster_image,
        stream_type: c.stream_type || 'HLS',
        server_url:
          c.server_url || c.server_url1 || 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        has_content_access: Boolean(c.details?.has_content_access ?? c.has_content_access ?? true),
      });
    }

    return {
      channels: unique,
      categories: res?.category_data || [],
    };
  } catch (err: any) {
    return rejectWithValue(err.message || 'Failed to fetch live TV dashboard');
  }
});

export const liveTvSlice = createSlice({
  name: 'liveTv',
  initialState,
  reducers: {
    setSelectedChannel: (state, action: PayloadAction<LiveChannel | null>) => {
      state.selectedChannel = action.payload;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchLiveTvDashboard.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchLiveTvDashboard.fulfilled, (state, action) => {
        state.isLoading = false;
        state.channels = action.payload.channels;
        state.categories = action.payload.categories;
        if (!state.selectedChannel && action.payload.channels.length > 0) {
          state.selectedChannel = action.payload.channels[0];
        }
      })
      .addCase(fetchLiveTvDashboard.rejected, (state, action) => {
        state.isLoading = false;
        state.error = (action.payload as string) || 'Failed to fetch live channels';
      });
  },
});

export const { setSelectedChannel } = liveTvSlice.actions;
export default liveTvSlice.reducer;

