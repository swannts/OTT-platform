import React from 'react';
import { Skeleton, MediaCardSkeleton } from '@/components/ui';

export default function SearchLoading() {
  return (
    <div className="min-h-screen bg-[#070a0e] text-white pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
      <Skeleton className="h-12 w-full max-w-2xl rounded-2xl border border-white/10" />

      <div className="flex items-center space-x-2 pt-2">
        {[...Array(4)].map((_, i) => (
          <Skeleton key={i} className="h-8 w-20 rounded-full" />
        ))}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 pt-4">
        {[...Array(12)].map((_, i) => (
          <MediaCardSkeleton key={i} aspectRatio="poster" />
        ))}
      </div>
    </div>
  );
}

