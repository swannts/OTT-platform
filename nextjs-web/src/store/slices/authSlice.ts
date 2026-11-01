import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { api } from '@/lib/api';
import { User, SubscriptionStatus } from '@/types';

interface AuthState {
  user: User | null;
  token: string | null;
  subscription: SubscriptionStatus | null;
  isLoading: boolean;
  error: string | null;
}

const initialState: AuthState = {
  user: null,
  token: typeof window !== 'undefined' ? localStorage.getItem('token') : null,
  subscription: null,
  isLoading: false,
  error: null,
};

export const loginUser = createAsyncThunk<
  { user: User; token: string; subscription?: SubscriptionStatus | null },
  { email: string; password: string }
>('auth/loginUser', async (credentials, { rejectWithValue }) => {
  try {
    const res = await api.login({
      email: credentials.email,
      password: credentials.password,
    });
    if (res?.token) {
      if (typeof window !== 'undefined') {
        localStorage.setItem('token', res.token);
      }
    }
    const user: User = {
      id: res?.id || 1,
      name: res?.name || 'User',
      email: res?.email || credentials.email,
      user_type: res?.user_type,
      is_subscribe: res?.is_subscribe,
    };
    return {
      user,
      token: res?.token || '',
      subscription: null,
    };
  } catch (err: any) {
    return rejectWithValue(err.message || 'Login failed');
  }
});

export const logoutUser = createAsyncThunk<void, void>(
  'auth/logoutUser',
  async () => {
    try {
      await api.logout();
    } catch {
      // Ignore network errors on logout
    } finally {
      if (typeof window !== 'undefined') {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      }
    }
  }
);

export const fetchUserProfile = createAsyncThunk<
  { user: User; subscription?: SubscriptionStatus | null },
  void
>('auth/fetchUserProfile', async (_, { rejectWithValue }) => {
  try {
    const profile = await api.getProfile();
    const sub = await api.getUserSubscription().catch(() => null);
    return {
      user: profile,
      subscription: sub,
    };
  } catch (err: any) {
    return rejectWithValue(err.message || 'Failed to fetch user profile');
  }
});

export const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser: (state, action: PayloadAction<User | null>) => {
      state.user = action.payload;
    },
    clearAuthError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    // loginUser
    builder
      .addCase(loginUser.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(loginUser.fulfilled, (state, action) => {
        state.isLoading = false;
        state.user = action.payload.user;
        state.token = action.payload.token;
        if (action.payload.subscription) {
          state.subscription = action.payload.subscription;
        }
      })
      .addCase(loginUser.rejected, (state, action) => {
        state.isLoading = false;
        state.error = (action.payload as string) || 'Authentication failed';
      });

    // logoutUser
    builder.addCase(logoutUser.fulfilled, (state) => {
      state.user = null;
      state.token = null;
      state.subscription = null;
      state.error = null;
    });

    // fetchUserProfile
    builder.addCase(fetchUserProfile.fulfilled, (state, action) => {
      state.user = action.payload.user;
      if (action.payload.subscription) {
        state.subscription = action.payload.subscription;
      }
    });
  },
});

export const { setUser, clearAuthError } = authSlice.actions;
export default authSlice.reducer;
