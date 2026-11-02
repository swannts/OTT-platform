'use client';

import React, { useState, useEffect, useMemo } from 'react';
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
  className,
  containerClassName,
  ...props
}: SafeImageProps) {
  const initialTarget = typeof src === 'string' && src.trim() ? src.trim() : fallbackSrc;
  const [imgSrc, setImgSrc] = useState<string>(initialTarget);
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasError, setHasError] = useState(false);

  // Sync state if src changes
  useEffect(() => {
    const nextSrc = typeof src === 'string' && src.trim() ? src.trim() : fallbackSrc;
    setImgSrc(nextSrc);
    setIsLoaded(false);
    setHasError(false);
  }, [src, fallbackSrc]);

  // Decode blurhash to lightweight data URL (cached)
  const blurDataUrl = useMemo(() => {
    if (!blurhash) return null;
    return blurHashToDataURL(blurhash, 32, 32);
  }, [blurhash]);

  const handleError = () => {
    if (!hasError) {
      setHasError(true);
      setImgSrc(fallbackSrc);
      setIsLoaded(true);
    }
  };

  const handleLoad = () => {
    setIsLoaded(true);
  };

  return (
    <div
      className={cn(
        'relative overflow-hidden w-full h-full bg-[#0a0f16]',
        aspectClasses[aspectRatio],
        containerClassName
      )}
    >
      {/* 1. BlurHash Placeholder Layer (Cross-fades smoothly when real image loads) */}
      {blurDataUrl ? (
        <div
          className={cn(
            'absolute inset-0 z-0 bg-cover bg-center filter blur-md scale-110 transition-opacity duration-700 ease-out pointer-events-none',
            isLoaded ? 'opacity-0' : 'opacity-100'
          )}
          style={{ backgroundImage: `url(${blurDataUrl})` }}
          aria-hidden="true"
        />
      ) : (
        /* Shimmering Sacred Fallback if no blurhash is available */
        <div
          className={cn(
            'absolute inset-0 z-0 bg-gradient-to-br from-[#0b1017] via-[#141b24] to-[#070b10] transition-opacity duration-700 pointer-events-none',
            isLoaded ? 'opacity-0' : 'opacity-100'
          )}
          aria-hidden="true"
        />
      )}

      {/* 2. Primary Image Layer */}
      <img
        src={imgSrc}
        alt={alt}
        onLoad={handleLoad}
        onError={handleError}
        loading="lazy"
        className={cn(
          'relative z-10 w-full h-full object-cover transition-opacity duration-500 ease-in-out',
          isLoaded ? 'opacity-100' : 'opacity-0',
          className
        )}
        {...props}
      />
    </div>
  );
}
