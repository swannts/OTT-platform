import React from 'react';
import { Skeleton } from '@/components/ui';

export default function BlogLoading() {
  return (
    <div className="min-h-screen bg-[#070a0e] text-white pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="text-center max-w-2xl mx-auto space-y-3">
        <Skeleton className="h-6 w-32 rounded-full mx-auto bg-[#E5B869]/20" />
        <Skeleton className="h-10 w-80 max-w-full rounded-xl mx-auto" />
        <Skeleton className="h-4 w-96 max-w-full rounded mx-auto" />
      </div>

      {/* Categories Bar */}
      <div className="flex items-center justify-center space-x-2 pt-2">
        {[...Array(5)].map((_, i) => (
          <Skeleton key={i} className="h-8 w-24 rounded-full" />
        ))}
      </div>

      {/* Blog Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-4">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="rounded-2xl bg-white/[0.02] border border-white/5 overflow-hidden space-y-3 p-4">
            <Skeleton className="w-full aspect-[16/10] rounded-xl" />
            <Skeleton className="h-4 w-24 rounded" />
            <Skeleton className="h-6 w-4/5 rounded-lg" />
            <Skeleton className="h-4 w-full rounded" />
            <Skeleton className="h-4 w-3/4 rounded" />
            <div className="flex items-center justify-between pt-2">
              <Skeleton className="h-4 w-20 rounded" />
              <Skeleton className="h-4 w-16 rounded" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

