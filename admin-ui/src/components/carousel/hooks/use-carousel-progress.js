import { useState, useEffect, useCallback } from 'react';

// ----------------------------------------------------------------------

export function useCarouselProgress(mainApi) {
  const [scrollProgress, setScrollProgress] = useState(0);

  const onScroll = useCallback((_mainApi) => {
    const progress = Math.max(0, Math.min(1, _mainApi.scrollProgress()));

    setScrollProgress(progress * 100);
  }, []);

  useEffect(() => {
    if (!mainApi) return;

    const frame = window.requestAnimationFrame(() => onScroll(mainApi));
    mainApi.on('reInit', onScroll);
    mainApi.on('scroll', onScroll);

    return () => {
      window.cancelAnimationFrame(frame);
      mainApi.off('reInit', onScroll);
      mainApi.off('scroll', onScroll);
    };
  }, [mainApi, onScroll]);

  return { value: scrollProgress };
}
