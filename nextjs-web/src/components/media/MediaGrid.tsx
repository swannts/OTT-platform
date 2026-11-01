'use client';

import React from 'react';
import { MediaItem } from '@/types';
import MediaCard from './MediaCard';
import { cn } from '@/lib/utils';

interface MediaGridProps {
  items: MediaItem[];
  isLoading?: boolean;
  aspectRatio?: 'poster' | 'backdrop';
  emptyState?: React.ReactNode;
  skeletonCount?: number;
  columnsClassName?: string;
  className?: string;
}

export default function MediaGrid({
  items,
  isLoading = false,
  aspectRatio = 'backdrop',
  emptyState,
  skeletonCount = 8,
  columnsClassName = 'grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
  className,
}: MediaGridProps) {
  if (isLoading) {
    return (
      <div className={cn('grid gap-4 sm:gap-6', columnsClassName, className)}>
        {[...Array(skeletonCount)].map((_, i) => (
          <div
            key={i}
            className={cn(
              'bg-white/5 rounded-2xl animate-pulse min-w-0',
              aspectRatio === 'poster' ? 'aspect-[2/3]' : 'aspect-[16/10]'
            )}
          />
        ))}
      </div>
    );
  }

  if (items.length === 0) {
    return emptyState ? <>{emptyState}</> : null;
  }

  return (
    <div className={cn('grid gap-4 sm:gap-6', columnsClassName, className)}>
      {items.map((item) => (
        <div key={item.id} className="min-w-0">
          <MediaCard item={item} layout="grid" aspectRatio={aspectRatio} />
        </div>
      ))}
    </div>
  );
}

