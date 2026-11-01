import React from 'react';
import type { Metadata } from 'next';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';
import TvShowsClient from '@/components/media/TvShowsClient';

export const revalidate = 60; // ISR cache for 60 seconds

export const metadata: Metadata = {
  title: 'Islamic TV Shows & Docuseries | NOOR',
  description:
    'Watch inspiring Prophetic chronicles, Islamic history docuseries, and faith-based family series on NOOR. Pure, ad-free halal video entertainment.',
  keywords: [
    'Islamic TV shows',
    'Prophetic chronicles',
    'Islamic docuseries',
    'Halal series',
    'Islamic history',
    'Seerah series',
    'NOOR Islamic Cinema',
  ],
  openGraph: {
    title: 'Islamic TV Shows & Docuseries | NOOR',
    description:
      'Watch inspiring Prophetic chronicles, Islamic history docuseries, and faith-based family series on NOOR.',
    url: 'https://noorcinema.com/tv-shows',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80',
        width: 1200,
        height: 630,
        alt: 'Islamic TV Shows & Series - NOOR',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Islamic TV Shows & Docuseries | NOOR',
    description:
      'Watch inspiring Prophetic chronicles, Islamic history docuseries, and faith-based family series on NOOR.',
    images: ['https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/tv-shows',
  },
};

export default async function TvShowsPage() {
  let shows: MediaItem[] = [];

  try {
    const res = await api.getTvShows({ page: 1 });
    const list = Array.isArray(res) ? res : res?.data || [];
    shows = Array.isArray(list) ? list.map(normalizeMediaItem) : [];
  } catch (err) {
    console.error('Failed to fetch tv shows on server:', err);
  }

  // Schema.org CollectionPage
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: 'Islamic TV Shows & Series',
    description: 'Inspirational Prophetic chronicles, historical educational docuseries, and faith-based family series on NOOR.',
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
      <TvShowsClient initialShows={shows} />
    </>
  );
}
