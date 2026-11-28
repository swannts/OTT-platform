'use client';

import React, { useState, useEffect, useRef } from 'react';
import Image from 'next/image';
import { cn } from '@/lib/utils';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';
import { blurHashToDataURL } from '@/lib/blurhash';
import { Skeleton } from './Skeleton';

export const DEFAULT_ISLAMIC_FALLBACK = DEFAULT_FALLBACK_IMAGE;

export interface SafeImageProps extends Omit<React.ImgHTMLAttributes<HTMLImageElement>, 'src' | 'width' | 'height'> {
  src?: string | null;
  fallbackSrc?: string | null;
  blurhash?: string | null;
  aspectRatio?: 'poster' | 'backdrop' | 'square' | 'banner' | 'auto';
  containerClassName?: string;
  priority?: boolean;
  showSkeleton?: boolean;
  unoptimized?: boolean;
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
  fallbackSrc,
  blurhash,
  aspectRatio = 'auto',
  priority = false,
  showSkeleton = false,
  unoptimized = false,
  sizes = '100vw',
  className,
  containerClassName,
  ...props
}: SafeImageProps) {
  const resolvedFallback = fallbackSrc === undefined ? DEFAULT_ISLAMIC_FALLBACK : fallbackSrc;
  const cleanTarget = typeof src === 'string' && src.trim() ? src.trim() : resolvedFallback || '';
  const [failedSource, setFailedSource] = useState<string | null>(null);
  const [loadedSource, setLoadedSource] = useState<string | null>(null);
  const [blurPlaceholder, setBlurPlaceholder] = useState<{ hash: string; dataUrl: string } | null>(null);
  const imageRef = useRef<HTMLImageElement>(null);
  const hasError = failedSource === cleanTarget;
  const imgSrc = hasError ? resolvedFallback || '' : cleanTarget;
  const isLoaded = Boolean(imgSrc) && loadedSource === imgSrc;
  const usesLocalhostSource = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\//i.test(cleanTarget);
  const blurDataUrl = blurPlaceholder && blurPlaceholder.hash === blurhash ? blurPlaceholder.dataUrl : null;

  // An eager image can finish loading from the server-rendered HTML before
  // React hydrates and installs onLoad. Detect that completed request here.
  useEffect(() => {
    const image = imageRef.current;
    if (imgSrc && image?.complete && image.naturalWidth > 0) {
      setLoadedSource(imgSrc);
    }
  }, [imgSrc]);

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
    if (!imgSrc) return;
    if (isLoaded) return;

    const timer = setTimeout(() => {
      // If the source does not resolve, use the configured fallback when present.
      if (resolvedFallback && imgSrc !== resolvedFallback) {
        setFailedSource(cleanTarget);
        setLoadedSource(resolvedFallback);
      } else if (!resolvedFallback) {
        setFailedSource(cleanTarget);
      } else {
        setLoadedSource(resolvedFallback);
      }
    }, 2000);

    return () => clearTimeout(timer);
  }, [imgSrc, isLoaded, resolvedFallback, cleanTarget]);

  const handleLoad = () => {
    setLoadedSource(imgSrc);
  };

  const handleError = () => {
    if (resolvedFallback && imgSrc !== resolvedFallback) {
      setFailedSource(cleanTarget);
      setLoadedSource(resolvedFallback);
    } else if (!resolvedFallback) {
      setFailedSource(cleanTarget);
      setLoadedSource(null);
    } else {
      setLoadedSource(resolvedFallback);
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

      {showSkeleton && !isLoaded ? (
        <Skeleton className="absolute inset-0 z-[5] h-full w-full rounded-none bg-white/[0.035] pointer-events-none" />
      ) : null}

      {/* 3. Primary Image Layer */}
      {imgSrc ? (
        <Image
          ref={imageRef}
          src={imgSrc}
          alt={alt}
          fill
          sizes={sizes}
          priority={priority}
          unoptimized={unoptimized || usesLocalhostSource}
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
      ) : null}
    </div>
  );
}
