// Centralized Redux Selectors
export {
  selectTvShowsState,
  selectTvShows,
  selectTvShowsLoading,
  selectTvShowsPage,
  selectCurrentTvShow,
  selectTvShowEpisodes,
  selectTvShowsError,
} from './slices/tvShowsSlice';

export {
  selectMoviesState,
  selectMovies,
  selectMoviesLoading,
  selectMoviesPage,
  selectCurrentMovie,
  selectMoviesError,
} from './slices/moviesSlice';

export {
  selectHomeState,
  selectHomeBanners,
  selectHomeTop10,
  selectHomeLatestMovies,
  selectHomePopularMovies,
  selectHomePayPerView,
  selectHomeTvShows,
  selectHomeLiveChannels,
  selectHomeContinueWatching,
  selectHomeLoading,
  selectHomeError,
} from './slices/homeSlice';

export {
  selectLiveTvState,
  selectLiveTvChannels,
  selectSelectedChannel,
  selectLiveTvCategories,
  selectLiveTvLoading,
  selectLiveTvError,
} from './slices/liveTvSlice';

export {
  selectPlayerState,
  selectActiveItem,
  selectIsPlaying,
  selectPlayerProgress,
  selectPlayerVolume,
  selectIsMuted,
  selectPlayerLoading,
} from './slices/playerSlice';

