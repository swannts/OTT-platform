'use client';

import React, { useState, useEffect } from 'react';
import Image from 'next/image';
import { cn } from '@/lib/utils';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';
import { blurHashToDataURL } from '@/lib/blurhash';
import { Skeleton } from './Skeleton';

export const DEFAULT_ISLAMIC_FALLBACK = DEFAULT_FALLBACK_IMAGE;

export interface SafeImageProps extends Omit<React.ImgHTMLAttributes<HTMLImageElement>, 'src' | 'width' | 'height'> {
  src?: string | null;
  fallbackSrc?: string;
  blurhash?: string | null;
  aspectRatio?: 'poster' | 'backdrop' | 'square' | 'banner' | 'auto';
  containerClassName?: string;
  priority?: boolean;
  showSkeleton?: boolean;
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
  showSkeleton = false,
  sizes = '100vw',
  className,
  containerClassName,
  ...props
}: SafeImageProps) {
  const cleanTarget = typeof src === 'string' && src.trim() ? src.trim() : fallbackSrc;
  const [failedSource, setFailedSource] = useState<string | null>(null);
  const [loadedSource, setLoadedSource] = useState<string | null>(null);
  const [blurPlaceholder, setBlurPlaceholder] = useState<{ hash: string; dataUrl: string } | null>(null);
  const hasError = failedSource === cleanTarget;
  const imgSrc = hasError ? fallbackSrc : cleanTarget;
  const isLoaded = loadedSource === imgSrc;
  const blurDataUrl = blurPlaceholder && blurPlaceholder.hash === blurhash ? blurPlaceholder.dataUrl : null;

  // BlurHash decoding uses canvas, so generate the data URL after hydration.
  // Keeping it out of render makes the server HTML match the first client render.
  useEffect(() => {
    if (!blurhash) return;

    let cancelled = false;
    const frame = window.requestAnimationFrame(() => {
      const dataUrl = blurHashToDataURL(blurhash, 32, 32);
      if (!cancelled) setBlurPlaceholder(dataUrl ? { hash: blurhash, dataUrl } : null);
    });

    return () => {
      cancelled = true;
      window.cancelAnimationFrame(frame);
    };
  }, [blurhash]);
  // Safety Watchdog: If an image is cached or network stalls on page change,
  // never leave the user stuck on the blurhash color forever.
  useEffect(() => {
    if (isLoaded) return;

    const timer = setTimeout(() => {
      // If image didn't resolve in 2 seconds, gracefully show fallback and reveal
      if (imgSrc !== fallbackSrc) {
        setFailedSource(cleanTarget);
        setLoadedSource(fallbackSrc);
      } else {
        setLoadedSource(fallbackSrc);
      }
    }, 2000);

    return () => clearTimeout(timer);
  }, [imgSrc, isLoaded, fallbackSrc, cleanTarget]);

  const handleLoad = () => {
    setLoadedSource(imgSrc);
  };

  const handleError = () => {
    if (imgSrc !== fallbackSrc) {
      setFailedSource(cleanTarget);
    }
    setLoadedSource(fallbackSrc);
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

      {showSkeleton && !isLoaded ? (
        <Skeleton className="absolute inset-0 z-[5] h-full w-full rounded-none bg-white/[0.035] pointer-events-none" />
      ) : null}

      {/* 3. Primary Image Layer */}
      <Image
        src={imgSrc}
        alt={alt}
        fill
        sizes={sizes}
        priority={priority}
        onLoad={handleLoad}
        onError={handleError}
        decoding="async"
        loading={priority ? 'eager' : (props.loading || 'lazy')}
        className={cn(
          'z-10 object-cover transition-opacity duration-300 ease-in-out',
          isLoaded ? 'opacity-100' : 'opacity-0',
          className
        )}
        {...props}
      />
    </div>
  );
}
