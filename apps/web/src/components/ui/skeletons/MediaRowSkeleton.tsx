import React from 'react';
import { Skeleton } from '../Skeleton';
import { MediaCardSkeleton } from './MediaCardSkeleton';
import { cn } from '@/lib/utils';

export interface MediaRowSkeletonProps {
  cardCount?: number;
  aspectRatio?: 'poster' | 'backdrop';
  className?: string;
}

export const MediaRowSkeleton: React.FC<MediaRowSkeletonProps> = ({
  cardCount = 6,
  aspectRatio = 'poster',
  className,
}) => {
  return (
    <div className={cn('w-full space-y-4 my-6 sm:my-8', className)}>
      {/* Row Header */}
      <div className="flex items-center justify-between px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div className="flex items-center space-x-2.5">
          <Skeleton className="w-1.5 h-6 rounded-full bg-[#E5B869]/30" />
          <Skeleton className="h-6 w-44 rounded-lg" />
        </div>
        <Skeleton className="h-4 w-20 rounded" />
      </div>

      {/* Cards Row */}
      <div className="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto overflow-hidden">
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          {[...Array(cardCount)].map((_, i) => (
            <MediaCardSkeleton key={i} aspectRatio={aspectRatio} />
          ))}
        </div>
      </div>
    </div>
  );
};

export default MediaRowSkeleton;

