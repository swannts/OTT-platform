import { configureStore } from '@reduxjs/toolkit';
import homeReducer from './slices/homeSlice';
import moviesReducer from './slices/moviesSlice';
import tvShowsReducer from './slices/tvShowsSlice';
import liveTvReducer from './slices/liveTvSlice';
import playerReducer from './slices/playerSlice';
import authReducer from './slices/authSlice';

export const makeStore = () =>
  configureStore({
    reducer: {
      home: homeReducer,
      movies: moviesReducer,
      tvShows: tvShowsReducer,
      liveTv: liveTvReducer,
      player: playerReducer,
      auth: authReducer,
    },
  });

export type AppStore = ReturnType<typeof makeStore>;
export type RootState = ReturnType<AppStore['getState']>;
export type AppDispatch = AppStore['dispatch'];

// Re-export centralized selectors
export * from './selectors';
