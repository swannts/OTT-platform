import React from 'react';
import type { Metadata } from 'next';
import { getLiveTvChannelsDal } from '@/server/dal';
import { LiveTvSection } from '@/features/live-tv';

export const revalidate = 60; // ISR cache for 60 seconds

export const metadata: Metadata = {
  title: 'Watch Live Islamic TV - Makkah & Madinah Broadcasts | NOOR',
  description:
    'Watch 24/7 direct live broadcasts from Masjid Al-Haram in Makkah, Al-Masjid An-Nabawi in Madinah, Quran recitations, and scholarly lectures on NOOR Islamic Cinema.',
  keywords: [
    'Makkah live TV',
    'Madinah live TV',
    'Kaaba live stream',
    'Masjid Al-Haram live',
    'Masjid Nabawi live',
    'Islamic TV channel',
    'Live Quran TV',
    'NOOR Islamic Cinema',
  ],
  openGraph: {
    title: 'Watch Live Islamic TV - Makkah & Madinah Broadcasts | NOOR',
    description:
      'Watch 24/7 direct live broadcasts from Masjid Al-Haram in Makkah and Al-Masjid An-Nabawi in Madinah on NOOR.',
    url: 'https://noorcinema.com/live-tv',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80',
        width: 1200,
        height: 630,
        alt: 'Live Islamic TV - Makkah & Madinah',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Watch Live Islamic TV - Makkah & Madinah Broadcasts | NOOR',
    description:
      'Watch 24/7 direct live broadcasts from Masjid Al-Haram in Makkah and Al-Masjid An-Nabawi in Madinah.',
    images: ['https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/live-tv',
  },
};

export default async function LiveTvPage() {
  const channels = await getLiveTvChannelsDal();

  // Schema.org BroadcastService
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'BroadcastService',
    name: 'NOOR Live Islamic Television',
    broadcastDisplayName: 'NOOR Live TV',
    videoFormat: 'HD',
    broadcaster: {
      '@type': 'Organization',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    areaServed: 'Worldwide',
    hasBroadcastChannel: channels.map((c) => ({
      '@type': 'BroadcastChannel',
      name: c.name,
      broadcastChannelId: c.id,
      genre: c.category,
    })),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <LiveTvSection initialChannels={channels} />
    </>
  );
}
