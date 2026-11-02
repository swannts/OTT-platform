import React from 'react';
import { DetailSkeleton } from '@/components/ui';

export default function TvShowDetailLoading() {
  return <DetailSkeleton hasEpisodes={true} />;
}

