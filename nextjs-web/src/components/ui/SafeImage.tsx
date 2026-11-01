'use client';

import React, { useState } from 'react';
import { cn } from '@/lib/utils';

export const DEFAULT_ISLAMIC_FALLBACK =
  'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?w=800&auto=format&fit=crop&q=80';

interface SafeImageProps extends Omit<React.ImgHTMLAttributes<HTMLImageElement>, 'src'> {
  src?: string | null;
  fallbackSrc?: string;
  aspectRatio?: 'poster' | 'backdrop' | 'square' | 'banner' | 'auto';
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
  aspectRatio = 'auto',
  className,
  ...props
}: SafeImageProps) {
  const [imgSrc, setImgSrc] = useState<string>(
    typeof src === 'string' && src.trim() ? src.trim() : fallbackSrc
  );
  const [hasError, setHasError] = useState(false);

  const handleError = () => {
    if (!hasError) {
      setHasError(true);
      setImgSrc(fallbackSrc);
    }
  };

  return (
    <img
      src={imgSrc}
      alt={alt}
      onError={handleError}
      loading="lazy"
      className={cn(
        'object-cover transition-transform duration-300',
        aspectClasses[aspectRatio],
        className
      )}
      {...props}
    />
  );
}
