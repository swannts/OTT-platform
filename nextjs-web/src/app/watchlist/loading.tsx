import React from 'react';
import { Skeleton, MediaCardSkeleton } from '@/components/ui';

export default function WatchlistLoading() {
  return (
    <div className="min-h-screen bg-[#070a0e] text-white pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
      <div className="space-y-2">
        <div className="flex items-center space-x-2.5">
          <Skeleton className="w-1.5 h-7 rounded-full bg-[#E5B869]/30" />
          <Skeleton className="h-8 w-48 rounded-xl" />
        </div>
        <Skeleton className="h-4 w-72 rounded" />
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 pt-4">
        {[...Array(12)].map((_, i) => (
          <MediaCardSkeleton key={i} aspectRatio="poster" />
        ))}
      </div>
    </div>
  );
}

