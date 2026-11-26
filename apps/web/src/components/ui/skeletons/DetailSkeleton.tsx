import React from 'react';
import { Skeleton } from '../Skeleton';
import { MediaCardSkeleton } from './MediaCardSkeleton';
import { cn } from '@/lib/utils';

export interface DetailSkeletonProps {
  className?: string;
  hasEpisodes?: boolean;
}

export const DetailSkeleton: React.FC<DetailSkeletonProps> = ({
  className,
  hasEpisodes = false,
}) => {
  return (
    <div className={cn('min-h-screen bg-[#070a0e] text-white pb-16', className)}>
      {/* Hero Backdrop Skeleton */}
      <div className="relative w-full h-[50vh] sm:h-[65vh] max-h-[640px] bg-[#0b1016] overflow-hidden">
        <Skeleton className="absolute inset-0 w-full h-full rounded-none bg-white/[0.03]" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/60 to-transparent" />
      </div>

      {/* Main Content Info */}
      <div className="relative -mt-32 sm:-mt-48 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col md:flex-row gap-8 items-start">
          {/* Poster Card */}
          <div className="w-48 sm:w-64 flex-shrink-0 hidden md:block">
            <Skeleton className="w-full aspect-[2/3] rounded-2xl border border-white/10 shadow-2xl" />
          </div>

          {/* Details */}
          <div className="flex-1 space-y-4 pt-4 sm:pt-12 w-full">
            <div className="flex items-center space-x-2">
              <Skeleton className="h-6 w-20 rounded-full bg-[#E5B869]/20" />
              <Skeleton className="h-6 w-16 rounded-full" />
              <Skeleton className="h-6 w-14 rounded-full" />
            </div>

            <Skeleton className="h-10 sm:h-12 w-3/4 rounded-xl" />

            <div className="flex items-center space-x-4 pt-1">
              <Skeleton className="h-5 w-16 rounded" />
              <Skeleton className="h-5 w-14 rounded" />
              <Skeleton className="h-5 w-20 rounded" />
              <Skeleton className="h-5 w-24 rounded" />
            </div>

            <div className="space-y-2 pt-2 max-w-3xl">
              <Skeleton className="h-4 w-full rounded" />
              <Skeleton className="h-4 w-5/6 rounded" />
              <Skeleton className="h-4 w-4/6 rounded" />
            </div>

            {/* Action Buttons */}
            <div className="flex items-center space-x-3 pt-4">
              <Skeleton className="h-12 w-40 rounded-xl bg-[#E5B869]/25" />
              <Skeleton className="h-12 w-36 rounded-xl" />
              <Skeleton className="h-12 w-12 rounded-xl" />
              <Skeleton className="h-12 w-12 rounded-xl" />
            </div>
          </div>
        </div>

        {/* Episode Grid if applicable */}
        {hasEpisodes && (
          <div className="mt-14 space-y-4">
            <div className="flex items-center space-x-2">
              <Skeleton className="w-1.5 h-6 rounded-full bg-[#E5B869]/30" />
              <Skeleton className="h-7 w-36 rounded-lg" />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="p-3 rounded-xl bg-white/[0.02] border border-white/5 space-y-3">
                  <Skeleton className="w-full aspect-video rounded-lg" />
                  <Skeleton className="h-4 w-3/4 rounded" />
                  <Skeleton className="h-3 w-1/2 rounded" />
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Similar Titles Row */}
        <div className="mt-16 space-y-4">
          <div className="flex items-center space-x-2">
            <Skeleton className="w-1.5 h-6 rounded-full bg-[#E5B869]/30" />
            <Skeleton className="h-7 w-48 rounded-lg" />
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {[...Array(6)].map((_, i) => (
              <MediaCardSkeleton key={i} aspectRatio="poster" />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default DetailSkeleton;

