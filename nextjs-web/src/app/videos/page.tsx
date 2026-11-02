import React from 'react';
import type { Metadata } from 'next';
import { getVideosDal } from '@/server/dal';
import { VideosSection } from '@/features/videos';

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
        url: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80',
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
    images: ['https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/videos',
  },
};

export default async function VideosPage() {
  const videos = await getVideosDal(1);

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
