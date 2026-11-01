import React from 'react';
import type { Metadata } from 'next';
import { getHomeInitialDataDal } from '@/server/dal';
import { HomeSection } from '@/features/home';

export const revalidate = 60; // ISR revalidate every 60s for high-speed SEO caching

export const metadata: Metadata = {
  title: 'NOOR - Islamic Video Streaming & Live TV | Halal Cinema & Sacred Documentaries',
  description:
    'Stream exclusive Islamic series, documentaries, live sacred broadcasts from Makkah and Madinah, and spiritual lectures on NOOR. Pure, ad-free halal family entertainment.',
  keywords: [
    'Islamic movies',
    'Halal streaming',
    'Makkah live TV',
    'Madinah live TV',
    'Islamic documentaries',
    'Prophetic series',
    'Quran recitation',
    'Islamic lectures',
    'NOOR Islamic Cinema',
  ],
  openGraph: {
    title: 'NOOR - Islamic Video Streaming & Live TV',
    description:
      'Stream exclusive Islamic series, documentaries, live sacred broadcasts from Makkah and Madinah, and spiritual lectures on NOOR.',
    url: 'https://noorcinema.com',
    siteName: 'NOOR Islamic Cinema',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80',
        width: 1200,
        height: 630,
        alt: 'Holy Kaaba Makkah - NOOR Islamic Cinema',
      },
    ],
    locale: 'en_US',
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'NOOR - Islamic Video Streaming & Live TV',
    description:
      'Stream exclusive Islamic series, documentaries, live sacred broadcasts from Makkah and Madinah, and spiritual lectures.',
    images: ['https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/',
  },
};

export default async function HomePage() {
  const {
    banners,
    top10,
    latestMovies,
    popularMovies,
    payPerView,
    tvShows,
    liveChannels,
  } = await getHomeInitialDataDal();

  // Schema.org Structured JSON-LD Data
  const jsonLd = {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'WebSite',
        '@id': 'https://noorcinema.com/#website',
        url: 'https://noorcinema.com',
        name: 'NOOR Islamic Cinema',
        description: 'Halal video streaming platform for Islamic movies, series, and live holy mosque broadcasts.',
        potentialAction: [
          {
            '@type': 'SearchAction',
            target: {
              '@type': 'EntryPoint',
              urlTemplate: 'https://noorcinema.com/search?q={search_term_string}',
            },
            'query-input': 'required name=search_term_string',
          },
        ],
      },
      {
        '@type': 'Organization',
        '@id': 'https://noorcinema.com/#organization',
        name: 'NOOR Islamic Cinema',
        url: 'https://noorcinema.com',
        logo: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=300&auto=format&fit=crop&q=80',
      },
    ],
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <HomeSection
        initialBanners={banners}
        initialTop10={top10}
        initialLatestMovies={latestMovies}
        initialPopularMovies={popularMovies}
        initialPayPerView={payPerView}
        initialTvShows={tvShows}
        initialLiveChannels={liveChannels}
      />
    </>
  );
}
