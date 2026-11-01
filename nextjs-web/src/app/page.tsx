import React from 'react';
import type { Metadata } from 'next';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, LiveChannel } from '@/types';
import HomeClient from '@/components/home/HomeClient';

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
  let banners: MediaItem[] = [];
  let top10: MediaItem[] = [];
  let latestMovies: MediaItem[] = [];
  let popularMovies: MediaItem[] = [];
  let payPerView: MediaItem[] = [];
  let tvShows: MediaItem[] = [];
  let liveChannels: LiveChannel[] = [];

  try {
    const [bannersRes, dashboardRes, liveRes, tvRes] = await Promise.allSettled([
      api.getBannerData('home'),
      api.getDashboard(),
      api.getLiveTvDashboard(),
      api.getTvShows({ page: 1 }),
    ]);

    if (bannersRes.status === 'fulfilled' && Array.isArray(bannersRes.value)) {
      banners = bannersRes.value;
    }

    if (dashboardRes.status === 'fulfilled' && dashboardRes.value) {
      let d = dashboardRes.value;
      if (d && typeof d === 'object' && 'data' in d && d.data) {
        d = d.data;
      }
      const top10Raw = d?.top_10?.data || (Array.isArray(d?.top_10) ? d.top_10 : []);
      if (Array.isArray(top10Raw) && top10Raw.length > 0) {
        top10 = top10Raw.map(normalizeMediaItem);
      }

      const latestRaw = d?.latest_movie?.data || (Array.isArray(d?.latest_movie) ? d.latest_movie : []);
      if (Array.isArray(latestRaw) && latestRaw.length > 0) {
        latestMovies = latestRaw.map(normalizeMediaItem);
      }

      const popRaw = d?.popular_movie?.data || (Array.isArray(d?.popular_movie) ? d.popular_movie : []);
      if (Array.isArray(popRaw) && popRaw.length > 0) {
        popularMovies = popRaw.map(normalizeMediaItem);
      }

      const ppvRaw = d?.pay_per_view?.data || (Array.isArray(d?.pay_per_view) ? d.pay_per_view : []);
      if (Array.isArray(ppvRaw) && ppvRaw.length > 0) {
        payPerView = ppvRaw.map(normalizeMediaItem);
      }
    }

    // Ensure Trending Now is always populated for SEO and instant render
    if (latestMovies.length === 0) {
      try {
        const m = await api.getMovies({ page: 1 });
        const list = Array.isArray(m) ? m : m?.data || [];
        if (Array.isArray(list) && list.length > 0) {
          latestMovies = list.map(normalizeMediaItem);
        }
      } catch (e) {
        console.error('Fallback movie fetch error:', e);
      }
    }

    if (liveRes.status === 'fulfilled' && liveRes.value) {
      const l = liveRes.value;
      if (Array.isArray(l.slider) && l.slider.length > 0) {
        liveChannels = l.slider;
      } else if (Array.isArray(l.category_data)) {
        liveChannels = l.category_data.flatMap((c: any) => c.channel_data || []);
      }
    }

    if (tvRes.status === 'fulfilled' && tvRes.value) {
      const t = tvRes.value;
      const list = Array.isArray(t) ? t : t?.data || [];
      tvShows = Array.isArray(list) ? list.map(normalizeMediaItem) : [];
    }
  } catch (error) {
    console.error('Error fetching homepage SSR content:', error);
  }

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
      <HomeClient
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
