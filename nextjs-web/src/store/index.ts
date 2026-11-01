import { configureStore } from '@reduxjs/toolkit';
import homeReducer from './slices/homeSlice';
import moviesReducer from './slices/moviesSlice';
import tvShowsReducer from './slices/tvShowsSlice';
import liveTvReducer from './slices/liveTvSlice';
import playerReducer from './slices/playerSlice';
import authReducer from './slices/authSlice';

export const store = configureStore({
  reducer: {
    home: homeReducer,
    movies: moviesReducer,
    tvShows: tvShowsReducer,
    liveTv: liveTvReducer,
    player: playerReducer,
    auth: authReducer,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: false,
    }),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;

