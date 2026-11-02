import React from 'react';
import { Spinner, Skeleton } from '@/components/ui';

export default function WatchLoading() {
  return (
    <div className="min-h-screen bg-[#070a0e] text-white flex flex-col">
      {/* Player Screen Shimmer Area */}
      <div className="relative w-full aspect-video max-h-[80vh] bg-black flex items-center justify-center">
        <Skeleton className="absolute inset-0 w-full h-full rounded-none bg-white/[0.02]" />
        <div className="relative z-10">
          <Spinner size="lg" label="Preparing sacred stream..." />
        </div>
      </div>

      {/* Video metadata row */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full space-y-4">
        <Skeleton className="h-8 w-1/2 rounded-xl" />
        <div className="flex items-center space-x-3">
          <Skeleton className="h-4 w-16 rounded" />
          <Skeleton className="h-4 w-20 rounded" />
          <Skeleton className="h-4 w-24 rounded" />
        </div>
        <Skeleton className="h-4 w-3/4 rounded" />
      </div>
    </div>
  );
}

