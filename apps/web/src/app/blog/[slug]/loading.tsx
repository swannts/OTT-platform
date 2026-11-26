import React from 'react';
import { Skeleton } from '@/components/ui';

export default function BlogPostLoading() {
  return (
    <article className="min-h-screen bg-[#070a0e] text-white pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">
      <Skeleton className="h-4 w-28 rounded" />
      <Skeleton className="h-6 w-32 rounded-full bg-[#E5B869]/20" />
      <Skeleton className="h-10 sm:h-14 w-full rounded-2xl" />
      <div className="flex items-center space-x-3 pt-2">
        <Skeleton className="w-10 h-10 rounded-full" />
        <div className="space-y-1">
          <Skeleton className="h-4 w-32 rounded" />
          <Skeleton className="h-3 w-24 rounded" />
        </div>
      </div>
      <Skeleton className="w-full aspect-[16/9] rounded-2xl border border-white/5 my-6" />
      <div className="space-y-3 pt-4">
        <Skeleton className="h-4 w-full rounded" />
        <Skeleton className="h-4 w-full rounded" />
        <Skeleton className="h-4 w-5/6 rounded" />
        <Skeleton className="h-4 w-4/6 rounded" />
      </div>
      <div className="space-y-3 pt-4">
        <Skeleton className="h-4 w-full rounded" />
        <Skeleton className="h-4 w-11/12 rounded" />
        <Skeleton className="h-4 w-3/4 rounded" />
      </div>
    </article>
  );
}

