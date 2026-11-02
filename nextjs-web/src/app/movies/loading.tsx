import React from 'react';
import { HeroBannerSkeleton, MediaRowSkeleton } from '@/components/ui';

export default function MoviesLoading() {
  return (
    <div className="min-h-screen bg-[#070a0e] text-white">
      <HeroBannerSkeleton />
      <div className="relative -mt-10 sm:-mt-16 z-10 space-y-8 pb-16">
        <MediaRowSkeleton aspectRatio="poster" cardCount={6} />
        <MediaRowSkeleton aspectRatio="backdrop" cardCount={4} />
      </div>
    </div>
  );
}

