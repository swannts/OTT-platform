import React from 'react';
import { DetailSkeleton } from '@/components/ui';

export default function MovieDetailLoading() {
  return <DetailSkeleton hasEpisodes={false} />;
}

