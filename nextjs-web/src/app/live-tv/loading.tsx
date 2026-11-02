import React from 'react';
import { Skeleton, MediaCardSkeleton } from '@/components/ui';

export default function LiveTvLoading() {
  return (
    <div className="min-h-screen bg-[#070a0e] text-white pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
      {/* Page Title */}
      <div className="space-y-2">
        <div className="flex items-center space-x-2.5">
          <Skeleton className="w-1.5 h-7 rounded-full bg-[#E5B869]/30" />
          <Skeleton className="h-8 w-60 rounded-xl" />
        </div>
        <Skeleton className="h-4 w-96 max-w-full rounded" />
      </div>

      {/* Featured Channel Banner */}
      <Skeleton className="w-full aspect-[21/9] max-h-[420px] rounded-2xl border border-white/5" />

      {/* Channel Grid */}
      <div className="space-y-4 pt-4">
        <Skeleton className="h-6 w-44 rounded-lg" />
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          {[...Array(12)].map((_, i) => (
            <MediaCardSkeleton key={i} aspectRatio="backdrop" />
          ))}
        </div>
      </div>
    </div>
  );
}

