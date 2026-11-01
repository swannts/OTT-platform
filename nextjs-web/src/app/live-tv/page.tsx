import React from 'react';
import type { Metadata } from 'next';
import { api } from '@/lib/api';
import { LiveChannel } from '@/types';
import LiveTvClient from '@/components/media/LiveTvClient';

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
  let channels: LiveChannel[] = [];

  try {
    const res = await api.getLiveTvDashboard();
    const rawChannels: any[] = [];

    if (res?.slider && Array.isArray(res.slider)) {
      rawChannels.push(...res.slider);
    }
    if (res?.category_data && Array.isArray(res.category_data)) {
      res.category_data.forEach((cat: any) => {
        if (cat.channel_data && Array.isArray(cat.channel_data)) {
          rawChannels.push(...cat.channel_data);
        }
      });
    }

    const seen = new Set();
    for (const c of rawChannels) {
      if (!c || seen.has(c.id)) continue;
      seen.add(c.id);
      channels.push({
        id: c.id,
        name: c.details?.name || c.name || `Live Channel ${c.id}`,
        category: c.details?.category || c.category || 'Live Stream',
        poster_image: c.poster_image || c.thumbnail_image,
        thumbnail_image: c.thumbnail_image || c.poster_image,
        server_url: c.server_url || c.server_url1 || 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        stream_type: c.stream_type || 'HLS',
        has_content_access: Boolean(c.details?.has_content_access ?? c.has_content_access ?? true),
        description: c.details?.description || c.description,
      });
    }
  } catch (err) {
    console.error('Failed to fetch live channels on server:', err);
  }

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
      <LiveTvClient initialChannels={channels} />
    </>
  );
}
