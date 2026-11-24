import { useState, useEffect, useCallback } from 'react';

// ----------------------------------------------------------------------

export function useCarouselAutoPlay(mainApi) {
  const [isPlaying, setIsPlaying] = useState(false);

  const onClickAutoplay = useCallback(
    (callback) => {
      const autoplay = mainApi?.plugins()?.autoplay;
      if (!autoplay) return;

      const resetOrStop =
        autoplay.options.stopOnInteraction === false ? autoplay.reset : autoplay.stop;

      resetOrStop();
      callback();
    },
    [mainApi]
  );

  const onTogglePlay = useCallback(() => {
    const autoplay = mainApi?.plugins()?.autoplay;
    if (!autoplay) return;

    const playOrStop = autoplay.isPlaying() ? autoplay.stop : autoplay.play;
    playOrStop();
  }, [mainApi]);

  const handlePlay = useCallback(() => setIsPlaying(true), []);
  const handleStop = useCallback(() => setIsPlaying(false), []);
  const handleReInit = useCallback(() => setIsPlaying(false), []);

  useEffect(() => {
    const autoplay = mainApi?.plugins()?.autoplay;
    if (!autoplay) return;

    const frame = window.requestAnimationFrame(() => setIsPlaying(autoplay.isPlaying()));
    mainApi
      .on('autoplay:play', handlePlay)
      .on('autoplay:stop', handleStop)
      .on('reInit', handleReInit);

    return () => {
      window.cancelAnimationFrame(frame);
      mainApi.off('autoplay:play', handlePlay);
      mainApi.off('autoplay:stop', handleStop);
      mainApi.off('reInit', handleReInit);
    };
  }, [handlePlay, handleReInit, handleStop, mainApi]);

  return { isPlaying, onTogglePlay, onClickAutoplay };
}
