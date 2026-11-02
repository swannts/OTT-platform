'use client';

import { useEffect, useRef, useState } from 'react';
import Hls from 'hls.js';

interface UseHlsStreamOptions {
  src: string;
  autoPlay?: boolean;
  onReady?: () => void;
  onError?: (err: unknown) => void;
}

export function useHlsStream(
  videoRef: React.RefObject<HTMLVideoElement | null>,
  { src, autoPlay = false, onReady, onError }: UseHlsStreamOptions
) {
  const hlsRef = useRef<Hls | null>(null);
  const [isHlsActive, setIsHlsActive] = useState(false);

  useEffect(() => {
    const video = videoRef.current;
    if (!video || !src) return;

    // Clean up previous HLS instance if any
    if (hlsRef.current) {
      hlsRef.current.destroy();
      hlsRef.current = null;
      setIsHlsActive(false);
    }

    const isM3U8 = src.includes('.m3u8');

    // 1. Try Hls.js library if supported and stream is M3U8
    if (isM3U8 && Hls.isSupported()) {
      const hls = new Hls({
        enableWorker: true,
        lowLatencyMode: true,
        backBufferLength: 90,
      });

      hlsRef.current = hls;
      setIsHlsActive(true);

      hls.loadSource(src);
      hls.attachMedia(video);

      hls.on(Hls.Events.MANIFEST_PARSED, () => {
        onReady?.();
        if (autoPlay) {
          video.play().catch(() => {});
        }
      });

      hls.on(Hls.Events.ERROR, (_, data) => {
        if (data.fatal) {
          switch (data.type) {
            case Hls.ErrorTypes.NETWORK_ERROR:
              hls.startLoad();
              break;
            case Hls.ErrorTypes.MEDIA_ERROR:
              hls.recoverMediaError();
              break;
            default:
              hls.destroy();
              onError?.(data);
              break;
          }
        }
      });

      return () => {
        hls.destroy();
        hlsRef.current = null;
        setIsHlsActive(false);
      };
    }

    // 2. Native HLS support (Safari iOS/macOS) or direct MP4/WebM file
    video.src = src;
    if (autoPlay) {
      video.play().catch(() => {});
    }

    return () => {
      video.src = '';
    };
  }, [src, autoPlay, onReady, onError, videoRef]);

  return {
    hls: hlsRef.current,
    isHlsActive,
  };
}

