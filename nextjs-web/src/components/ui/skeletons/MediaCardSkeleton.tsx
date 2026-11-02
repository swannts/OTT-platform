import React from 'react';
import { Skeleton } from '../Skeleton';
import { cn } from '@/lib/utils';

export interface MediaCardSkeletonProps {
  aspectRatio?: 'poster' | 'backdrop';
  className?: string;
  showMeta?: boolean;
}

export const MediaCardSkeleton: React.FC<MediaCardSkeletonProps> = ({
  aspectRatio = 'backdrop',
  className,
  showMeta = true,
}) => {
  return (
    <div className={cn('flex flex-col space-y-2 w-full min-w-0', className)}>
      <Skeleton
        className={cn(
          'w-full rounded-2xl border border-white/[0.04]',
          aspectRatio === 'poster' ? 'aspect-[2/3]' : 'aspect-[16/10]'
        )}
      />
      {showMeta && (
        <div className="space-y-1.5 pt-1 px-0.5">
          <Skeleton className="h-4 w-4/5 rounded-md" />
          <div className="flex items-center space-x-2">
            <Skeleton className="h-3 w-12 rounded" />
            <Skeleton className="h-3 w-16 rounded" />
          </div>
        </div>
      )}
    </div>
  );
};

export default MediaCardSkeleton;

