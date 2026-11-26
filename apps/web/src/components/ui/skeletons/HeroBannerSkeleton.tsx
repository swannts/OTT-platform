import React from 'react';
import { Skeleton } from '../Skeleton';
import { cn } from '@/lib/utils';

export interface HeroBannerSkeletonProps {
  className?: string;
}

export const HeroBannerSkeleton: React.FC<HeroBannerSkeletonProps> = ({ className }) => {
  return (
    <div
      className={cn(
        'relative w-full h-[65vh] sm:h-[75vh] lg:h-[85vh] max-h-[820px] min-h-[480px] bg-[#0b1016] overflow-hidden',
        className
      )}
    >
      {/* Background Shimmer Layer */}
      <Skeleton className="absolute inset-0 w-full h-full rounded-none bg-white/[0.03]" />

      {/* Dark Vignettes & Gradients */}
      <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/50 to-transparent" />
      <div className="absolute inset-0 bg-gradient-to-r from-[#070a0e] via-[#070a0e]/60 to-transparent" />

      {/* Content Skeleton */}
      <div className="relative h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-end pb-16 sm:pb-24">
        <div className="max-w-2xl space-y-4">
          {/* Badge */}
          <div className="flex items-center space-x-2">
            <Skeleton className="h-6 w-24 rounded-full bg-[#E5B869]/15" />
            <Skeleton className="h-6 w-16 rounded-full" />
          </div>

          {/* Title */}
          <div className="space-y-2">
            <Skeleton className="h-10 sm:h-14 w-3/4 rounded-xl" />
            <Skeleton className="h-8 sm:h-10 w-1/2 rounded-xl" />
          </div>

          {/* Metadata Row */}
          <div className="flex items-center space-x-3 pt-1">
            <Skeleton className="h-4 w-14 rounded" />
            <Skeleton className="h-4 w-12 rounded" />
            <Skeleton className="h-4 w-20 rounded" />
            <Skeleton className="h-4 w-16 rounded" />
          </div>

          {/* Description */}
          <div className="space-y-2 pt-2">
            <Skeleton className="h-4 w-full rounded" />
            <Skeleton className="h-4 w-5/6 rounded" />
            <Skeleton className="h-4 w-2/3 rounded" />
          </div>

          {/* Action Buttons */}
          <div className="flex items-center space-x-3 pt-4">
            <Skeleton className="h-12 w-36 rounded-xl bg-[#E5B869]/20" />
            <Skeleton className="h-12 w-32 rounded-xl" />
            <Skeleton className="h-12 w-12 rounded-xl" />
          </div>
        </div>
      </div>
    </div>
  );
};

export default HeroBannerSkeleton;

