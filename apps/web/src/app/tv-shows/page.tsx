import React from 'react';
import type { Metadata } from 'next';
import { getTvShowsCursorDal } from '@/server/dal';
import { TvShowsSection } from '@/features/tv-shows';

export const revalidate = 3600; // The catalog changes infrequently; refresh it hourly.

const pageDescription = 'Browse TV shows and series currently available to stream on NOOR.';

export const metadata: Metadata = {
  title: 'TV Shows & Series | NOOR',
  description: pageDescription,
  keywords: [
    'TV shows',
    'TV series',
    'streaming series',
    'NOOR',
  ],
  openGraph: {
    title: 'TV Shows & Series | NOOR',
    description: pageDescription,
    url: 'https://noorcinema.com/tv-shows',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80',
        width: 1200,
        height: 630,
        alt: 'TV Shows & Series - NOOR',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'TV Shows & Series | NOOR',
    description: pageDescription,
    images: ['https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/tv-shows',
  },
};

export default async function TvShowsPage() {
  const { items: shows, nextCursor, hasMore } = await getTvShowsCursorDal({ per_page: 12 });

  // Schema.org CollectionPage
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: 'TV Shows & Series',
    description: pageDescription,
    url: 'https://noorcinema.com/tv-shows',
    isPartOf: {
      '@type': 'WebSite',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    hasPart: shows.slice(0, 10).map((s) => ({
      '@type': 'TVSeries',
      name: s.name,
      description: s.description,
      image: s.poster_image,
      url: `https://noorcinema.com/tv-shows/${s.id}`,
    })),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <TvShowsSection
        initialShows={shows}
        initialCursor={nextCursor}
        initialHasMore={hasMore}
      />
    </>
  );
}
