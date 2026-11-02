'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { cn } from '@/lib/utils';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';
import { blurHashToDataURL } from '@/lib/blurhash';

export const DEFAULT_ISLAMIC_FALLBACK = DEFAULT_FALLBACK_IMAGE;

export interface SafeImageProps extends Omit<React.ImgHTMLAttributes<HTMLImageElement>, 'src'> {
  src?: string | null;
  fallbackSrc?: string;
  blurhash?: string | null;
  aspectRatio?: 'poster' | 'backdrop' | 'square' | 'banner' | 'auto';
  containerClassName?: string;
  priority?: boolean;
}

const aspectClasses = {
  poster: 'aspect-[2/3]',
  backdrop: 'aspect-[16/10]',
  square: 'aspect-square',
  banner: 'aspect-[21/9]',
  auto: '',
};

export default function SafeImage({
  src,
  alt = 'Image',
  fallbackSrc = DEFAULT_ISLAMIC_FALLBACK,
  blurhash,
  aspectRatio = 'auto',
  priority = false,
  className,
  containerClassName,
  ...props
}: SafeImageProps) {
  const cleanTarget = typeof src === 'string' && src.trim() ? src.trim() : fallbackSrc;
  const [imgSrc, setImgSrc] = useState<string>(cleanTarget);
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [blurDataUrl, setBlurDataUrl] = useState<string | null>(null);
  const imgRef = useRef<HTMLImageElement | null>(null);

  // Check if image is already loaded (crucial for cached images during route navigation)
  const checkImageComplete = useCallback((node: HTMLImageElement | null) => {
    if (!node) return;
    if (node.complete) {
      if (node.naturalWidth > 0) {
        setIsLoaded(true);
      } else if (node.currentSrc || node.src) {
        // Failed to load
        if (!hasError && imgSrc !== fallbackSrc) {
          setHasError(true);
          setImgSrc(fallbackSrc);
        } else {
          setIsLoaded(true);
        }
      }
    }
  }, [hasError, imgSrc, fallbackSrc]);

  // Callback ref so we immediately evaluate cached images upon DOM node creation
  const setRef = useCallback((node: HTMLImageElement | null) => {
    imgRef.current = node;
    checkImageComplete(node);
  }, [checkImageComplete]);

  // Sync state if src changes
  useEffect(() => {
    const nextSrc = typeof src === 'string' && src.trim() ? src.trim() : fallbackSrc;
    setImgSrc(nextSrc);
    setHasError(false);

    // If cached in memory, immediately mark loaded
    if (imgRef.current) {
      if (imgRef.current.complete && imgRef.current.naturalWidth > 0) {
        setIsLoaded(true);
        return;
      }
    }

    setIsLoaded(false);

    // Check again on next microtask/frame after React commits DOM changes
    const raf = requestAnimationFrame(() => {
      checkImageComplete(imgRef.current);
    });

    return () => cancelAnimationFrame(raf);
  }, [src, fallbackSrc, checkImageComplete]);

  // Decode blurhash to lightweight data URL on client after mount
  useEffect(() => {
    if (!blurhash) {
      setBlurDataUrl(null);
      return;
    }
    const dataUrl = blurHashToDataURL(blurhash, 32, 32);
    setBlurDataUrl(dataUrl);
  }, [blurhash]);

  // Safety Watchdog: If an image is cached or network stalls on page change,
  // never leave the user stuck on the blurhash color forever.
  useEffect(() => {
    if (isLoaded) return;

    // Check immediately in case DOM complete updated
    if (imgRef.current?.complete && imgRef.current.naturalWidth > 0) {
      setIsLoaded(true);
      return;
    }

    const timer = setTimeout(() => {
      if (imgRef.current?.complete) {
        if (imgRef.current.naturalWidth > 0) {
          setIsLoaded(true);
          return;
        }
      }

      // If image didn't resolve in 2 seconds, gracefully show fallback and reveal
      if (!hasError && imgSrc !== fallbackSrc) {
        setHasError(true);
        setImgSrc(fallbackSrc);
        setIsLoaded(true);
      } else {
        setIsLoaded(true);
      }
    }, 2000);

    return () => clearTimeout(timer);
  }, [imgSrc, isLoaded, hasError, fallbackSrc]);

  const handleLoad = () => {
    setIsLoaded(true);
  };

  const handleError = () => {
    if (!hasError && imgSrc !== fallbackSrc) {
      setHasError(true);
      setImgSrc(fallbackSrc);
      if (imgRef.current?.complete && imgRef.current.naturalWidth > 0) {
        setIsLoaded(true);
      }
    } else {
      setIsLoaded(true);
    }
  };

  return (
    <div
      className={cn(
        'relative overflow-hidden w-full h-full bg-[#0a0f16]',
        aspectClasses[aspectRatio],
        containerClassName
      )}
    >
      {/* 1. Base Fallback Background (always matches SSR and initial paint) */}
      <div
        className={cn(
          'absolute inset-0 z-0 bg-gradient-to-br from-[#0b1017] via-[#141b24] to-[#070b10] transition-opacity duration-500 pointer-events-none',
          isLoaded ? 'opacity-0' : 'opacity-100'
        )}
        aria-hidden="true"
      />

      {/* 2. BlurHash Placeholder Layer (Client-hydrated, cross-fades smoothly when available) */}
      {blurDataUrl ? (
        <div
          className={cn(
            'absolute inset-0 z-0 bg-cover bg-center filter blur-md scale-110 transition-opacity duration-500 ease-out pointer-events-none',
            isLoaded ? 'opacity-0' : 'opacity-100'
          )}
          style={{ backgroundImage: `url(${blurDataUrl})` }}
          aria-hidden="true"
        />
      ) : null}

      {/* 3. Primary Image Layer */}
      <img
        ref={setRef}
        src={imgSrc}
        alt={alt}
        onLoad={handleLoad}
        onError={handleError}
        decoding="async"
        loading={priority ? 'eager' : (props.loading || 'lazy')}
        className={cn(
          'relative z-10 w-full h-full object-cover transition-opacity duration-300 ease-in-out',
          isLoaded ? 'opacity-100' : 'opacity-0',
          className
        )}
        {...props}
      />
    </div>
  );
}
