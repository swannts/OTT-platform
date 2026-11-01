import React from 'react';
import type { Metadata } from 'next';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';
import VideosSection from '@/sections/videos/VideosSection';

export const revalidate = 60; // ISR cache for 60 seconds

export const metadata: Metadata = {
  title: 'Islamic Lectures, Waz & Bayan | NOOR',
  description:
    'Watch insightful Islamic lectures, Friday Khutbahs, Waz & Bayan, and scholarly discourses by renowned scholars on NOOR.',
  keywords: [
    'Islamic lectures',
    'Waz and Bayan',
    'Friday Khutbah',
    'Islamic scholars',
    'Quran recitation',
    'Islamic reminders',
    'NOOR Islamic Cinema',
  ],
  openGraph: {
    title: 'Islamic Lectures, Waz & Bayan | NOOR',
    description:
      'Watch insightful Islamic lectures, Friday Khutbahs, and scholarly discourses on NOOR.',
    url: 'https://noorcinema.com/videos',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1544717305-2782549b5136?w=1200&auto=format&fit=crop&q=80',
        width: 1200,
        height: 630,
        alt: 'Islamic Lectures & Bayans - NOOR',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Islamic Lectures, Waz & Bayan | NOOR',
    description:
      'Watch insightful Islamic lectures, Friday Khutbahs, and scholarly discourses on NOOR.',
    images: ['https://images.unsplash.com/photo-1544717305-2782549b5136?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/videos',
  },
};

export default async function VideosPage() {
  let videos: MediaItem[] = [];

  try {
    const res = await api.getVideos(1);
    const list = Array.isArray(res) ? res : res?.data || [];
    videos = Array.isArray(list) ? list.map(normalizeMediaItem) : [];
  } catch (err) {
    console.error('Failed to fetch videos on server:', err);
  }

  // Schema.org CollectionPage
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: 'Islamic Lectures, Waz & Bayan',
    description: 'Scholarly discourses and spiritual reminders on NOOR.',
    url: 'https://noorcinema.com/videos',
    isPartOf: {
      '@type': 'WebSite',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <VideosSection initialVideos={videos} />
    </>
  );
}
