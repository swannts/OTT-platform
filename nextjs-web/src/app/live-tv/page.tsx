import React from 'react';
import type { Metadata } from 'next';
import { getLiveTvChannelsDal } from '@/server/dal';
import { LiveTvSection } from '@/features/live-tv';
import { api } from '@/lib/api';

export const revalidate = 60; // ISR cache for 60 seconds

export async function generateMetadata(): Promise<Metadata> {
  const seo = await api.getSeoSettings().catch(() => null);
  const baseTitle = seo?.meta_title
    ? `Live Channels | ${seo.meta_title}`
    : 'Watch Live Islamic TV - Makkah & Madinah Broadcasts | NOOR';
  const description =
    seo?.meta_description ||
    'Watch 24/7 direct live broadcasts from Masjid Al-Haram in Makkah, Al-Masjid An-Nabawi in Madinah, Quran recitations, and scholarly lectures on NOOR Islamic Cinema.';
  const keywords =
    Array.isArray(seo?.meta_keywords) && seo.meta_keywords.length > 0
      ? ['Live TV', ...seo.meta_keywords]
      : [
          'Makkah live TV',
          'Madinah live TV',
          'Kaaba live stream',
          'Masjid Al-Haram live',
          'Masjid Nabawi live',
          'Islamic TV channel',
          'Live Quran TV',
          'NOOR Islamic Cinema',
        ];
  const imageUrl = seo?.seo_image || undefined;

  return {
    title: baseTitle,
    description,
    keywords,
    openGraph: {
      title: baseTitle,
      description,
      url: '/live-tv',
      siteName: 'NOOR Islamic Cinema',
      type: 'website',
      images: imageUrl ? [{ url: imageUrl, width: 1200, height: 630, alt: 'Live Islamic TV' }] : [],
    },
    twitter: {
      card: 'summary_large_image',
      title: baseTitle,
      description,
      images: imageUrl ? [imageUrl] : [],
    },
    alternates: {
      canonical: '/live-tv',
    },
  };
}

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
