import { useState, useEffect, useCallback } from 'react';

// ----------------------------------------------------------------------

export function useCarouselAutoScroll(mainApi) {
  const [isPlaying, setIsPlaying] = useState(false);

  const onClickAutoplay = useCallback(
    (callback) => {
      const autoScroll = mainApi?.plugins()?.autoScroll;
      if (!autoScroll) return;

      const resetOrStop =
        autoScroll.options.stopOnInteraction === false ? autoScroll.reset : autoScroll.stop;

      resetOrStop();
      callback();
    },
    [mainApi]
  );

  const onTogglePlay = useCallback(() => {
    const autoScroll = mainApi?.plugins()?.autoScroll;
    if (!autoScroll) return;

    const playOrStop = autoScroll.isPlaying() ? autoScroll.stop : autoScroll.play;
    playOrStop();
  }, [mainApi]);

  const handlePlay = useCallback(() => setIsPlaying(true), []);
  const handleStop = useCallback(() => setIsPlaying(false), []);
  const handleReInit = useCallback(() => setIsPlaying(false), []);

  useEffect(() => {
    const autoScroll = mainApi?.plugins()?.autoScroll;
    if (!autoScroll) return;

    const frame = window.requestAnimationFrame(() => setIsPlaying(autoScroll.isPlaying()));
    mainApi
      .on('autoScroll:play', handlePlay)
      .on('autoScroll:stop', handleStop)
      .on('reInit', handleReInit);

    return () => {
      window.cancelAnimationFrame(frame);
      mainApi.off('autoScroll:play', handlePlay);
      mainApi.off('autoScroll:stop', handleStop);
      mainApi.off('reInit', handleReInit);
    };
  }, [handlePlay, handleReInit, handleStop, mainApi]);

  return { isPlaying, onTogglePlay, onClickAutoplay };
}
